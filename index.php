<?php
/**
 * Login page / entry point. No public self-registration - accounts are
 * created directly in the database by whoever administers the system.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/models/User.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirectTo($role === 'ipho' ? '/ipho/dashboard.php' : '/bhw/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $user = getUserByEmail($conn, $email);
        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            loginUser($user);
            logActivity($conn, (int) $user['id'], 'login', 'User logged in.');
            redirectTo($user['role'] === 'ipho' ? '/ipho/dashboard.php' : '/bhw/dashboard.php');
        } elseif ($user && $user['status'] !== 'active') {
            $error = 'This account has been deactivated. Contact your administrator.';
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/public/assets/css/custom.css" rel="stylesheet">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="login-header">
            <div class="badge-seal">NB</div>
            <h1 class="h5 mb-1">MediTrack</h1>
            <div class="small opacity-75">Senior Citizen Healthcare &amp; Medicine Monitoring<br>Barangay New Bulatukan Health Center</div>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" action="/index.php" novalidate>
                <?php csrfField(); ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email address</label>
                    <input type="email" name="email" class="form-control" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Sign In</button>
            </form>
            <p class="text-muted small text-center mt-3 mb-0">Access is limited to registered BHW, midwife, and IPHO staff accounts.</p>
        </div>
    </div>
</div>
</body>
</html>
