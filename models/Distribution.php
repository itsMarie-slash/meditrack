<?php
/**
 * distributions table access, including the transactional "mark as
 * dispensed" flow that keeps distributions + inventory_transactions +
 * medicines.current_stock in sync (see recordDistributionDispensed()).
 */

require_once __DIR__ . '/Medicine.php';

function getDistributions(mysqli $conn, array $filters, int $limit, int $offset): array
{
    [$where, $types, $params] = buildDistributionWhere($filters);
    $sql = "SELECT d.*, s.full_name AS senior_name, s.purok_zone, m.name AS medicine_name, m.unit
            FROM distributions d
            JOIN senior_citizens s ON s.id = d.senior_citizen_id
            JOIN medicines m ON m.id = d.medicine_id
            $where
            ORDER BY d.scheduled_date DESC, d.id DESC
            LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function countDistributions(mysqli $conn, array $filters): int
{
    [$where, $types, $params] = buildDistributionWhere($filters);
    $sql = "SELECT COUNT(*) AS total
            FROM distributions d
            JOIN senior_citizens s ON s.id = d.senior_citizen_id
            JOIN medicines m ON m.id = d.medicine_id
            $where";
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (int) $row['total'];
}

function buildDistributionWhere(array $filters): array
{
    $conditions = [];
    $types = '';
    $params = [];

    if (!empty($filters['status'])) {
        $conditions[] = 'd.status = ?';
        $types .= 's';
        $params[] = $filters['status'];
    }
    if (!empty($filters['senior_citizen_id'])) {
        $conditions[] = 'd.senior_citizen_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['senior_citizen_id'];
    }
    if (!empty($filters['medicine_id'])) {
        $conditions[] = 'd.medicine_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['medicine_id'];
    }
    if (!empty($filters['search'])) {
        $conditions[] = '(s.full_name LIKE ? OR m.name LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $types, $params];
}

function getDistributionById(mysqli $conn, int $id): ?array
{
    $sql = 'SELECT d.*, s.full_name AS senior_name, s.contact_number, m.name AS medicine_name, m.unit
            FROM distributions d
            JOIN senior_citizens s ON s.id = d.senior_citizen_id
            JOIN medicines m ON m.id = d.medicine_id
            WHERE d.id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function addDistribution(mysqli $conn, array $data): int
{
    $sql = 'INSERT INTO distributions (senior_citizen_id, medicine_id, quantity, scheduled_date, status, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    $status = $data['status'] ?? 'Scheduled';
    mysqli_stmt_bind_param(
        $stmt,
        'iiisssi',
        $data['senior_citizen_id'],
        $data['medicine_id'],
        $data['quantity'],
        $data['scheduled_date'],
        $status,
        $data['notes'],
        $data['recorded_by']
    );
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $newId;
}

function updateDistribution(mysqli $conn, int $id, array $data): bool
{
    $sql = 'UPDATE distributions SET senior_citizen_id = ?, medicine_id = ?, quantity = ?,
            scheduled_date = ?, notes = ? WHERE id = ? AND status = "Scheduled"';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'iiissi',
        $data['senior_citizen_id'],
        $data['medicine_id'],
        $data['quantity'],
        $data['scheduled_date'],
        $data['notes'],
        $id
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Mark a distribution as "Not Dispensed" or "Missed" (no stock impact).
 */
function updateDistributionStatus(mysqli $conn, int $id, string $status): bool
{
    $sql = 'UPDATE distributions SET status = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * The core "did this senior citizen actually receive their medicine" flow
 * (requirement 4.4). Runs as a single mysqli transaction so the
 * distribution row, the inventory_transactions audit row, and the
 * medicines.current_stock counter never drift out of sync even if
 * something fails halfway through.
 *
 * Returns ['ok' => bool, 'error' => string|null].
 */
function recordDistributionDispensed(mysqli $conn, int $distributionId, int $userId): array
{
    $distribution = getDistributionById($conn, $distributionId);
    if (!$distribution) {
        return ['ok' => false, 'error' => 'Distribution record not found.'];
    }
    if ($distribution['status'] === 'Dispensed') {
        return ['ok' => false, 'error' => 'This record is already marked as dispensed.'];
    }

    $medicine = getMedicineById($conn, (int) $distribution['medicine_id']);
    if (!$medicine) {
        return ['ok' => false, 'error' => 'Medicine record not found.'];
    }
    if ((int) $medicine['current_stock'] < (int) $distribution['quantity']) {
        return ['ok' => false, 'error' => 'Not enough stock on hand to dispense this quantity.'];
    }

    mysqli_begin_transaction($conn);
    try {
        // 1. Insert the inventory_transactions audit row + decrement stock.
        $decremented = adjustMedicineStock(
            $conn,
            (int) $distribution['medicine_id'],
            'dispensed',
            -1 * (int) $distribution['quantity'],
            'Distribution #' . $distributionId,
            $userId
        );
        if (!$decremented) {
            throw new RuntimeException('Failed to update medicine stock.');
        }

        // 2. Update the distribution row itself.
        $sql = 'UPDATE distributions SET status = "Dispensed", dispensed_date = CURDATE() WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $distributionId);
        $updated = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$updated) {
            throw new RuntimeException('Failed to update distribution status.');
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('recordDistributionDispensed failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'A database error occurred while dispensing. No changes were saved.'];
    }

    return ['ok' => true, 'error' => null, 'medicine_id' => (int) $distribution['medicine_id']];
}

/** Full distribution history for one beneficiary, most recent first. */
function getDistributionsBySeniorCitizen(mysqli $conn, int $seniorCitizenId): array
{
    $sql = 'SELECT d.*, m.name AS medicine_name, m.unit
            FROM distributions d
            JOIN medicines m ON m.id = d.medicine_id
            WHERE d.senior_citizen_id = ?
            ORDER BY d.scheduled_date DESC';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $seniorCitizenId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Dispensed quantity per medicine, grouped into distribution "cycles" by
 * scheduled_date. Used by ForecastService as the historical input series.
 * Returns rows ordered oldest -> newest: [['period' => 'YYYY-MM-DD', 'total_quantity' => int], ...]
 */
function getDispensedHistoryByMedicine(mysqli $conn, int $medicineId, int $cycles): array
{
    $sql = "SELECT scheduled_date AS period, SUM(quantity) AS total_quantity
            FROM distributions
            WHERE medicine_id = ? AND status = 'Dispensed'
            GROUP BY scheduled_date
            ORDER BY scheduled_date DESC
            LIMIT ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $medicineId, $cycles);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array_reverse($rows); // oldest first
}

/** Distributions scheduled within the next $daysAhead days that haven't had a reminder SMS yet. */
function getUpcomingDistributionsNeedingReminder(mysqli $conn, int $daysAhead): array
{
    $sql = "SELECT d.*, s.full_name AS senior_name, s.contact_number, m.name AS medicine_name
            FROM distributions d
            JOIN senior_citizens s ON s.id = d.senior_citizen_id
            JOIN medicines m ON m.id = d.medicine_id
            WHERE d.status = 'Scheduled'
              AND d.reminder_sent = 0
              AND d.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY d.scheduled_date ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $daysAhead);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function markReminderSent(mysqli $conn, int $distributionId): void
{
    $sql = 'UPDATE distributions SET reminder_sent = 1 WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $distributionId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** Counts for the dashboard: how many distributions are due within the coming week. */
function countDistributionsByStatus(mysqli $conn, string $status): int
{
    $sql = 'SELECT COUNT(*) AS total FROM distributions WHERE status = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (int) $row['total'];
}

/** Latest distribution status per beneficiary (for GIS marker coloring). */
function getLatestDistributionStatusBySeniorCitizen(mysqli $conn): array
{
    $sql = 'SELECT d1.senior_citizen_id, d1.status, d1.scheduled_date, d1.medicine_id, m.name AS medicine_name
            FROM distributions d1
            JOIN medicines m ON m.id = d1.medicine_id
            INNER JOIN (
                SELECT senior_citizen_id, MAX(scheduled_date) AS max_date
                FROM distributions
                GROUP BY senior_citizen_id
            ) latest ON latest.senior_citizen_id = d1.senior_citizen_id AND latest.max_date = d1.scheduled_date';
    $result = mysqli_query($conn, $sql);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $bySenior = [];
    foreach ($rows as $row) {
        $bySenior[(int) $row['senior_citizen_id']] = $row;
    }
    return $bySenior;
}
