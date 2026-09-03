<?php
/**
 * User account management - BHW-only (BHW is this system's admin role).
 * There is no public self-registration (per spec 4.1); accounts for
 * midwife and IPHO staff are created here.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/User.php';

$user = requireRole(['bhw']);

$users = getAllUsers($conn);

$roleBadge = [
    'bhw' => 'text-bg-success',
    'midwife' => 'text-bg-info',
    'ipho' => 'text-bg-secondary',
];

$pageTitle = 'User Accounts';
$activeMenu = 'users';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1>User Accounts</h1>
        <div class="page-subtitle"><?= count($users) ?> account(s) &middot; BHW, Midwife, and IPHO staff</div>
    </div>
    <a href="/bhw/user_form.php" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Add User Account</a>
</div>

<div class="mt-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= h($u['name']) ?><?php if ((int) $u['id'] === (int) $user['id']): ?> <span class="badge text-bg-light border">You</span><?php endif; ?></td>
            <td><?= h($u['email']) ?></td>
            <td><span class="badge <?= $roleBadge[$u['role']] ?? 'text-bg-light' ?>"><?= h(ucfirst($u['role'])) ?></span></td>
            <td><span class="badge <?= $u['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= h(ucfirst($u['status'])) ?></span></td>
            <td class="small text-muted"><?= formatDate($u['created_at']) ?></td>
            <td class="text-end">
                <a href="/bhw/user_form.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                    <form method="post" action="/bhw/user_status.php" class="d-inline" onsubmit="return confirm('<?= $u['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?> this account?');">
                        <?php csrfField(); ?>
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                        <button type="submit" class="btn btn-sm btn-outline-<?= $u['status'] === 'active' ? 'danger' : 'success' ?>">
                            <?= $u['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
