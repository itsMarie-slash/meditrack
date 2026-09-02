<?php
/**
 * modules/auth/login.php
 * -----------------------------------------------------------------
 * Displays the login form. If the user is already logged in, send
 * them straight to the dashboard instead of showing the form again.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (is_logged_in()) {
    redirect_to('/meditrack/modules/dashboard/index.php');
}

// Pull a one-time error message set by process_login.php (Post/Redirect/Get pattern)
$errorMessage = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// Re-populate the last submitted username so the user doesn't retype it
$oldUsername = $_SESSION['old_username'] ?? '';
unset($_SESSION['old_username']);

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediTrack</title>
    <link rel="stylesheet" href="/meditrack/assets/css/style.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <h1>MediTrack</h1>
            <p>New Bulatukan Barangay Health Center</p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form id="loginForm" action="process_login.php" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username"
                       value="<?php echo htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="field-error" id="usernameError"></span>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password">
                <span class="field-error" id="passwordError"></span>
            </div>

            <button type="submit" class="btn" id="loginBtn">Sign In</button>
        </form>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> MediTrack &mdash; Barangay Health Center System
        </div>
    </div>
</div>

<script src="/meditrack/assets/js/login.js"></script>
</body>
</html>
