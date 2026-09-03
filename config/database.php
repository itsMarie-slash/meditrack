<?php
/**
 * Single shared mysqli connection ($conn), included on every page that
 * needs the database. Uses mysqli procedural style + prepared statements
 * throughout the app (see models/ and services/).
 */

require_once __DIR__ . '/config.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    // Never leak connection details to the browser.
    error_log('Database connection failed: ' . mysqli_connect_error());
    die('A system error occurred. Please try again later.');
}

mysqli_set_charset($conn, 'utf8mb4');
