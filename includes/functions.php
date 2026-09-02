<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------------
 * General-purpose helper functions shared across the whole system
 * (not just the login module). Keeping them here avoids repeating
 * the same sanitation/validation code in every module.
 * -----------------------------------------------------------------
 */

/**
 * Clean a raw string coming from user input.
 * - Trims whitespace
 * - Strips slashes (in case magic quotes-style input sneaks in)
 * - Converts special characters to HTML entities to prevent
 *   stored/reflected XSS when the value is later echoed back.
 *
 * @param string $data
 * @return string
 */
function sanitize_input(string $data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate (or reuse) a CSRF token for the current session and
 * return it. Call this when rendering any form.
 *
 * @return string
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token against the one stored in session.
 * Uses hash_equals() to prevent timing attacks.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect the browser to a given path and stop script execution.
 * Centralized so every module redirects the same way.
 *
 * @param string $path
 * @return void
 */
function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Return a human-readable label for a role code.
 * Used in the UI (e.g. dashboard header, user management table).
 *
 * @param string $role
 * @return string
 */
function role_label(string $role): string
{
    $labels = [
        'bhw'     => 'Barangay Health Worker',
        'midwife' => 'Midwife',
        'ipho'    => 'IPHO',
    ];
    return $labels[$role] ?? ucfirst($role);
}
