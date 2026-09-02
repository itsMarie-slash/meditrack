<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------
 * Session and access-control helpers for Module 1 (Login & Auth).
 * Every protected page (dashboard, inventory, etc.) will start with:
 *
 *      require_once __DIR__ . '/../../includes/auth.php';
 *      require_login();
 *
 * and, if the page is role-specific:
 *
 *      require_role(['bhw', 'midwife']);
 * -----------------------------------------------------------------
 */

// Start the session once, here, so every file that includes auth.php
// automatically has session access without repeating session_start().
if (session_status() === PHP_SESSION_NONE) {

    // Harden the session cookie before starting the session.
    session_set_cookie_params([
        'lifetime' => 0,        // expires when the browser closes
        'path'     => '/',
        'httponly' => true,     // JS cannot read the cookie (mitigates XSS theft)
        'samesite' => 'Lax',    // basic CSRF mitigation for cross-site requests
    ]);
    session_start();
}

/**
 * Check whether a user is currently logged in.
 *
 * @return bool
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Force the visitor to be logged in to continue.
 * Redirects to the login page if not authenticated.
 *
 * @return void
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect_to('/meditrack/modules/auth/login.php');
    }
}

/**
 * Restrict a page to specific roles only.
 * Must be called after require_login().
 *
 * @param array $allowedRoles e.g. ['bhw'], ['bhw', 'midwife']
 * @return void
 */
function require_role(array $allowedRoles): void
{
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('Access denied: you do not have permission to view this page.');
    }
}

/**
 * Store the authenticated user's data in the session.
 *
 * @param array $user Row fetched from the users table.
 * @return void
 */
function login_user(array $user): void
{
    // Regenerate the session ID on privilege change (login) to
    // prevent session fixation attacks.
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
}

/**
 * Clear all session data and destroy the session (logout).
 *
 * @return void
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
