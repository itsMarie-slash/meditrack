<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';
require_once __DIR__ . '/../models/Medicine.php';

$user = requireRole(['bhw', 'midwife']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $id !== null;
$record = $editing ? getDistributionById($conn, $id) : null;
if ($editing && (!$record || $record['status'] !== 'Scheduled')) {
    setFlash('error', 'Only records with "Scheduled" status can be edited.');
    redirectTo('/bhw/distribution.php');
}

$preselectedSenior = (int) ($_GET['senior_citizen_id'] ?? 0);

$errors = [];
$form = $record ?: [
    'senior_citizen_id' => $preselectedSenior ?: '',
    'medicine_id' => '',
    'quantity' => '',
    'scheduled_date' => date('Y-m-d'),
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $form = [
        'senior_citizen_id' => sanitizeInput($_POST['senior_citizen_id'] ?? ''),
        'medicine_id' => sanitizeInput($_POST['medicine_id'] ?? ''),
        'quantity' => sanitizeInput($_POST['quantity'] ?? ''),
        'scheduled_date' => sanitizeInput($_POST['scheduled_date'] ?? ''),
        'notes' => sanitizeInput($_POST['notes'] ?? ''),
    ];

    if (!ctype_digit($form['senior_citizen_id']) || !getSeniorCitizenById($conn, (int) $form['senior_citizen_id'])) {
        $errors[] = 'Please select a valid beneficiary.';
    }
    if (!ctype_digit($form['medicine_id']) || !getMedicineById($conn, (int) $form['medicine_id'])) {
        $errors[] = 'Please select a valid medicine.';
    }
    if (!is_numeric($form['quantity']) || (int) $form['quantity'] <= 0) {
        $errors[] = 'Quantity must be a positive number.';
    }
    if ($form['scheduled_date'] === '' || !strtotime($form['scheduled_date'])) {
        $errors[] = 'A valid scheduled date is required.';
    }

    if (!$errors) {
        $data = [
            'senior_citizen_id' => (int) $form['senior_citizen_id'],
            'medicine_id' => (int) $form['medicine_id'],
            'quantity' => (int) $form['quantity'],
            'scheduled_date' => $form['scheduled_date'],
            'notes' => $form['notes'],
            'recorded_by' => (int) $user['id'],
        ];

        if ($editing) {
            updateDistribution($conn, $id, $data);
            logActivity($conn, (int) $user['id'], 'update_distribution', 'Updated distribution #' . $id);
            setFlash('success', 'Distribution updated successfully.');
        } else {
            $newId = addDistribution($conn, $data);
            logActivity($conn, (int) $user['id'], 'schedule_distribution', 'Scheduled distribution #' . $newId);
            setFlash('success', 'Distribution scheduled successfully. An SMS reminder will go out automatically before the date.');
        }
        redirectTo('/bhw/distribution.php');
    }
}

$seniors = getSeniorCitizens($conn, ['status' => 'active'], 'full_name', 'ASC', 1000, 0);
$medicines = getAllMedicines($conn);

$pageTitle = $editing ? 'Edit Distribution' : 'Schedule Distribution';
$activeMenu = 'distribution';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div><h1><?= $editing ? 'Edit Distribution' : 'Schedule Distribution' ?></h1></div>
    <a href="/bhw/distribution.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 small"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="col-lg-7">
<form method="post" class="mt-card p-3" novalidate>
    <?php csrfField(); ?>
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label small">Beneficiary <span class="text-danger">*</span></label>
            <select name="senior_citizen_id" class="form-select form-select-sm" required>
                <option value="">-- Select beneficiary --</option>
                <?php foreach ($seniors as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (string) $form['senior_citizen_id'] === (string) $s['id'] ? 'selected' : '' ?>>
                        <?= h($s['full_name']) ?> (<?= h($s['senior_id_number']) ?> &middot; <?= h($s['purok_zone']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small">Medicine <span class="text-danger">*</span></label>
            <select name="medicine_id" class="form-select form-select-sm" required>
                <option value="">-- Select medicine --</option>
                <?php foreach ($medicines as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (string) $form['medicine_id'] === (string) $m['id'] ? 'selected' : '' ?>>
                        <?= h($m['name']) ?> (<?= (int) $m['current_stock'] ?> <?= h($m['unit']) ?> in stock)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Quantity <span class="text-danger">*</span></label>
            <input type="number" min="1" name="quantity" class="form-control form-control-sm" required value="<?= h((string) $form['quantity']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Scheduled Date <span class="text-danger">*</span></label>
            <input type="date" name="scheduled_date" class="form-control form-control-sm" required value="<?= h($form['scheduled_date']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label small">Notes</label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"><?= h($form['notes']) ?></textarea>
        </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3"><?= $editing ? 'Save Changes' : 'Schedule Distribution' ?></button>
</form>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
