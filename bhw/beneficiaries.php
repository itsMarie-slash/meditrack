<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';

$user = requireRole(['bhw', 'midwife']);

$search = sanitizeInput($_GET['search'] ?? '');
$purok = sanitizeInput($_GET['purok_zone'] ?? '');
$medicine = sanitizeInput($_GET['medicine'] ?? '');
$status = sanitizeInput($_GET['status'] ?? '');
$sortBy = sanitizeInput($_GET['sort'] ?? 'full_name');
$sortDir = sanitizeInput($_GET['dir'] ?? 'ASC');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$filters = ['search' => $search, 'purok_zone' => $purok, 'medicine' => $medicine, 'status' => $status];
$total = countSeniorCitizens($conn, $filters);
$pagination = paginate($page, $perPage, $total);
$beneficiaries = getSeniorCitizens($conn, $filters, $sortBy, $sortDir, $perPage, $pagination['offset']);
$purokOptions = getDistinctPurokZones($conn);

function sortLink(string $column, string $label, string $currentSort, string $currentDir, array $query): string
{
    $newDir = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $query['sort'] = $column;
    $query['dir'] = $newDir;
    $icon = $currentSort === $column ? ($currentDir === 'ASC' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<a class="text-decoration-none text-dark" href="?' . http_build_query($query) . '">' . h($label) . $icon . '</a>';
}
$baseQuery = ['search' => $search, 'purok_zone' => $purok, 'medicine' => $medicine, 'status' => $status];

$pageTitle = 'Beneficiaries';
$activeMenu = 'beneficiaries';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1>Senior Citizen Beneficiaries</h1>
        <div class="page-subtitle"><?= $total ?> record(s) found</div>
    </div>
    <a href="/bhw/beneficiary_form.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Register Beneficiary</a>
</div>

<div class="mt-card p-3 mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, SC ID, or contact number" value="<?= h($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Purok/Zone</label>
            <select name="purok_zone" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($purokOptions as $pz): ?>
                    <option value="<?= h($pz) ?>" <?= $purok === $pz ? 'selected' : '' ?>><?= h($pz) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Medicine</label>
            <input type="text" name="medicine" class="form-control form-control-sm" placeholder="e.g. Metformin" value="<?= h($medicine) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Active</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-fill" type="submit">Filter</button>
            <a href="/bhw/beneficiaries.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="mt-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead>
    <tr>
        <th><?= sortLink('full_name', 'Name', $sortBy, $sortDir, $baseQuery) ?></th>
        <th>Age / Sex</th>
        <th><?= sortLink('purok_zone', 'Purok/Zone', $sortBy, $sortDir, $baseQuery) ?></th>
        <th>Contact No.</th>
        <th>Maintenance Medicine</th>
        <th>Status</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$beneficiaries): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No beneficiaries found.</td></tr>
    <?php endif; ?>
    <?php foreach ($beneficiaries as $b): ?>
        <tr>
            <td><a href="/bhw/beneficiary_view.php?id=<?= (int) $b['id'] ?>"><?= h($b['full_name']) ?></a><div class="text-muted small"><?= h($b['senior_id_number']) ?></div></td>
            <td><?= calculateAge($b['birthdate']) ?> / <?= h($b['sex']) ?></td>
            <td><?= h($b['purok_zone']) ?></td>
            <td><?= h($b['contact_number'] ?: '-') ?></td>
            <td><?= h($b['maintenance_medicine'] ?: '-') ?></td>
            <td><span class="badge <?= $b['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= h(ucfirst($b['status'])) ?></span></td>
            <td class="text-end">
                <a href="/bhw/beneficiary_view.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                <a href="/bhw/beneficiary_form.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
            <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($baseQuery, ['sort' => $sortBy, 'dir' => $sortDir, 'page' => $p])) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
