<?php
/**
 * cron/send_reminders.php
 * ========================
 * Standalone script (no session/auth - meant to be run from the command
 * line, not a browser) that sends SMS reminders for distributions
 * scheduled within the next SMS_REMINDER_DAYS_BEFORE days.
 *
 * Local XAMPP setup: schedule this with Windows Task Scheduler, e.g. daily
 * at 8:00 AM, running:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\meditrack\cron\send_reminders.php
 * (A real Linux deployment would use a standard cron entry instead:
 *   0 8 * * * /usr/bin/php /path/to/meditrack/cron/send_reminders.php)
 *
 * Run manually to test: php cron/send_reminders.php
 */

// Guard against being run through a browser - this script has no auth.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Distribution.php';
require_once __DIR__ . '/../services/SemaphoreSmsService.php';
require_once __DIR__ . '/../models/ActivityLog.php';

$daysAhead = defined('SMS_REMINDER_DAYS_BEFORE') ? SMS_REMINDER_DAYS_BEFORE : 2;

echo "[" . date('Y-m-d H:i:s') . "] Checking for distributions scheduled within the next {$daysAhead} day(s)...\n";

$upcoming = getUpcomingDistributionsNeedingReminder($conn, $daysAhead);
echo count($upcoming) . " distribution(s) found.\n";

$sent = 0;
$failed = 0;
$skippedNoNumber = 0;

foreach ($upcoming as $distribution) {
    $result = sendDistributionReminder($conn, $distribution);

    if ($result === null) {
        $skippedNoNumber++;
        echo "  SKIP (no contact number): {$distribution['senior_name']} - {$distribution['medicine_name']}\n";
        continue;
    }

    // Whether the SMS succeeded or failed, mark reminder_sent so we don't
    // retry a beneficiary who already had a valid attempt logged today.
    markReminderSent($conn, (int) $distribution['id']);

    if ($result['success']) {
        $sent++;
        echo "  SENT: {$distribution['senior_name']} ({$result['phone_number']}) - {$distribution['medicine_name']}\n";
    } else {
        $failed++;
        echo "  FAILED: {$distribution['senior_name']} ({$result['phone_number']}) - {$result['error']}\n";
    }
}

$summary = "Reminder run complete: {$sent} sent, {$failed} failed, {$skippedNoNumber} skipped (no number).";
echo $summary . "\n";

// user_id NULL-equivalent: activity_logs.user_id is nullable, so a system
// (non-interactive) run is logged with no attributable user.
$stmt = mysqli_prepare($conn, 'INSERT INTO activity_logs (user_id, action, description) VALUES (NULL, ?, ?)');
$action = 'sms_reminder_cron';
mysqli_stmt_bind_param($stmt, 'ss', $action, $summary);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
