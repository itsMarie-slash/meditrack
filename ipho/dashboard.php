<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';
require_once __DIR__ . '/../models/Medicine.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../models/Forecast.php';

$user = requireRole(['ipho']);

$activeBeneficiaries = countActiveSeniorCitizens($conn);
$lowStock = getLowStockMedicines($conn);
$nearExpiry = getNearExpiryMedicines($conn, 60);
$scheduledCount = countDistributionsByStatus($conn, 'Scheduled');
$dispensedCount = countDistributionsByStatus($conn, 'Dispensed');
$forecasts = getLatestForecastPerMedicine($conn);

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1>IPHO Overview</h1>
        <div class="page-subtitle">Read-only visibility into Barangay New Bulatukan Health Center</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-tile"><div class="stat-value"><?= $activeBeneficiaries ?></div><div class="stat-label">Active Beneficiaries</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile <?= count($lowStock) ? 'stat-danger' : '' ?>"><div class="stat-value"><?= count($lowStock) ?></div><div class="stat-label">Low Stock Medicines</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile <?= count($nearExpiry) ? 'stat-warn' : '' ?>"><div class="stat-value"><?= count($nearExpiry) ?></div><div class="stat-label">Near-Expiry (60 days)</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-tile"><div class="stat-value"><?= $scheduledCount ?></div><div class="stat-label">Scheduled Distributions</div></div>
    </div>
</div>

<div class="mt-card p-3">
    <h2 class="h6 fw-bold mb-3">Latest Forecasts (for planning next allocation)</h2>
    <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Medicine</th><th>Current Stock</th><th>Predicted Need</th><th>Generated</th></tr></thead>
        <tbody>
        <?php if (!$forecasts): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No forecasts generated yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($forecasts as $f): ?>
            <tr>
                <td><?= h($f['medicine_name']) ?></td>
                <td><?= (int) $f['current_stock'] ?> <?= h($f['unit']) ?></td>
                <td><strong><?= number_format((float) $f['predicted_quantity'], 2) ?></strong> <?= h($f['unit']) ?></td>
                <td class="small text-muted"><?= formatDateTime($f['generated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <a href="/ipho/forecast.php" class="small">View full forecasting report &rarr;</a>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
