<?php
/**
 * db.php
 * Connects to the MySQL/MariaDB database using PDO.
 * Every file that needs database access includes this file.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'meditrack_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // default XAMPP password is blank
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw errors instead of failing silently
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                   // use REAL prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('System error: unable to connect to the database. Please contact the administrator.');
}
