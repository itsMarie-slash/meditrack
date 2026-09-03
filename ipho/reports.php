<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../models/Medicine.php';
require_once __DIR__ . '/../models/Forecast.php';

$user = requireRole(['ipho']);

$export = sanitizeInput($_GET['export'] ?? '');

if (in_array($export, ['distribution', 'inventory', 'forecast'], true)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="meditrack_' . $export . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($export === 'distribution') {
        fputcsv($out, ['Beneficiary', 'Purok/Zone', 'Medicine', 'Quantity', 'Scheduled Date', 'Dispensed Date', 'Status']);
        foreach (getDistributions($conn, [], 100000, 0) as $r) {
            fputcsv($out, [$r['senior_name'], $r['purok_zone'], $r['medicine_name'], $r['quantity'], $r['scheduled_date'], $r['dispensed_date'], $r['status']]);
        }
    } elseif ($export === 'inventory') {
        fputcsv($out, ['Medicine', 'Category', 'Unit', 'Current Stock', 'Reorder Threshold', 'Expiry Date', 'Date Received']);
        foreach (getAllMedicines($conn, false) as $r) {
            fputcsv($out, [$r['name'], $r['category'], $r['unit'], $r['current_stock'], $r['reorder_threshold'], $r['expiry_date'], $r['date_received']]);
        }
    } else {
        fputcsv($out, ['Medicine', 'Forecast Period', 'Predicted Quantity', 'Method', 'Generated At']);
        foreach (getLatestForecastPerMedicine($conn) as $r) {
            fputcsv($out, [$r['medicine_name'], $r['forecast_period'], $r['predicted_quantity'], $r['method_used'], $r['generated_at']]);
        }
    }

    fclose($out);
    logActivity($conn, (int) $user['id'], 'export_report', 'Exported ' . $export . ' CSV report.');
    exit;
}

$pageTitle = 'Reports';
$activeMenu = 'reports';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header"><div><h1>Reports &amp; Export</h1><div class="page-subtitle">Download CSV extracts for allocation planning</div></div></div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold">Distribution Report</h2>
            <p class="small text-muted">Full distribution history across all beneficiaries.</p>
            <a href="?export=distribution" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Download CSV</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold">Inventory Report</h2>
            <p class="small text-muted">Current medicine catalog and stock levels.</p>
            <a href="?export=inventory" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Download CSV</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold">Forecast Report</h2>
            <p class="small text-muted">Latest predicted demand per medicine, for allocation planning.</p>
            <a href="?export=forecast" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Download CSV</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
