<?php
/**
 * sms_logs table access.
 */

function logSms(mysqli $conn, ?int $seniorCitizenId, string $phoneNumber, string $message, string $type, string $status, string $responseNotes = ''): int
{
    $sql = 'INSERT INTO sms_logs (senior_citizen_id, phone_number, message, type, status, response_notes)
            VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isssss', $seniorCitizenId, $phoneNumber, $message, $type, $status, $responseNotes);
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $newId;
}

function getSmsLogs(mysqli $conn, int $limit = 100, int $offset = 0): array
{
    $sql = 'SELECT sl.*, s.full_name AS senior_name
            FROM sms_logs sl
            LEFT JOIN senior_citizens s ON s.id = sl.senior_citizen_id
            ORDER BY sl.sent_at DESC
            LIMIT ? OFFSET ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function countSmsLogs(mysqli $conn): int
{
    $result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM sms_logs');
    return (int) mysqli_fetch_assoc($result)['total'];
}
