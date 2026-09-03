<?php
/**
 * activity_logs table access.
 */

function addActivityLog(mysqli $conn, int $userId, string $action, string $description = ''): void
{
    $sql = 'INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iss', $userId, $action, $description);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** Latest activity log entries, most recent first. */
function getRecentActivityLogs(mysqli $conn, int $limit = 20): array
{
    $sql = 'SELECT al.*, u.name AS user_name
            FROM activity_logs al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC
            LIMIT ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}
