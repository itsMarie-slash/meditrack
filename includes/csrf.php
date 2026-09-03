<?php
/**
 * Manual CSRF protection. Generate a token into $_SESSION when rendering a
 * form, embed it as a hidden input via csrfField(), and verify it on the
 * POST handler with requireValidCsrf() before touching the database.
 */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Echo a ready-to-use hidden <input> for forms. */
function csrfField(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function isValidCsrf(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && !empty($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Call at the top of every POST handler. Stops the request on failure. */
function requireValidCsrf(): void
{
    $token = $_POST['csrf_token'] ?? null;
    if (!isValidCsrf($token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}
