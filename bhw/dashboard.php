<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';
require_once __DIR__ . '/../models/Medicine.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../models/ActivityLog.php';

$user = requireRole(['bhw', 'midwife']);

$activeBeneficiaries = countActiveSeniorCitizens($conn);
$lowStock = getLowStockMedicines($conn);
$nearExpiry = getNearExpiryMedicines($conn, 60);
$scheduledCount = countDistributionsByStatus($conn, 'Scheduled');
$dispensedCount = countDistributionsByStatus($conn, 'Dispensed');
$recentLogs = getRecentActivityLogs($conn, 10);

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <div class="page-subtitle">Welcome back, <?= h($user['name']) ?>. Here's today's overview.</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-value"><?= $activeBeneficiaries ?></div>
            <div class="stat-label">Active Beneficiaries</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile <?= count($lowStock) ? 'stat-danger' : '' ?>">
            <div class="stat-value"><?= count($lowStock) ?></div>
            <div class="stat-label">Low Stock Medicines</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile <?= count($nearExpiry) ? 'stat-warn' : '' ?>">
            <div class="stat-value"><?= count($nearExpiry) ?></div>
            <div class="stat-label">Near-Expiry (60 days)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile">
            <div class="stat-value"><?= $scheduledCount ?></div>
            <div class="stat-label">Scheduled Distributions</div>
        </div>
    </div>
</div>

<?php if (count($lowStock) || count($nearExpiry)): ?>
<div class="alert alert-warning mt-card p-3 mb-4">
    <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle"></i> Inventory attention needed</div>
    <?php if (count($lowStock)): ?>
        <div class="small mb-1"><strong>Low stock:</strong>
            <?= h(implode(', ', array_map(fn($m) => $m['name'] . ' (' . $m['current_stock'] . ' ' . $m['unit'] . ' left)', $lowStock))) ?>
        </div>
    <?php endif; ?>
    <?php if (count($nearExpiry)): ?>
        <div class="small mb-0"><strong>Expiring soon:</strong>
            <?= h(implode(', ', array_map(fn($m) => $m['name'] . ' (' . formatDate($m['expiry_date']) . ')', $nearExpiry))) ?>
        </div>
    <?php endif; ?>
    <a href="/bhw/inventory.php" class="small">View inventory &rarr;</a>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-8">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold mb-3">Quick Actions</h2>
            <div class="d-flex flex-wrap gap-2">
                <a href="/bhw/beneficiary_form.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Register Beneficiary</a>
                <a href="/bhw/distribution_form.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-plus"></i> Schedule Distribution</a>
                <a href="/bhw/inventory_form.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-seam"></i> Add Medicine Stock</a>
                <a href="/bhw/sms_broadcast.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-megaphone"></i> Send Broadcast</a>
            </div>
            <hr>
            <h2 class="h6 fw-bold mb-2">Dispensed vs Scheduled</h2>
            <p class="small text-muted mb-0"><?= $dispensedCount ?> distributions dispensed to date, <?= $scheduledCount ?> currently scheduled. See the <a href="/bhw/forecast.php">forecast page</a> for projected demand.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold mb-3">Recent Activity</h2>
            <?php if (!$recentLogs): ?>
                <p class="text-muted small mb-0">No activity yet.</p>
            <?php else: ?>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($recentLogs as $log): ?>
                        <li class="mb-2 pb-2 border-bottom">
                            <div><strong><?= h($log['user_name'] ?? 'System') ?></strong> &mdash; <?= h($log['action']) ?></div>
                            <div class="text-muted"><?= h($log['description']) ?></div>
                            <div class="text-muted"><?= formatDateTime($log['created_at']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
