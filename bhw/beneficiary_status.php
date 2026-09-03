<?php
/** POST-only handler: soft-delete (deactivate) or reactivate a beneficiary. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';

$user = requireRole(['bhw', 'midwife']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/bhw/beneficiaries.php');
}
requireValidCsrf();

$id = (int) ($_POST['id'] ?? 0);
$newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';
$record = getSeniorCitizenById($conn, $id);

if (!$record) {
    setFlash('error', 'Beneficiary not found.');
} else {
    setSeniorCitizenStatus($conn, $id, $newStatus);
    logActivity($conn, (int) $user['id'], 'update_beneficiary_status', ucfirst($newStatus) . 'd beneficiary: ' . $record['full_name']);
    setFlash('success', 'Beneficiary marked as ' . $newStatus . '.');
}

redirectTo('/bhw/beneficiary_view.php?id=' . $id);
