<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../models/Medicine.php';

$user = requireRole(['bhw', 'midwife']);

$export = sanitizeInput($_GET['export'] ?? '');

if ($export === 'distribution' || $export === 'inventory') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="meditrack_' . $export . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($export === 'distribution') {
        fputcsv($out, ['Beneficiary', 'Purok/Zone', 'Medicine', 'Quantity', 'Scheduled Date', 'Dispensed Date', 'Status']);
        $rows = getDistributions($conn, [], 100000, 0);
        foreach ($rows as $r) {
            fputcsv($out, [$r['senior_name'], $r['purok_zone'], $r['medicine_name'], $r['quantity'], $r['scheduled_date'], $r['dispensed_date'], $r['status']]);
        }
    } else {
        fputcsv($out, ['Medicine', 'Category', 'Unit', 'Current Stock', 'Reorder Threshold', 'Expiry Date', 'Date Received']);
        $rows = getAllMedicines($conn, false);
        foreach ($rows as $r) {
            fputcsv($out, [$r['name'], $r['category'], $r['unit'], $r['current_stock'], $r['reorder_threshold'], $r['expiry_date'], $r['date_received']]);
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
<div class="page-header"><div><h1>Reports &amp; Export</h1><div class="page-subtitle">Download CSV extracts of current data</div></div></div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold">Distribution Report</h2>
            <p class="small text-muted">Full distribution history: beneficiary, medicine, quantity, scheduled/dispensed dates, and status.</p>
            <a href="?export=distribution" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Download CSV</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold">Inventory Report</h2>
            <p class="small text-muted">Current medicine catalog with stock levels, reorder thresholds, and expiry dates.</p>
            <a href="?export=inventory" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> Download CSV</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
