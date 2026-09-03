<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';
require_once __DIR__ . '/../models/Distribution.php';

$user = requireRole(['bhw', 'midwife']);

$id = (int) ($_GET['id'] ?? 0);
$record = getSeniorCitizenById($conn, $id);
if (!$record) {
    setFlash('error', 'Beneficiary not found.');
    redirectTo('/bhw/beneficiaries.php');
}

$history = getDistributionsBySeniorCitizen($conn, $id);

$statusBadge = [
    'Scheduled' => 'badge-status-scheduled',
    'Dispensed' => 'badge-status-dispensed',
    'Not Dispensed' => 'badge-status-not-dispensed',
    'Missed' => 'badge-status-missed',
];

$pageTitle = 'Beneficiary Profile';
$activeMenu = 'beneficiaries';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= h($record['full_name']) ?></h1>
        <div class="page-subtitle">SC ID: <?= h($record['senior_id_number']) ?> &middot; <?= h($record['purok_zone']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="/bhw/beneficiary_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <form method="post" action="/bhw/beneficiary_status.php" onsubmit="return confirm('<?= $record['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?> this beneficiary?');">
            <?php csrfField(); ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="new_status" value="<?= $record['status'] === 'active' ? 'inactive' : 'active' ?>">
            <button type="submit" class="btn btn-sm btn-outline-<?= $record['status'] === 'active' ? 'danger' : 'success' ?>">
                <?= $record['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?>
            </button>
        </form>
        <a href="/bhw/beneficiaries.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="mt-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Profile</h2>
            <table class="table table-sm mb-0">
                <tr><th class="text-muted fw-normal" style="width:45%">Age / Sex</th><td><?= calculateAge($record['birthdate']) ?> yrs old / <?= h($record['sex']) ?></td></tr>
                <tr><th class="text-muted fw-normal">Birthdate</th><td><?= formatDate($record['birthdate']) ?></td></tr>
                <tr><th class="text-muted fw-normal">Address</th><td><?= h($record['address']) ?></td></tr>
                <tr><th class="text-muted fw-normal">Contact Number</th><td><?= h($record['contact_number'] ?: 'Not on file') ?></td></tr>
                <tr><th class="text-muted fw-normal">Status</th><td><span class="badge <?= $record['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= h(ucfirst($record['status'])) ?></span></td></tr>
            </table>
        </div>
        <div class="mt-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Health Information</h2>
            <table class="table table-sm mb-0">
                <tr><th class="text-muted fw-normal" style="width:45%">Maintenance Medicine</th><td><?= h($record['maintenance_medicine'] ?: '-') ?></td></tr>
                <tr><th class="text-muted fw-normal">Health Conditions</th><td><?= nl2br(h($record['health_conditions'] ?: '-')) ?></td></tr>
                <tr><th class="text-muted fw-normal">Allergies</th><td><?= nl2br(h($record['allergies'] ?: '-')) ?></td></tr>
            </table>
        </div>
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold mb-3">Emergency Contact</h2>
            <table class="table table-sm mb-0">
                <tr><th class="text-muted fw-normal" style="width:45%">Name</th><td><?= h($record['emergency_contact_name'] ?: '-') ?></td></tr>
                <tr><th class="text-muted fw-normal">Number</th><td><?= h($record['emergency_contact_number'] ?: '-') ?></td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold mb-3">Distribution History</h2>
            <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Medicine</th><th>Qty</th><th>Scheduled</th><th>Dispensed</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$history): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No distribution records yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($history as $d): ?>
                    <tr>
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
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
