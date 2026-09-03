<?php
/**
 * senior_citizens table access (the beneficiary directory).
 */

/**
 * Searchable/filterable/paginated directory listing.
 *
 * @param array $filters ['search' => string, 'purok_zone' => string, 'medicine' => string, 'status' => string]
 */
function getSeniorCitizens(mysqli $conn, array $filters, string $sortBy, string $sortDir, int $limit, int $offset): array
{
    [$where, $types, $params] = buildSeniorCitizenWhere($filters);

    $allowedSort = ['full_name', 'purok_zone', 'birthdate', 'created_at'];
    if (!in_array($sortBy, $allowedSort, true)) {
        $sortBy = 'full_name';
    }
    $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

    $sql = "SELECT * FROM senior_citizens $where ORDER BY $sortBy $sortDir LIMIT ? OFFSET ?";
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

function countSeniorCitizens(mysqli $conn, array $filters): int
{
    [$where, $types, $params] = buildSeniorCitizenWhere($filters);
    $sql = "SELECT COUNT(*) AS total FROM senior_citizens $where";
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

/** Shared WHERE builder for the listing + count queries above. */
function buildSeniorCitizenWhere(array $filters): array
{
    $conditions = [];
    $types = '';
    $params = [];

    if (!empty($filters['search'])) {
        $conditions[] = '(full_name LIKE ? OR senior_id_number LIKE ? OR contact_number LIKE ?)';
        $like = '%' . $filters['search'] . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($filters['purok_zone'])) {
        $conditions[] = 'purok_zone = ?';
        $types .= 's';
        $params[] = $filters['purok_zone'];
    }
    if (!empty($filters['medicine'])) {
        $conditions[] = 'maintenance_medicine LIKE ?';
        $types .= 's';
        $params[] = '%' . $filters['medicine'] . '%';
    }
    if (!empty($filters['status'])) {
        $conditions[] = 'status = ?';
        $types .= 's';
        $params[] = $filters['status'];
    } else {
        $conditions[] = "status != 'inactive'";
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $types, $params];
}

function getSeniorCitizenById(mysqli $conn, int $id): ?array
{
    $sql = 'SELECT * FROM senior_citizens WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/** All distinct Purok/Zone values, for the filter dropdown. */
function getDistinctPurokZones(mysqli $conn): array
{
    $sql = "SELECT DISTINCT purok_zone FROM senior_citizens WHERE status != 'inactive' ORDER BY purok_zone";
    $result = mysqli_query($conn, $sql);
    return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'purok_zone');
}

function addSeniorCitizen(mysqli $conn, array $data): int
{
    $sql = 'INSERT INTO senior_citizens
        (full_name, birthdate, sex, purok_zone, address, contact_number, senior_id_number,
         maintenance_medicine, health_conditions, allergies, emergency_contact_name,
         emergency_contact_number, latitude, longitude, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssssssddi',
        $data['full_name'],
        $data['birthdate'],
        $data['sex'],
        $data['purok_zone'],
        $data['address'],
        $data['contact_number'],
        $data['senior_id_number'],
        $data['maintenance_medicine'],
        $data['health_conditions'],
        $data['allergies'],
        $data['emergency_contact_name'],
        $data['emergency_contact_number'],
        $data['latitude'],
        $data['longitude'],
        $data['created_by']
    );
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $newId;
}

function updateSeniorCitizen(mysqli $conn, int $id, array $data): bool
{
    $sql = 'UPDATE senior_citizens SET
        full_name = ?, birthdate = ?, sex = ?, purok_zone = ?, address = ?,
        contact_number = ?, senior_id_number = ?, maintenance_medicine = ?,
        health_conditions = ?, allergies = ?, emergency_contact_name = ?,
        emergency_contact_number = ?, latitude = ?, longitude = ?
        WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssssssddi',
        $data['full_name'],
        $data['birthdate'],
        $data['sex'],
        $data['purok_zone'],
        $data['address'],
        $data['contact_number'],
        $data['senior_id_number'],
        $data['maintenance_medicine'],
        $data['health_conditions'],
        $data['allergies'],
        $data['emergency_contact_name'],
        $data['emergency_contact_number'],
        $data['latitude'],
        $data['longitude'],
        $id
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/** Soft-delete: flips status to inactive rather than DELETE, since
 * distribution records reference this beneficiary. */
function setSeniorCitizenStatus(mysqli $conn, int $id, string $status): bool
{
    $sql = 'UPDATE senior_citizens SET status = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function seniorIdNumberExists(mysqli $conn, string $seniorIdNumber, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $sql = 'SELECT id FROM senior_citizens WHERE senior_id_number = ? AND id != ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $seniorIdNumber, $excludeId);
    } else {
        $sql = 'SELECT id FROM senior_citizens WHERE senior_id_number = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $seniorIdNumber);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

/** All active beneficiaries with a registered mobile number + coordinates, for SMS/GIS. */
function getAllActiveSeniorCitizens(mysqli $conn): array
{
    $sql = "SELECT * FROM senior_citizens WHERE status = 'active' ORDER BY full_name";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function countActiveSeniorCitizens(mysqli $conn): int
{
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM senior_citizens WHERE status = 'active'");
    return (int) mysqli_fetch_assoc($result)['total'];
}
