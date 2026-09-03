<?php
/**
 * medicines + inventory_transactions table access.
 */

function getAllMedicines(mysqli $conn, bool $activeOnly = true): array
{
    $sql = $activeOnly
        ? "SELECT * FROM medicines WHERE status = 'active' ORDER BY name"
        : 'SELECT * FROM medicines ORDER BY name';
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getMedicineById(mysqli $conn, int $id): ?array
{
    $sql = 'SELECT * FROM medicines WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function addMedicine(mysqli $conn, array $data): int
{
    $sql = 'INSERT INTO medicines (name, category, unit, current_stock, reorder_threshold, expiry_date, date_received)
            VALUES (?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'sssiiss',
        $data['name'],
        $data['category'],
        $data['unit'],
        $data['current_stock'],
        $data['reorder_threshold'],
        $data['expiry_date'],
        $data['date_received']
    );
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $newId;
}

function updateMedicine(mysqli $conn, int $id, array $data): bool
{
    $sql = 'UPDATE medicines SET name = ?, category = ?, unit = ?, reorder_threshold = ?,
            expiry_date = ?, date_received = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'sssissi',
        $data['name'],
        $data['category'],
        $data['unit'],
        $data['reorder_threshold'],
        $data['expiry_date'],
        $data['date_received'],
        $id
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function setMedicineStatus(mysqli $conn, int $id, string $status): bool
{
    $sql = 'UPDATE medicines SET status = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Adjust current_stock by a signed delta (positive for received/adjusted-up,
 * negative for dispensed/expired/adjusted-down) and log the transaction.
 * Caller is responsible for wrapping this in a transaction when it must be
 * atomic with other writes (see recordDistributionDispensed() in Distribution.php).
 */
function adjustMedicineStock(mysqli $conn, int $medicineId, string $type, int $signedQuantity, string $notes, int $userId): bool
{
    $sql = 'UPDATE medicines SET current_stock = current_stock + ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $signedQuantity, $medicineId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return false;
    }

    $logQty = abs($signedQuantity);
    $sql2 = 'INSERT INTO inventory_transactions (medicine_id, type, quantity, notes, created_by) VALUES (?, ?, ?, ?, ?)';
    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, 'isisi', $medicineId, $type, $logQty, $notes, $userId);
    $ok2 = mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);
    return $ok2;
}

function getInventoryTransactions(mysqli $conn, int $medicineId, int $limit = 50): array
{
    $sql = 'SELECT it.*, u.name AS user_name
            FROM inventory_transactions it
            LEFT JOIN users u ON u.id = it.created_by
            WHERE it.medicine_id = ?
            ORDER BY it.created_at DESC
            LIMIT ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $medicineId, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Medicines at or below their reorder threshold. */
function getLowStockMedicines(mysqli $conn): array
{
    $sql = "SELECT * FROM medicines WHERE status = 'active' AND current_stock <= reorder_threshold ORDER BY current_stock ASC";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/** Medicines expiring within $days days (and not already expired). */
function getNearExpiryMedicines(mysqli $conn, int $days = 60): array
{
    $sql = "SELECT * FROM medicines
            WHERE status = 'active' AND expiry_date IS NOT NULL
              AND expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY expiry_date ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $days);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function getExpiredMedicines(mysqli $conn): array
{
    $sql = "SELECT * FROM medicines WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE() ORDER BY expiry_date ASC";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
