<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../models/Medicine.php';

$user = requireRole(['ipho']);

$status = sanitizeInput($_GET['status'] ?? '');
$medicineId = sanitizeInput($_GET['medicine_id'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$filters = ['status' => $status, 'medicine_id' => $medicineId];
$total = countDistributions($conn, $filters);
$pagination = paginate($page, $perPage, $total);
$distributions = getDistributions($conn, $filters, $perPage, $pagination['offset']);
$medicines = getAllMedicines($conn);

$statusBadge = [
    'Scheduled' => 'badge-status-scheduled',
    'Dispensed' => 'badge-status-dispensed',
    'Not Dispensed' => 'badge-status-not-dispensed',
    'Missed' => 'badge-status-missed',
];

$pageTitle = 'Distribution History';
$activeMenu = 'distribution';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header"><div><h1>Distribution History</h1><div class="page-subtitle"><?= $total ?> record(s) &middot; read-only</div></div></div>

<div class="mt-card p-3 mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach (['Scheduled', 'Dispensed', 'Not Dispensed', 'Missed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Medicine</label>
            <select name="medicine_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($medicines as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (string) $medicineId === (string) $m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Filter</button></div>
    </form>
</div>

<div class="mt-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Beneficiary</th><th>Medicine</th><th>Qty</th><th>Scheduled</th><th>Dispensed</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$distributions): ?><tr><td colspan="6" class="text-center text-muted py-4">No records found.</td></tr><?php endif; ?>
    <?php foreach ($distributions as $d): ?>
        <tr>
            <td><?= h($d['senior_name']) ?><div class="text-muted small"><?= h($d['purok_zone']) ?></div></td>
            <td><?= h($d['medicine_name']) ?></td>
            <td><?= (int) $d['quantity'] ?> <?= h($d['unit']) ?></td>
            <td><?= formatDate($d['scheduled_date']) ?></td>
            <td><?= formatDate($d['dispensed_date']) ?></td>
            <td><span class="badge <?= $statusBadge[$d['status']] ?? 'text-bg-light' ?>"><?= h($d['status']) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
        <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(['status' => $status, 'medicine_id' => $medicineId, 'page' => $p]) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
