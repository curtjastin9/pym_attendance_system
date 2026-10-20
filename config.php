<?php
/**
 * Database connection configuration.
 *
 * LOCAL (XAMPP): works as-is with the defaults.
 * LIVE / VERCEL: set environment variables DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 *                (or create config.local.php). See session.php -> pym_db_creds().
 */
require_once __DIR__ . '/session.php';

[$DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET, $DB_PORT] = pym_db_creds();

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
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
