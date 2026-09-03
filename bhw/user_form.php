<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/User.php';

$user = requireRole(['bhw']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $id !== null;
$record = $editing ? getUserById($conn, $id) : null;
if ($editing && !$record) {
    setFlash('error', 'User account not found.');
    redirectTo('/bhw/users.php');
}
$isSelf = $editing && (int) $id === (int) $user['id'];

$errors = [];
$form = $record ?: ['name' => '', 'email' => '', 'role' => 'bhw'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $form = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'role' => sanitizeInput($_POST['role'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($form['name'] === '') $errors[] = 'Name is required.';
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    } elseif (emailExists($conn, $form['email'], $id)) {
        $errors[] = 'This email address is already registered to another account.';
    }
    if (!in_array($form['role'], ['bhw', 'midwife', 'ipho'], true)) {
        $errors[] = 'Please select a valid role.';
    }
    if ($isSelf && $form['role'] !== 'bhw') {
        $errors[] = 'You cannot remove your own BHW (admin) access.';
    }

    if (!$editing) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Password and confirmation do not match.';
        }
    } elseif ($password !== '') {
        // Editing with a new password provided - validate it; leaving both blank keeps the old password.
        if (strlen($password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }
    }

    if (!$errors) {
        if ($editing) {
            updateUser($conn, $id, $form['name'], $form['email'], $form['role']);
            if ($password !== '') {
                updateUserPassword($conn, $id, $password);
            }
            logActivity($conn, (int) $user['id'], 'update_user', 'Updated user account: ' . $form['name']);
            setFlash('success', 'User account updated successfully.');
        } else {
            createUser($conn, $form['name'], $form['email'], $password, $form['role']);
            logActivity($conn, (int) $user['id'], 'create_user', 'Created user account: ' . $form['name'] . ' (' . $form['role'] . ')');
            setFlash('success', 'User account created successfully.');
        }
        redirectTo('/bhw/users.php');
    }
}

$pageTitle = $editing ? 'Edit User Account' : 'Add User Account';
$activeMenu = 'users';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div><h1><?= $editing ? 'Edit User Account' : 'Add User Account' ?></h1></div>
    <a href="/bhw/users.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 small"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="col-lg-6">
<form method="post" class="mt-card p-3" novalidate>
    <?php csrfField(); ?>
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label small">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm" required value="<?= h($form['name']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label small">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control form-control-sm" required value="<?= h($form['email']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label small">Role <span class="text-danger">*</span></label>
            <select name="role" class="form-select form-select-sm" <?= $isSelf ? 'disabled' : '' ?>>
                <option value="bhw" <?= $form['role'] === 'bhw' ? 'selected' : '' ?>>BHW (full access / admin)</option>
                <option value="midwife" <?= $form['role'] === 'midwife' ? 'selected' : '' ?>>Midwife</option>
                <option value="ipho" <?= $form['role'] === 'ipho' ? 'selected' : '' ?>>IPHO Staff (read-only + reports)</option>
            </select>
            <?php if ($isSelf): ?>
                <input type="hidden" name="role" value="bhw">
                <div class="form-text">You cannot change your own role.</div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label small"><?= $editing ? 'New Password' : 'Password' ?> <?= $editing ? '' : '<span class="text-danger">*</span>' ?></label>
            <input type="password" name="password" class="form-control form-control-sm" <?= $editing ? '' : 'required' ?> minlength="8" placeholder="<?= $editing ? 'Leave blank to keep current password' : 'At least 8 characters' ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control form-control-sm" minlength="8">
        </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3"><?= $editing ? 'Save Changes' : 'Create Account' ?></button>
</form>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
