<?php
/**
 * users table access.
 */

function getUserByEmail(mysqli $conn, string $email): ?array
{
    $sql = 'SELECT * FROM users WHERE email = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $user ?: null;
}

function getUserById(mysqli $conn, int $id): ?array
{
    $sql = 'SELECT * FROM users WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $user ?: null;
}

function getAllUsers(mysqli $conn): array
{
    $sql = 'SELECT id, name, email, role, status, created_at FROM users ORDER BY name';
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function emailExists(mysqli $conn, string $email, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $sql = 'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $email, $excludeId);
    } else {
        $sql = 'SELECT id FROM users WHERE email = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

/** Creates a user account. There is no public self-registration - only an
 * existing BHW (the system's admin role) can create accounts. */
function createUser(mysqli $conn, string $name, string $email, string $plainPassword, string $role): int
{
    $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
    $sql = 'INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, "active")';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $hash, $role);
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $newId;
}

function updateUser(mysqli $conn, int $id, string $name, string $email, string $role): bool
{
    $sql = 'UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $role, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function updateUserPassword(mysqli $conn, int $id, string $plainPassword): bool
{
    $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
    $sql = 'UPDATE users SET password = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function setUserStatus(mysqli $conn, int $id, string $status): bool
{
    $sql = 'UPDATE users SET status = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}
