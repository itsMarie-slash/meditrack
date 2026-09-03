<?php
/**
 * JSON endpoint consumed by the Leaflet map in map.php. Still behind the
 * role guard - no beneficiary data is exposed without a session, even via
 * this "API-shaped" endpoint.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';
require_once __DIR__ . '/../models/Distribution.php';

requireRole(['bhw', 'midwife', 'ipho']);

$seniors = getAllActiveSeniorCitizens($conn);
$latestStatus = getLatestDistributionStatusBySeniorCitizen($conn);

$features = [];
foreach ($seniors as $s) {
    if ($s['latitude'] === null || $s['longitude'] === null) {
        continue;
    }
    $status = $latestStatus[$s['id']]['status'] ?? null;
    $features[] = [
        'id' => (int) $s['id'],
        'name' => $s['full_name'],
        'purok_zone' => $s['purok_zone'],
        'contact_number' => $s['contact_number'],
        'medicine' => $s['maintenance_medicine'],
        'latitude' => (float) $s['latitude'],
        'longitude' => (float) $s['longitude'],
        'distribution_status' => $status,
        'marker_color' => match ($status) {
            'Dispensed' => 'green',
            'Scheduled' => 'blue',
            'Not Dispensed', 'Missed' => 'red',
            default => 'gray',
        },
    ];
}

header('Content-Type: application/json');
echo json_encode($features);
