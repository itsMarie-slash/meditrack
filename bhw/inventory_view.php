<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/Medicine.php';

$user = requireRole(['bhw', 'midwife']);

$id = (int) ($_GET['id'] ?? 0);
$medicine = getMedicineById($conn, $id);
if (!$medicine) {
    setFlash('error', 'Medicine not found.');
    redirectTo('/bhw/inventory.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $type = sanitizeInput($_POST['type'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');

    // "dispensed" transactions must always originate from a Distribution
    // record (see models/Distribution.php -> recordDistributionDispensed),
    // never from a free-form manual adjustment - so it's excluded here.
    $allowedTypes = ['received', 'adjusted', 'expired'];

    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Invalid transaction type.';
    }
    if (!is_numeric($quantity) || (int) $quantity <= 0) {
        $errors[] = 'Quantity must be a positive number.';
    }
    if (in_array($type, ['expired'], true) && (int) $quantity > (int) $medicine['current_stock']) {
        $errors[] = 'Cannot mark more units expired than are currently in stock.';
    }
    if ($type === 'adjusted' && $notes === '') {
        $errors[] = 'Please explain the reason for a manual stock adjustment.';
    }

    if (!$errors) {
        $qty = (int) $quantity;
        // "received" increases stock; "expired" always decreases it;
        // "adjusted" can go either way based on the sign the user picks.
        if ($type === 'received') {
            $signed = $qty;
        } elseif ($type === 'expired') {
            $signed = -1 * $qty;
        } else {
            $direction = ($_POST['direction'] ?? 'increase') === 'decrease' ? -1 : 1;
            $signed = $direction * $qty;
        }

        adjustMedicineStock($conn, $id, $type, $signed, $notes, (int) $user['id']);
        logActivity($conn, (int) $user['id'], 'inventory_adjustment', ucfirst($type) . ' ' . $qty . ' ' . $medicine['unit'] . ' of ' . $medicine['name']);
        setFlash('success', 'Stock updated successfully.');
        redirectTo('/bhw/inventory_view.php?id=' . $id);
    }
}

$transactions = getInventoryTransactions($conn, $id, 50);

$pageTitle = 'Medicine Detail';
$activeMenu = 'inventory';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= h($medicine['name']) ?></h1>
        <div class="page-subtitle"><?= h($medicine['category'] ?: 'Uncategorized') ?> &middot; <?= (int) $medicine['current_stock'] ?> <?= h($medicine['unit']) ?> on hand</div>
    </div>
    <div class="d-flex gap-2">
        <a href="/bhw/inventory_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="/bhw/inventory.php" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 small"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="mt-card p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Record Stock Movement</h2>
            <form method="post">
                <?php csrfField(); ?>
                <div class="mb-2">
                    <label class="form-label small">Type</label>
                    <select name="type" id="txn-type" class="form-select form-select-sm" onchange="document.getElementById('direction-row').style.display = this.value === 'adjusted' ? '' : 'none';">
                        <option value="received">Received (new stock from IPHO)</option>
                        <option value="expired">Expired (remove from stock)</option>
                        <option value="adjusted">Adjusted (manual correction)</option>
                    </select>
                </div>
                <div class="mb-2" id="direction-row" style="display:none;">
                    <label class="form-label small">Direction</label>
                    <select name="direction" class="form-select form-select-sm">
                        <option value="increase">Increase stock</option>
                        <option value="decrease">Decrease stock</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Quantity</label>
                    <input type="number" min="1" name="quantity" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Notes</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Delivery receipt #, physical count reconciliation"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">Save Transaction</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="mt-card p-3">
            <h2 class="h6 fw-bold mb-3">Transaction History</h2>
            <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Date</th><th>Type</th><th>Quantity</th><th>Notes</th><th>By</th></tr></thead>
                <tbody>
                <?php if (!$transactions): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No transactions recorded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= formatDateTime($t['created_at']) ?></td>
                        <td><span class="badge text-bg-light border"><?= h(ucfirst($t['type'])) ?></span></td>
                        <td><?= (int) $t['quantity'] ?> <?= h($medicine['unit']) ?></td>
                        <td><?= h($t['notes'] ?: '-') ?></td>
                        <td><?= h($t['user_name'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
