<?php
/**
 * Database connection.
 * Vercel + TiDB Cloud env vars: DB_HOST, DB_PORT (4000), DB_NAME, DB_USER, DB_PASS
 * Set DB_HIDE_ERRORS=1 once everything works to hide technical details.
 */
require_once __DIR__ . '/session.php';

try {
    $pdo = pym_connect();
} catch (Throwable $e) {
    error_log('PYM database connection failed: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    $msg = 'The system cannot reach the database right now. Please try again later.';
    if (pym_env('DB_HIDE_ERRORS') !== '1') {
        [$h, $n, $u, $p, $c, $port] = pym_db_creds();
        $msg .= ' [host=' . $h . ' port=' . $port . ' db=' . $n . ' user=' . $u . ' pass_len=' . strlen($p)
              . ' | ' . $e->getMessage() . ']';
    }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
