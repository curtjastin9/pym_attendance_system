<?php
/**
 * Database connection configuration.
 *
 * LOCAL (XAMPP): works as-is with the defaults.
 * VERCEL + TiDB Cloud: set environment variables
 *   DB_HOST, DB_PORT (4000), DB_NAME, DB_USER, DB_PASS
 * SSL is enabled automatically for remote hosts (set DB_SSL=0 to turn it off).
 *
 * TEMPORARY DEBUG: set env var DB_DEBUG=1 to show the real connection error
 * on the login page. Remove it after the problem is fixed.
 */
require_once __DIR__ . '/session.php';

[$DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET, $DB_PORT] = pym_db_creds();

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 10,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
] + pym_ssl_options($DB_HOST);

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (\PDOException $e) {
    error_log('PYM database connection failed: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    $msg = 'The system cannot reach the database right now. Please try again later.';
    if (getenv('DB_DEBUG') === '1') {
        $msg .= ' [DEBUG host=' . $DB_HOST . ' port=' . $DB_PORT . ' db=' . $DB_NAME . ' user=' . $DB_USER
              . ' pass_len=' . strlen($DB_PASS) . ' | ' . $e->getMessage() . ']';
    }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
