<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/Medicine.php';

$user = requireRole(['bhw', 'midwife']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $id !== null;
$record = $editing ? getMedicineById($conn, $id) : null;
if ($editing && !$record) {
    setFlash('error', 'Medicine not found.');
    redirectTo('/bhw/inventory.php');
}

$errors = [];
$form = $record ?: [
    'name' => '', 'category' => '', 'unit' => 'tablet', 'reorder_threshold' => 0,
    'expiry_date' => '', 'date_received' => date('Y-m-d'), 'current_stock' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $form = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'category' => sanitizeInput($_POST['category'] ?? ''),
        'unit' => sanitizeInput($_POST['unit'] ?? ''),
        'reorder_threshold' => sanitizeInput($_POST['reorder_threshold'] ?? '0'),
        'expiry_date' => sanitizeInput($_POST['expiry_date'] ?? ''),
        'date_received' => sanitizeInput($_POST['date_received'] ?? ''),
        'current_stock' => sanitizeInput($_POST['current_stock'] ?? '0'),
    ];

    if ($form['name'] === '') $errors[] = 'Medicine name is required.';
    if ($form['unit'] === '') $errors[] = 'Unit is required.';
    if (!is_numeric($form['reorder_threshold']) || (int) $form['reorder_threshold'] < 0) {
        $errors[] = 'Reorder threshold must be a non-negative number.';
    }
    if (!$editing) {
        if (!is_numeric($form['current_stock']) || (int) $form['current_stock'] < 0) {
            $errors[] = 'Initial stock must be a non-negative number.';
        }
    }
    if ($form['expiry_date'] !== '' && !strtotime($form['expiry_date'])) {
        $errors[] = 'Expiry date is not a valid date.';
    }
    if ($form['date_received'] !== '' && !strtotime($form['date_received'])) {
        $errors[] = 'Date received is not a valid date.';
    }
    if ($form['expiry_date'] !== '' && $form['date_received'] !== '' && strtotime($form['expiry_date']) < strtotime($form['date_received'])) {
        $errors[] = 'Expiry date cannot be before the date received.';
    }

    if (!$errors) {
        $form['reorder_threshold'] = (int) $form['reorder_threshold'];
        $form['expiry_date'] = $form['expiry_date'] ?: null;
        $form['date_received'] = $form['date_received'] ?: null;

        if ($editing) {
            updateMedicine($conn, $id, $form);
            logActivity($conn, (int) $user['id'], 'update_medicine', 'Updated medicine: ' . $form['name']);
            setFlash('success', 'Medicine updated successfully.');
        } else {
            // Stock is created at 0, then logged as a "received" inventory
            // transaction so the audit trail covers the very first entry too.
            $form['current_stock'] = 0;
            $newId = addMedicine($conn, $form);
            $initialQty = (int) ($_POST['current_stock'] ?? 0);
            if ($initialQty > 0) {
                adjustMedicineStock($conn, $newId, 'received', $initialQty, 'Initial stock on registration', (int) $user['id']);
            }
            logActivity($conn, (int) $user['id'], 'create_medicine', 'Added medicine: ' . $form['name']);
            setFlash('success', 'Medicine added successfully.');
        }
        redirectTo('/bhw/inventory.php');
    }
}

$pageTitle = $editing ? 'Edit Medicine' : 'Add Medicine';
$activeMenu = 'inventory';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div><h1><?= $editing ? 'Edit Medicine' : 'Add Medicine' ?></h1></div>
    <a href="/bhw/inventory.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Inventory</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 small"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row">
<div class="col-lg-7">
<form method="post" class="mt-card p-3" novalidate>
    <?php csrfField(); ?>
    <div class="row g-2">
        <div class="col-md-8">
            <label class="form-label small">Medicine Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm" required value="<?= h($form['name']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Unit <span class="text-danger">*</span></label>
            <select name="unit" class="form-select form-select-sm">
                <?php foreach (['tablet', 'box', 'bottle', 'vial', 'sachet'] as $u): ?>
                    <option value="<?= $u ?>" <?= $form['unit'] === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small">Category</label>
            <input type="text" name="category" class="form-control form-control-sm" placeholder="e.g. Antihypertensive" value="<?= h($form['category']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Reorder Threshold <span class="text-danger">*</span></label>
            <input type="number" min="0" name="reorder_threshold" class="form-control form-control-sm" required value="<?= h((string) $form['reorder_threshold']) ?>">
        </div>
        <?php if (!$editing): ?>
        <div class="col-md-6">
            <label class="form-label small">Initial Stock Quantity</label>
            <input type="number" min="0" name="current_stock" class="form-control form-control-sm" value="<?= h((string) $form['current_stock']) ?>">
            <div class="form-text">Logged automatically as a "received" inventory transaction.</div>
        </div>
        <?php else: ?>
        <div class="col-md-6">
            <label class="form-label small">Current Stock on Hand</label>
            <input type="text" class="form-control form-control-sm" disabled value="<?= (int) $form['current_stock'] ?> (use the Stock Log to adjust)">
        </div>
        <?php endif; ?>
        <div class="col-md-6">
            <label class="form-label small">Date Received</label>
            <input type="date" name="date_received" class="form-control form-control-sm" value="<?= h($form['date_received']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small">Expiry Date</label>
            <input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= h($form['expiry_date']) ?>">
        </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3"><?= $editing ? 'Save Changes' : 'Add Medicine' ?></button>
</form>
</div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
