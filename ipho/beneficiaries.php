<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';

$user = requireRole(['ipho']);

$search = sanitizeInput($_GET['search'] ?? '');
$purok = sanitizeInput($_GET['purok_zone'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$filters = ['search' => $search, 'purok_zone' => $purok];
$total = countSeniorCitizens($conn, $filters);
$pagination = paginate($page, $perPage, $total);
$beneficiaries = getSeniorCitizens($conn, $filters, 'full_name', 'ASC', $perPage, $pagination['offset']);
$purokOptions = getDistinctPurokZones($conn);

$pageTitle = 'Beneficiaries';
$activeMenu = 'beneficiaries';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div><h1>Senior Citizen Beneficiaries</h1><div class="page-subtitle"><?= $total ?> active record(s) &middot; read-only</div></div>
</div>

<div class="mt-card p-3 mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label small">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, SC ID, contact number" value="<?= h($search) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Purok/Zone</label>
            <select name="purok_zone" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($purokOptions as $pz): ?>
                    <option value="<?= h($pz) ?>" <?= $purok === $pz ? 'selected' : '' ?>><?= h($pz) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Filter</button></div>
    </form>
</div>

<div class="mt-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Age/Sex</th><th>Purok/Zone</th><th>Contact</th><th>Maintenance Medicine</th></tr></thead>
    <tbody>
    <?php if (!$beneficiaries): ?><tr><td colspan="5" class="text-center text-muted py-4">No beneficiaries found.</td></tr><?php endif; ?>
    <?php foreach ($beneficiaries as $b): ?>
        <tr>
            <td><?= h($b['full_name']) ?><div class="text-muted small"><?= h($b['senior_id_number']) ?></div></td>
            <td><?= calculateAge($b['birthdate']) ?> / <?= h($b['sex']) ?></td>
            <td><?= h($b['purok_zone']) ?></td>
            <td><?= h($b['contact_number'] ?: '-') ?></td>
            <td><?= h($b['maintenance_medicine'] ?: '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
        <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(['search' => $search, 'purok_zone' => $purok, 'page' => $p]) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
