<?php
/**
 * Site + third-party service settings.
 *
 * Copy this file to config.php (same folder) and fill in real values.
 * config.php is gitignored so real credentials never get committed.
 */

// Database credentials (used by config/database.php)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'meditrack');

// Semaphore SMS API (https://semaphore.co)
define('SEMAPHORE_API_KEY', '');
define('SEMAPHORE_SENDER_NAME', 'MEDITRACK');
define('SEMAPHORE_API_URL', 'https://api.semaphore.co/api/v4/messages');

// Number of days before a scheduled distribution to send a reminder SMS
define('SMS_REMINDER_DAYS_BEFORE', 2);

// Site-wide settings
define('SITE_NAME', 'MediTrack - Barangay New Bulatukan Health Center');
define('BARANGAY_LAT', 7.1907);
define('BARANGAY_LNG', 125.4553);
