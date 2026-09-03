<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    logActivity($conn, (int) $_SESSION['user_id'], 'logout', 'User logged out.');
}

logoutUser();
redirectTo('/index.php');
