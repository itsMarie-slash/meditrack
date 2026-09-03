<?php
/**
 * Shared helper functions used across the app.
 * Assumes config/database.php (for $conn) has already been included by
 * whatever page requires this file.
 */

require_once __DIR__ . '/../models/ActivityLog.php';

/**
 * Trim + convert to a safe string for storage. This is NOT output escaping
 * (use htmlspecialchars() at print time) - it just normalizes input before
 * it goes into a prepared statement.
 */
function sanitizeInput(string $value): string
{
    return trim($value);
}

/** Escape a value for safe HTML output. Use on every user-supplied value. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Philippine mobile format: 09XXXXXXXXX (11 digits, starts with 09). */
function isValidPhilippineMobile(string $number): bool
{
    return (bool) preg_match('/^09\d{9}$/', $number);
}

function formatDate(?string $date, string $format = 'M d, Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

function formatDateTime(?string $datetime, string $format = 'M d, Y g:i A'): string
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '-';
}

function calculateAge(string $birthdate): int
{
    $bd = new DateTime($birthdate);
    $today = new DateTime('today');
    return $bd->diff($today)->y;
}

/** Record an entry in activity_logs. Call after every create/update/delete. */
function logActivity(mysqli $conn, int $userId, string $action, string $description = ''): void
{
    addActivityLog($conn, $userId, $action, $description);
}

/** Redirect and stop execution. */
function redirectTo(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Simple flash-message helper stored in the session for one page load. */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Pagination helper: clamp + compute OFFSET from the current page number. */
function paginate(int $page, int $perPage, int $totalRows): array
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'totalPages' => $totalPages, 'offset' => $offset];
}
