<?php
/**
 * Session bootstrap + role-based access control.
 *
 * Every protected page must:
 *   require_once __DIR__ . '/../includes/auth.php';
 *   requireRole(['bhw', 'midwife']); // whichever roles may view this page
 *
 * This is the ONLY access-control gate the app relies on - menu links being
 * hidden is a UX nicety, not a security boundary.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie before starting it.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

/**
 * Guard for the top of every protected page. Redirects to login if not
 * authenticated, or shows a 403 if the logged-in role isn't in $allowedRoles.
 *
 * Note: in this system the "bhw" role is treated as the full-access/admin
 * role - it has unrestricted CRUD everywhere. "midwife" mirrors bhw for
 * beneficiary/inventory/distribution data. "ipho" is read-only except for
 * forecasting/report/export pages.
 */
function requireRole(array $allowedRoles): array
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to continue.');
        redirectTo('/index.php');
    }

    $role = $_SESSION['user_role'];
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="font-family:sans-serif;text-align:center;margin-top:80px;">'
            . '<h1>403 - Access Denied</h1><p>Your account role does not have permission to view this page.</p>'
            . '<p><a href="/index.php">Return to login</a></p></body></html>';
        exit;
    }

    return currentUser();
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
