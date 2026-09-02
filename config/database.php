<?php
/**
 * config/database.php
 * -----------------------------------------------------------------
 * Central database connection file using PDO.
 * PDO is used (instead of mysqli) because it supports prepared
 * statements with named placeholders and works with any driver,
 * which keeps every module's SQL consistent and injection-safe.
 *
 * Every other PHP file that needs the database includes this file
 * and then uses the returned $pdo object.
 * -----------------------------------------------------------------
 */

// --- Connection settings (adjust to match your XAMPP setup) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'meditrack_db');
define('DB_USER', 'root');
define('DB_PASS', '');       // default XAMPP MySQL root password is blank
define('DB_CHARSET', 'utf8mb4');

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    // Throw exceptions on error instead of silent failure/warnings
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Return rows as associative arrays by default
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Use real prepared statements (not emulated) for stronger SQL-injection protection
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log the real error instead of displaying it to users.
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Please contact the system administrator.');
}
