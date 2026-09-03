<?php
/**
 * Top of every protected page's HTML. Expects the including page to have
 * already called requireRole() and to have set:
 *   $pageTitle   (string) - shown in <title> and the page header
 *   $activeMenu  (string) - key used by sidebar.php to highlight the current nav item
 * currentUser() must be available (auth.php already included).
 */
$user = currentUser();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'MediTrack') ?> - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/public/assets/css/custom.css" rel="stylesheet">
</head>
<body>
<div class="app-topbar">
    <div class="brand">
        <span class="seal">NB</span>
        <span>MediTrack <span class="fw-normal text-muted d-none d-md-inline">&middot; Brgy. New Bulatukan Health Center</span></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="text-end d-none d-sm-block">
            <div class="fw-semibold small"><?= h($user['name']) ?></div>
            <span class="badge role-badge bg-secondary-subtle text-secondary-emphasis"><?= h(ucfirst($user['role'])) ?></span>
        </div>
        <a href="/logout.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <?php if ($flash): ?>
            <div class="alert alert-<?= h($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= h($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
