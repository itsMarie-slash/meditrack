<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/Medicine.php';

$user = requireRole(['bhw', 'midwife']);

$showInactive = ($_GET['status'] ?? '') === 'inactive';
$medicines = getAllMedicines($conn, !$showInactive);
$lowStockIds = array_column(getLowStockMedicines($conn), 'id');
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+60 days'));

$pageTitle = 'Medicine Inventory';
$activeMenu = 'inventory';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1>Medicine Inventory</h1>
        <div class="page-subtitle"><?= count($medicines) ?> medicine(s) <?= $showInactive ? '(inactive)' : '(active)' ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="/bhw/inventory.php?status=<?= $showInactive ? '' : 'inactive' ?>" class="btn btn-sm btn-outline-secondary"><?= $showInactive ? 'Show Active' : 'Show Inactive' ?></a>
        <a href="/bhw/inventory_form.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add Medicine</a>
    </div>
</div>

<div class="mt-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead>
    <tr>
        <th>Name</th><th>Category</th><th>Stock on Hand</th><th>Reorder Threshold</th><th>Expiry Date</th><th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$medicines): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No medicines found.</td></tr>
    <?php endif; ?>
    <?php foreach ($medicines as $m): ?>
        <?php
        $isLow = in_array($m['id'], $lowStockIds, true);
        $isExpired = $m['expiry_date'] && $m['expiry_date'] < $today;
        $isNearExpiry = $m['expiry_date'] && $m['expiry_date'] >= $today && $m['expiry_date'] <= $soon;
        ?>
        <tr>
            <td><a href="/bhw/inventory_view.php?id=<?= (int) $m['id'] ?>"><?= h($m['name']) ?></a></td>
            <td><?= h($m['category'] ?: '-') ?></td>
            <td>
                <?= (int) $m['current_stock'] ?> <?= h($m['unit']) ?>
                <?php if ($isLow): ?><span class="badge text-bg-danger ms-1">Low Stock</span><?php endif; ?>
            </td>
            <td><?= (int) $m['reorder_threshold'] ?></td>
            <td>
                <?= formatDate($m['expiry_date']) ?>
                <?php if ($isExpired): ?><span class="badge text-bg-danger ms-1">Expired</span>
                <?php elseif ($isNearExpiry): ?><span class="badge text-bg-warning ms-1">Expiring Soon</span><?php endif; ?>
            </td>
            <td class="text-end">
                <a href="/bhw/inventory_view.php?id=<?= (int) $m['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history"></i> Log</a>
                <a href="/bhw/inventory_form.php?id=<?= (int) $m['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
