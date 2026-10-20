<?php
/**
 * Shared bootstrap — included first by every page and API file.
 * Philippine time zone, hidden PHP errors, hardened session cookie,
 * DB-backed sessions on Vercel, TiDB Cloud (SSL) support with automatic fallbacks.
 */
date_default_timezone_set('Asia/Manila');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function pym_env($k) {
    $v = getenv($k);
    if ($v === false || $v === '') $v = $_ENV[$k] ?? $_SERVER[$k] ?? false;
    return ($v === false || $v === '') ? null : (string)$v;
}

/** Database credentials: defaults -> config.local.php -> environment variables. */
function pym_db_creds() {
    $DB_HOST = 'localhost'; $DB_NAME = 'pym_attendance'; $DB_USER = 'root'; $DB_PASS = ''; $DB_CHARSET = 'utf8mb4'; $DB_PORT = '3306';
    if (file_exists(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
    foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','DB_PORT'] as $k) {
        $v = pym_env($k);
        if ($v !== null) $$k = ($k === 'DB_PASS') ? rtrim($v, "\r\n") : trim($v);
    }
    // Clean a host pasted as "mysql://host:4000" or "host:4000"
    $DB_HOST = preg_replace('#^[a-z]+://#i', '', $DB_HOST);
    if (preg_match('/^(.*):(\d+)$/', $DB_HOST, $m)) { $DB_HOST = $m[1]; $DB_PORT = $m[2]; }
    $DB_HOST = rtrim($DB_HOST, '/');
    return [$DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET, $DB_PORT];
}

/** True when the connection must use TLS. */
function pym_ssl_required($host) {
    $env = pym_env('DB_SSL');
    $remote = !in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    return ($env === null) ? $remote : !in_array(strtolower($env), ['0', 'false', 'off', 'no'], true);
}

/** List of SSL option sets to try, in order. */
function pym_ssl_attempts($host) {
    if (!pym_ssl_required($host)) return [[]];

    $attempts = [];
    $files = [];
    $custom = pym_env('DB_SSL_CA');
    if ($custom) { $files[] = $custom; $files[] = __DIR__ . '/' . ltrim($custom, '/'); }
    $files[] = __DIR__ . '/cacert.pem';
    if (function_exists('openssl_get_cert_locations')) {
        $loc = @openssl_get_cert_locations();
        if (is_array($loc)) {
            if (!empty($loc['default_cert_file'])) $files[] = $loc['default_cert_file'];
            if (!empty($loc['default_cert_dir']))  $dirs[] = $loc['default_cert_dir'];
        }
    }
    $files = array_merge($files, [
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/cert.pem',
        '/etc/ssl/cert.pem',
        '/etc/ssl/ca-bundle.pem',
        '/opt/lib/ca-bundle.crt',
        '/var/task/ca-bundle.crt',
    ]);
    foreach ($files as $f) {
        if (@is_readable($f) && @is_file($f)) {
            $attempts[] = [PDO::MYSQL_ATTR_SSL_CA => $f, PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false];
        }
    }
    foreach (['/etc/ssl/certs', '/etc/pki/tls/certs'] as $d) {
        if (@is_dir($d)) {
            $attempts[] = [PDO::MYSQL_ATTR_SSL_CAPATH => $d, PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false];
            break;
        }
    }
    // Forces TLS on even when no CA bundle exists on the server (encrypted, no certificate check)
    $attempts[] = [PDO::MYSQL_ATTR_SSL_CIPHER => 'DEFAULT', PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false];
    $attempts[] = [PDO::MYSQL_ATTR_SSL_CIPHER => 'ECDHE-RSA-AES128-GCM-SHA256', PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false];
    return $attempts;
}

/** Opens the database connection (tries every SSL strategy). Throws on failure. */
function pym_connect() {
    if (!empty($GLOBALS['PYM_PDO'])) return $GLOBALS['PYM_PDO'];
    [$h, $n, $u, $p, $c, $port] = pym_db_creds();
    $dsn = "mysql:host={$h};port={$port};dbname={$n};charset={$c}";
    $base = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
    ];
    $last = null;
    $errs = [];
    foreach (pym_ssl_attempts($h) as $i => $ssl) {
        try {
            $pdo = new PDO($dsn, $u, $p, $ssl + $base);
            $GLOBALS['PYM_PDO'] = $pdo;
            return $pdo;
        } catch (Throwable $e) {
            $last = $e;
            $errs[] = '#' . ($i + 1) . ' ' . $e->getMessage();
            error_log('PYM DB attempt failed: ' . $e->getMessage());
        }
    }
    if (count($errs) > 1) throw new RuntimeException(implode(' || ', $errs));
    throw $last ?: new RuntimeException('Database connection failed.');
}

class PymDbSessionHandler implements SessionHandlerInterface {
    private $pdo;
    public function __construct() {
        $this->pdo = pym_connect();
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
    if (getenv('VERCEL') || getenv('NOW_REGION') || pym_env('VERCEL')) {
        try { session_set_save_handler(new PymDbSessionHandler(), true); }
        catch (Throwable $e) { error_log('session handler: ' . $e->getMessage()); }
    }
    session_start();
}
