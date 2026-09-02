<?php
/**
 * modules/auth/process_login.php
 * -----------------------------------------------------------------
 * Handles the login form submission (POST only).
 * - Validates input server-side (never trust the client)
 * - Verifies CSRF token
 * - Applies simple brute-force lockout using login_attempts
 * - Verifies credentials with password_verify() (bcrypt)
 * - Starts the session via login_user() on success
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only allow POST requests to this endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/meditrack/modules/auth/login.php');
}

// --- CSRF check ---
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $_SESSION['login_error'] = 'Invalid session. Please try again.';
    redirect_to('/meditrack/modules/auth/login.php');
}

$username = sanitize_input($_POST['username'] ?? '');
$password = $_POST['password'] ?? ''; // do not sanitize/alter the password itself, just validate presence

// --- Server-side validation (mirrors login.js rules) ---
if ($username === '' || $password === '') {
    $_SESSION['login_error']  = 'Username and password are required.';
    $_SESSION['old_username'] = $username;
    redirect_to('/meditrack/modules/auth/login.php');
}

if (strlen($username) < 3 || strlen($password) < 6) {
    $_SESSION['login_error']  = 'Invalid username or password format.';
    $_SESSION['old_username'] = $username;
    redirect_to('/meditrack/modules/auth/login.php');
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// --- Brute-force lockout: block after 5 failed attempts within 5 minutes ---
$lockoutStmt = $pdo->prepare(
    'SELECT COUNT(*) AS attempt_count
     FROM login_attempts
     WHERE username = :username
       AND was_successful = 0
       AND attempted_at > (NOW() - INTERVAL 5 MINUTE)'
);
$lockoutStmt->execute(['username' => $username]);
$attempts = (int) $lockoutStmt->fetch()['attempt_count'];

if ($attempts >= 5) {
    $_SESSION['login_error'] = 'Too many failed login attempts. Please try again in a few minutes.';
    redirect_to('/meditrack/modules/auth/login.php');
}

// --- Look up the user (prepared statement - prevents SQL injection) ---
$stmt = $pdo->prepare(
    'SELECT user_id, full_name, username, password_hash, role, is_active
     FROM users
     WHERE username = :username
     LIMIT 1'
);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

$loginOk = $user && $user['is_active'] == 1 && password_verify($password, $user['password_hash']);

// --- Record this attempt (success or failure) for lockout tracking ---
$logStmt = $pdo->prepare(
    'INSERT INTO login_attempts (username, ip_address, was_successful)
     VALUES (:username, :ip_address, :was_successful)'
);
$logStmt->execute([
    'username'       => $username,
    'ip_address'     => $ipAddress,
    'was_successful' => $loginOk ? 1 : 0,
]);

if (!$loginOk) {
    $_SESSION['login_error']  = 'Invalid username or password.';
    $_SESSION['old_username'] = $username;
    redirect_to('/meditrack/modules/auth/login.php');
}

// --- Success: log the user in ---
login_user($user);

// Update last_login_at
$updateStmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = :id');
$updateStmt->execute(['id' => $user['user_id']]);

redirect_to('/meditrack/modules/dashboard/index.php');
