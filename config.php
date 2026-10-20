<?php
/**
 * Database connection configuration.
 *
 * LOCAL (XAMPP): works as-is with the defaults below.
 * LIVE SERVER:   copy config.local.php.example to config.local.php and put your
 *                real database name / user / password there. config.local.php
 *                overrides the defaults, so you never edit this file on the host.
 */
require_once __DIR__ . '/session.php';

$DB_HOST    = 'localhost';
$DB_NAME    = 'pym_attendance';
$DB_USER    = 'root';
$DB_PASS    = '';
$DB_CHARSET = 'utf8mb4';

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Keep MySQL timestamps (created_at, etc.) in Philippine time too
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (\PDOException $e) {
    error_log('PYM database connection failed: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'The system cannot reach the database right now. Please try again later.'
    ]);
    exit;
}
