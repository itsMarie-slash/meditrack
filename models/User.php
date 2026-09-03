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
