<?php
/** POST-only handler: activate/deactivate a user account. BHW-only. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/User.php';

$user = requireRole(['bhw']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/bhw/users.php');
}
requireValidCsrf();

$id = (int) ($_POST['id'] ?? 0);
$newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';

if ($id === (int) $user['id']) {
    setFlash('error', 'You cannot deactivate your own account.');
    redirectTo('/bhw/users.php');
}

$record = getUserById($conn, $id);
if (!$record) {
    setFlash('error', 'User account not found.');
} else {
    setUserStatus($conn, $id, $newStatus);
    logActivity($conn, (int) $user['id'], 'update_user_status', ucfirst($newStatus) . 'd user account: ' . $record['name']);
    setFlash('success', 'Account marked as ' . $newStatus . '.');
}

redirectTo('/bhw/users.php');
