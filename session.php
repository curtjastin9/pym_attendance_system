<?php
/**
 * Shared bootstrap — included first by every page and API file.
 * - Philippine time zone
 * - Hides PHP errors from visitors (logged instead)
 * - Hardened session cookie
 * - On Vercel (stateless), sessions are stored in MySQL so login/logout work
 */
date_default_timezone_set('Asia/Manila');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/** Database credentials: defaults -> config.local.php -> environment variables. */
function pym_db_creds() {
    $DB_HOST = 'localhost'; $DB_NAME = 'pym_attendance'; $DB_USER = 'root'; $DB_PASS = ''; $DB_CHARSET = 'utf8mb4'; $DB_PORT = '3306';
    if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
    foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_PORT'] as $k) {
        $v = getenv($k);
        if ($v !== false && $v !== '') $$k = $v;
    }
    return [$DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET, $DB_PORT];
}

class PymDbSessionHandler implements SessionHandlerInterface {
    private $pdo;
    public function __construct() {
        [$h, $n, $u, $p, $c, $port] = pym_db_creds();
        $this->pdo = new PDO("mysql:host={$h};port={$port};dbname={$n};charset={$c}", $u, $p, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `php_sessions` (
            `id` varchar(128) NOT NULL, `data` mediumtext NOT NULL, `ts` int(11) NOT NULL,
            PRIMARY KEY (`id`), KEY `ts` (`ts`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }
    public function read($id): string|false {
        $s = $this->pdo->prepare("SELECT data FROM php_sessions WHERE id = ?");
        $s->execute([$id]);
        $r = $s->fetch();
        return $r ? $r['data'] : '';
    }
    public function write($id, $data): bool {
        $this->pdo->prepare("REPLACE INTO php_sessions (id, data, ts) VALUES (?,?,?)")->execute([$id, $data, time()]);
        return true;
    }
    public function destroy($id): bool {
        $this->pdo->prepare("DELETE FROM php_sessions WHERE id = ?")->execute([$id]);
        return true;
    }
    public function gc($max): int|false {
        return $this->pdo->prepare("DELETE FROM php_sessions WHERE ts < ?")->execute([time() - $max]) ? 1 : 0;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (getenv('VERCEL') || getenv('NOW_REGION')) {
        try { session_set_save_handler(new PymDbSessionHandler(), true); }
        catch (Throwable $e) { error_log('session handler: ' . $e->getMessage()); }
    }
    session_start();
}
