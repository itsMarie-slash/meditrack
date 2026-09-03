<?php
/**
 * SemaphoreSmsService
 * ====================
 * Plain PHP functions wrapping the Semaphore SMS API
 * (https://api.semaphore.co/api/v4/messages) via curl, plus the two
 * higher-level triggers required by the project (4.7):
 *   1. Upcoming distribution reminders (called from cron/send_reminders.php)
 *   2. Program update/broadcast messages (called from bhw/sms_broadcast.php)
 *
 * Every attempt - success or failure - is written to sms_logs so a failed
 * send (invalid number, insufficient Semaphore credits, network error)
 * is visible to staff instead of failing silently.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Sms.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Low-level call to the Semaphore API. Returns:
 *   ['success' => bool, 'http_code' => int, 'response' => array|string, 'error' => string|null]
 */
function sendSmsViaSemaphore(string $number, string $message): array
{
    if (empty(SEMAPHORE_API_KEY)) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'Semaphore API key is not configured (config/config.php).',
        ];
    }

    $ch = curl_init(SEMAPHORE_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query([
            'apikey' => SEMAPHORE_API_KEY,
            'number' => $number,
            'message' => $message,
            'sendername' => SEMAPHORE_SENDER_NAME,
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'http_code' => $httpCode, 'response' => null, 'error' => $curlError];
    }

    $decoded = json_decode($response, true);
    // Semaphore returns HTTP 200 with a JSON array of message objects on success.
    $success = $httpCode === 200 && $decoded !== null && !isset($decoded['message']);

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'response' => $decoded ?? $response,
        'error' => $success ? null : ('Semaphore API error (HTTP ' . $httpCode . '): ' . (is_string($response) ? $response : json_encode($response))),
    ];
}

/**
 * Send a reminder SMS for one upcoming distribution and log the attempt.
 * Skips (and returns null) beneficiaries with no registered mobile number.
 */
function sendDistributionReminder(mysqli $conn, array $distribution): ?array
{
    $number = trim($distribution['contact_number'] ?? '');
    if ($number === '') {
        return null; // no number on file - caller is responsible for counting/reporting this
    }

    $message = sprintf(
        'Hello %s! This is a reminder from Barangay New Bulatukan Health Center: your %s distribution is scheduled on %s. Please visit the health center. Thank you!',
        $distribution['senior_name'],
        $distribution['medicine_name'],
        formatDate($distribution['scheduled_date'])
    );

    $result = sendSmsViaSemaphore($number, $message);
    $status = $result['success'] ? 'sent' : 'failed';
    logSms($conn, (int) $distribution['senior_citizen_id'], $number, $message, 'reminder', $status, $result['error'] ?? '');

    return array_merge($result, ['phone_number' => $number]);
}

/**
 * Send a broadcast message to a list of beneficiaries (e.g. all, or a
 * filtered subset by Purok/Zone). Returns a tally so the caller can show
 * "X sent, Y failed, Z skipped (no number)" to the BHW.
 */
function sendBroadcastSms(mysqli $conn, array $seniorCitizens, string $message): array
{
    $sent = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($seniorCitizens as $sc) {
        $number = trim($sc['contact_number'] ?? '');
        if ($number === '') {
            $skipped++;
            continue;
        }

        $result = sendSmsViaSemaphore($number, $message);
        $status = $result['success'] ? 'sent' : 'failed';
        logSms($conn, (int) $sc['id'], $number, $message, 'broadcast', $status, $result['error'] ?? '');

        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped, 'total' => count($seniorCitizens)];
}
