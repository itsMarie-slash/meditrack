<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Medicine.php';

$user = requireRole(['ipho']);

$medicines = getAllMedicines($conn);
$lowStockIds = array_column(getLowStockMedicines($conn), 'id');
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+60 days'));

$pageTitle = 'Medicine Inventory';
$activeMenu = 'inventory';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header"><div><h1>Medicine Inventory</h1><div class="page-subtitle"><?= count($medicines) ?> active medicine(s) &middot; read-only</div></div></div>

<div class="mt-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Category</th><th>Stock on Hand</th><th>Reorder Threshold</th><th>Expiry Date</th></tr></thead>
    <tbody>
    <?php foreach ($medicines as $m): ?>
        <?php
        $isLow = in_array($m['id'], $lowStockIds, true);
        $isExpired = $m['expiry_date'] && $m['expiry_date'] < $today;
        $isNearExpiry = $m['expiry_date'] && $m['expiry_date'] >= $today && $m['expiry_date'] <= $soon;
        ?>
        <tr>
            <td><?= h($m['name']) ?></td>
            <td><?= h($m['category'] ?: '-') ?></td>
            <td><?= (int) $m['current_stock'] ?> <?= h($m['unit']) ?> <?php if ($isLow): ?><span class="badge text-bg-danger ms-1">Low Stock</span><?php endif; ?></td>
            <td><?= (int) $m['reorder_threshold'] ?></td>
            <td><?= formatDate($m['expiry_date']) ?>
                <?php if ($isExpired): ?><span class="badge text-bg-danger ms-1">Expired</span>
                <?php elseif ($isNearExpiry): ?><span class="badge text-bg-warning ms-1">Expiring Soon</span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
