<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';

$user = requireRole(['bhw', 'midwife']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = $id !== null;
$record = $editing ? getSeniorCitizenById($conn, $id) : null;
if ($editing && !$record) {
    setFlash('error', 'Beneficiary not found.');
    redirectTo('/bhw/beneficiaries.php');
}

$errors = [];
$form = $record ?: [
    'full_name' => '', 'birthdate' => '', 'sex' => 'Female', 'purok_zone' => '', 'address' => '',
    'contact_number' => '', 'senior_id_number' => '', 'maintenance_medicine' => '', 'health_conditions' => '',
    'allergies' => '', 'emergency_contact_name' => '', 'emergency_contact_number' => '',
    'latitude' => BARANGAY_LAT, 'longitude' => BARANGAY_LNG,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $form = [
        'full_name' => sanitizeInput($_POST['full_name'] ?? ''),
        'birthdate' => sanitizeInput($_POST['birthdate'] ?? ''),
        'sex' => sanitizeInput($_POST['sex'] ?? ''),
        'purok_zone' => sanitizeInput($_POST['purok_zone'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'contact_number' => sanitizeInput($_POST['contact_number'] ?? ''),
        'senior_id_number' => sanitizeInput($_POST['senior_id_number'] ?? ''),
        'maintenance_medicine' => sanitizeInput($_POST['maintenance_medicine'] ?? ''),
        'health_conditions' => sanitizeInput($_POST['health_conditions'] ?? ''),
        'allergies' => sanitizeInput($_POST['allergies'] ?? ''),
        'emergency_contact_name' => sanitizeInput($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_number' => sanitizeInput($_POST['emergency_contact_number'] ?? ''),
        'latitude' => sanitizeInput($_POST['latitude'] ?? ''),
        'longitude' => sanitizeInput($_POST['longitude'] ?? ''),
    ];

    // --- Server-side validation (never trust client-side JS alone) ---
    if ($form['full_name'] === '') $errors[] = 'Full name is required.';
    if ($form['birthdate'] === '' || !strtotime($form['birthdate'])) {
        $errors[] = 'A valid birthdate is required.';
    } elseif (strtotime($form['birthdate']) > time()) {
        $errors[] = 'Birthdate cannot be in the future.';
    }
    if (!in_array($form['sex'], ['Male', 'Female'], true)) $errors[] = 'Sex must be Male or Female.';
    if ($form['purok_zone'] === '') $errors[] = 'Purok/Zone is required.';
    if ($form['address'] === '') $errors[] = 'Address is required.';
    if ($form['senior_id_number'] === '') $errors[] = 'Senior Citizen ID number is required.';
    if ($form['contact_number'] !== '' && !isValidPhilippineMobile($form['contact_number'])) {
        $errors[] = 'Contact number must be a valid Philippine mobile number (e.g. 09171234567).';
    }
    if ($form['emergency_contact_number'] !== '' && !isValidPhilippineMobile($form['emergency_contact_number'])) {
        $errors[] = 'Emergency contact number must be a valid Philippine mobile number (e.g. 09171234567).';
    }
    if (!is_numeric($form['latitude']) || !is_numeric($form['longitude'])) {
        $errors[] = 'Please drop a pin on the map to set the beneficiary\'s location.';
    }
    if ($form['senior_id_number'] !== '' && seniorIdNumberExists($conn, $form['senior_id_number'], $id)) {
        $errors[] = 'This Senior Citizen ID number is already registered to another beneficiary.';
    }

    if (!$errors) {
        $form['latitude'] = (float) $form['latitude'];
        $form['longitude'] = (float) $form['longitude'];
        $form['created_by'] = (int) $user['id'];

        if ($editing) {
            updateSeniorCitizen($conn, $id, $form);
            logActivity($conn, (int) $user['id'], 'update_beneficiary', 'Updated beneficiary: ' . $form['full_name']);
            setFlash('success', 'Beneficiary updated successfully.');
        } else {
            $newId = addSeniorCitizen($conn, $form);
            logActivity($conn, (int) $user['id'], 'create_beneficiary', 'Registered beneficiary: ' . $form['full_name']);
            setFlash('success', 'Beneficiary registered successfully.');
        }
        redirectTo('/bhw/beneficiaries.php');
    }
}

$pageTitle = $editing ? 'Edit Beneficiary' : 'Register Beneficiary';
$activeMenu = 'beneficiaries';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= $editing ? 'Edit Beneficiary' : 'Register New Beneficiary' ?></h1>
        <div class="page-subtitle">Fields marked <span class="text-danger">*</span> are required.</div>
    </div>
    <a href="/bhw/beneficiaries.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Directory</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 small">
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" novalidate>
    <?php csrfField(); ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="mt-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Personal Information</h2>
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control form-control-sm" required value="<?= h($form['full_name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Sex <span class="text-danger">*</span></label>
                        <select name="sex" class="form-select form-select-sm">
                            <option value="Female" <?= $form['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Male" <?= $form['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Birthdate <span class="text-danger">*</span></label>
                        <input type="date" name="birthdate" class="form-control form-control-sm" required value="<?= h($form['birthdate']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Senior Citizen ID No. <span class="text-danger">*</span></label>
                        <input type="text" name="senior_id_number" class="form-control form-control-sm" required value="<?= h($form['senior_id_number']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Purok/Zone <span class="text-danger">*</span></label>
                        <input type="text" name="purok_zone" class="form-control form-control-sm" required value="<?= h($form['purok_zone']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control form-control-sm" placeholder="09XXXXXXXXX" value="<?= h($form['contact_number']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Address <span class="text-danger">*</span></label>
                        <input type="text" name="address" class="form-control form-control-sm" required value="<?= h($form['address']) ?>">
                    </div>
                </div>
            </div>

            <div class="mt-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Health Information</h2>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small">Maintenance Medicine(s)</label>
                        <input type="text" name="maintenance_medicine" class="form-control form-control-sm" placeholder="e.g. Losartan 50mg, Metformin 500mg" value="<?= h($form['maintenance_medicine']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Known Health Conditions</label>
                        <textarea name="health_conditions" class="form-control form-control-sm" rows="2"><?= h($form['health_conditions']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Allergies</label>
                        <textarea name="allergies" class="form-control form-control-sm" rows="2"><?= h($form['allergies']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Emergency Contact</h2>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">Name</label>
                        <input type="text" name="emergency_contact_name" class="form-control form-control-sm" value="<?= h($form['emergency_contact_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Contact Number</label>
                        <input type="text" name="emergency_contact_number" class="form-control form-control-sm" placeholder="09XXXXXXXXX" value="<?= h($form['emergency_contact_number']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="mt-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-2">Household Location</h2>
                <p class="small text-muted">Click on the map to drop a pin at the beneficiary's home. Used for the GIS dashboard.</p>
                <div id="picker-map" style="height: 340px; border-radius: 6px;"></div>
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="form-label small">Latitude</label>
                        <input type="text" id="latitude" name="latitude" class="form-control form-control-sm" readonly value="<?= h((string) $form['latitude']) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Longitude</label>
                        <input type="text" id="longitude" name="longitude" class="form-control form-control-sm" readonly value="<?= h((string) $form['longitude']) ?>">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= $editing ? 'Save Changes' : 'Register Beneficiary' ?></button>
        </div>
    </div>
</form>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const startLat = <?= json_encode((float) $form['latitude']) ?> || <?= BARANGAY_LAT ?>;
const startLng = <?= json_encode((float) $form['longitude']) ?> || <?= BARANGAY_LNG ?>;
const map = L.map('picker-map').setView([startLat, startLng], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
let marker = L.marker([startLat, startLng], {draggable: true}).addTo(map);
function setLatLng(lat, lng) {
    document.getElementById('latitude').value = lat.toFixed(7);
    document.getElementById('longitude').value = lng.toFixed(7);
}
marker.on('dragend', function () {
    const pos = marker.getLatLng();
    setLatLng(pos.lat, pos.lng);
});
map.on('click', function (e) {
    marker.setLatLng(e.latlng);
    setLatLng(e.latlng.lat, e.latlng.lng);
});
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
