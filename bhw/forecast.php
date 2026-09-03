<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/Forecast.php';
require_once __DIR__ . '/../models/Medicine.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../services/ForecastService.php';

// Forecasts are read-only for BHW per spec, but BHW/midwife may still
// trigger a manual recompute (the automatic hook already runs on every
// dispense - this is just a convenience for medicines with no recent
// activity, or to refresh the page after a bulk import).
$user = requireRole(['bhw', 'midwife']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'recompute_all') {
    requireValidCsrf();
    $service = new ForecastService($conn);
    $service->calculateForecastForAllMedicines();
    logActivity($conn, (int) $user['id'], 'recompute_forecast', 'Manually recomputed forecasts for all medicines.');
    setFlash('success', 'Forecasts recomputed for all medicines.');
    redirectTo('/bhw/forecast.php');
}

$latestForecasts = getLatestForecastPerMedicine($conn);
$selectedMedicineId = (int) ($_GET['medicine_id'] ?? ($latestForecasts[0]['medicine_id'] ?? 0));
$medicines = getAllMedicines($conn);

$chartHistory = [];
$chartForecastHistory = [];
if ($selectedMedicineId) {
    $chartHistory = getDispensedHistoryByMedicine($conn, $selectedMedicineId, 6);
    $chartForecastHistory = getForecastHistoryByMedicine($conn, $selectedMedicineId, 6);
}

$pageTitle = 'Demand Forecast';
$activeMenu = 'forecast';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1>Demand Forecasting</h1>
        <div class="page-subtitle">Predicted quantity needed per medicine for the next distribution cycle</div>
    </div>
    <form method="post">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="recompute_all">
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Recompute All</button>
    </form>
</div>

<div class="alert alert-light border small mb-3">
    <i class="bi bi-info-circle"></i> Forecasts recompute automatically every time a distribution is marked <strong>Dispensed</strong>.
    Accuracy depends entirely on the distribution history already recorded in this system.
</div>

<div class="mt-card p-0 mb-4">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Medicine</th><th>Current Stock</th><th>Predicted Need (Next Cycle)</th><th>Method</th><th>Last Computed</th></tr></thead>
    <tbody>
    <?php if (!$latestForecasts): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No forecasts yet - dispense at least one distribution to generate one.</td></tr>
    <?php endif; ?>
    <?php foreach ($latestForecasts as $f): ?>
        <tr class="<?= (int) $f['medicine_id'] === $selectedMedicineId ? 'table-active' : '' ?>">
            <td><a href="?medicine_id=<?= (int) $f['medicine_id'] ?>"><?= h($f['medicine_name']) ?></a></td>
            <td><?= (int) $f['current_stock'] ?> <?= h($f['unit']) ?></td>
            <td><strong><?= number_format((float) $f['predicted_quantity'], 2) ?></strong> <?= h($f['unit']) ?>
                <?php if ((float) $f['predicted_quantity'] > (int) $f['current_stock']): ?>
                    <span class="badge text-bg-warning ms-1">Stock may fall short</span>
                <?php endif; ?>
            </td>
            <td class="small text-muted"><?= h($f['method_used']) ?></td>
            <td class="small"><?= formatDateTime($f['generated_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php if ($selectedMedicineId): ?>
<div class="mt-card p-3">
    <h2 class="h6 fw-bold mb-3">Actual vs Forecasted Demand: <?= h($medicines[array_search($selectedMedicineId, array_column($medicines, 'id'))]['name'] ?? '') ?></h2>
    <canvas id="forecastChart" height="90"></canvas>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
<?php if ($selectedMedicineId): ?>
const actualLabels = <?= json_encode(array_map(fn($r) => date('M j, Y', strtotime($r['period'])), $chartHistory)) ?>;
const actualData = <?= json_encode(array_map(fn($r) => (float) $r['total_quantity'], $chartHistory)) ?>;
const forecastLabels = <?= json_encode(array_map(fn($r) => date('M j', strtotime($r['generated_at'])), $chartForecastHistory)) ?>;
const forecastData = <?= json_encode(array_map(fn($r) => (float) $r['predicted_quantity'], $chartForecastHistory)) ?>;

// Merge the two label sets so both lines share one x-axis, aligned to the right (most recent).
const labels = actualLabels.length >= forecastLabels.length ? actualLabels : forecastLabels;

new Chart(document.getElementById('forecastChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Actual Dispensed Quantity',
                data: actualData,
                borderColor: '#1a6b52',
                backgroundColor: 'rgba(26,107,82,0.1)',
                tension: 0.25,
            },
            {
                label: 'Forecasted Need',
                data: forecastData,
                borderColor: '#c99a2e',
                backgroundColor: 'rgba(201,154,46,0.1)',
                borderDash: [6, 4],
                tension: 0.25,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true } }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
