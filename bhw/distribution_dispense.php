<?php
/**
 * POST-only handler for changing a distribution's status.
 * "dispense" runs the transactional stock-decrement flow (4.4) and then
 * immediately recomputes the demand forecast for that medicine (4.5) -
 * this is the auto-recompute hook the forecasting module depends on.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../services/ForecastService.php';

$user = requireRole(['bhw', 'midwife']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/bhw/distribution.php');
}
requireValidCsrf();

$id = (int) ($_POST['id'] ?? 0);
$action = sanitizeInput($_POST['action'] ?? '');

if ($action === 'dispense') {
    $result = recordDistributionDispensed($conn, $id, (int) $user['id']);
    if ($result['ok']) {
        $forecastService = new ForecastService($conn);
        $forecastService->calculateForecast($result['medicine_id']);

        logActivity($conn, (int) $user['id'], 'dispense_distribution', 'Marked distribution #' . $id . ' as Dispensed; stock decremented and forecast recomputed.');
        setFlash('success', 'Distribution marked as dispensed. Stock updated and forecast recomputed.');
    } else {
        setFlash('error', $result['error']);
    }
} elseif (in_array($action, ['not_dispensed', 'missed'], true)) {
    $status = $action === 'missed' ? 'Missed' : 'Not Dispensed';
    updateDistributionStatus($conn, $id, $status);
    logActivity($conn, (int) $user['id'], 'update_distribution_status', 'Marked distribution #' . $id . ' as ' . $status . '.');
    setFlash('success', 'Distribution marked as ' . $status . '.');
} else {
    setFlash('error', 'Unknown action.');
}

redirectTo('/bhw/distribution.php');
