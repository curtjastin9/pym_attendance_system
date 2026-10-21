<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security.php';
require_once 'mail.php';

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

function respond($success, $message = '', $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}
function requireSecretariat() {
    if (!isset($_SESSION['user_id'])) respond(false, 'Your session has expired. Please log in again.');
    if (($_SESSION['role'] ?? '') !== 'secretariat') respond(false, 'You do not have permission to perform this action.');
}

function ensureAccountSchema($pdo) {
    if (!empty($_SESSION['acct_schema_ok'])) return;
    try {
        foreach (['email', 'email_key'] as $c) {
            if (!$pdo->query("SHOW COLUMNS FROM `users` LIKE '$c'")->fetch())
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `$c` VARCHAR(150) DEFAULT NULL");
        }
        if (!$pdo->query("SHOW INDEX FROM `users` WHERE Key_name = 'uniq_email_key'")->fetch())
            $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uniq_email_key` (`email_key`)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_codes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email_key` varchar(150) NOT NULL,
            `purpose` varchar(12) NOT NULL,
            `code_hash` varchar(64) NOT NULL,
            `payload` text DEFAULT NULL,
            `attempts` int(11) NOT NULL DEFAULT 0,
            `expires_at` int(11) NOT NULL,
            `sent_at` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_ep` (`email_key`,`purpose`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['acct_schema_ok'] = 1;
    } catch (Throwable $e) { error_log('account schema: ' . $e->getMessage()); }
}

/** Gmail only. One Gmail = one account (dots and +tags ignored). Returns [display, key] or null. */
function normEmail($e) {
    $e = strtolower(trim((string)$e));
    if ($e === '' || strlen($e) > 150 || !filter_var($e, FILTER_VALIDATE_EMAIL)) return null;
    [$l, $d] = explode('@', $e, 2);
    if (!in_array($d, ['gmail.com', 'googlemail.com'], true)) return null;
    $k = str_replace('.', '', explode('+', $l)[0]);
    if ($k === '') return null;
    return [$e, $k . '@gmail.com'];
}
function needEmail($raw) {
    $n = normEmail($raw);
    if (!$n) respond(false, 'Please enter a valid Gmail address (example: yourname@gmail.com).');
    return $n;
}
function codeHash($key, $purpose, $code) { return hash('sha256', $key . '|' . $purpose . '|' . $code); }

function emailTaken($pdo, $key, $exceptId = 0) {
    $s = $pdo->prepare("SELECT id FROM users WHERE email_key = ? AND id != ? LIMIT 1");
    $s->execute([$key, $exceptId]);
    return (bool)$s->fetch();
}

function issueCode($pdo, $email, $key, $purpose, $payload, $subject, $intro) {
    $s = $pdo->prepare("SELECT sent_at FROM email_codes WHERE email_key = ? AND purpose = ?");
    $s->execute([$key, $purpose]);
    $old = $s->fetch();
    if ($old && time() - (int)$old['sent_at'] < 45) respond(false, 'Please wait a few seconds before requesting another code.');
    if (rlCount($pdo, 'mailip', clientIp(), 900) >= 10) respond(false, 'Too many code requests. Please wait 15 minutes and try again.');

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare("DELETE FROM email_codes WHERE email_key = ? AND purpose = ?")->execute([$key, $purpose]);
    $pdo->prepare("INSERT INTO email_codes (email_key, purpose, code_hash, payload, attempts, expires_at, sent_at) VALUES (?,?,?,?,0,?,?)")
        ->execute([$key, $purpose, codeHash($key, $purpose, $code), $payload, time() + 600, time()]);
    rlHit($pdo, 'mailip', clientIp());

    [$ok, $err] = pym_code_mail($email, $code, $subject, $intro);
    if (!$ok) {
        $pdo->prepare("DELETE FROM email_codes WHERE email_key = ? AND purpose = ?")->execute([$key, $purpose]);
        $m = 'Could not send the email right now. Please try again later.';
        if (pym_env('DB_HIDE_ERRORS') !== '1') $m .= ' [' . $err . ']';
        respond(false, $m);
    }
}

function checkCode($pdo, $key, $purpose, $code) {
    $code = preg_replace('/\D/', '', (string)$code);
    $s = $pdo->prepare("SELECT * FROM email_codes WHERE email_key = ? AND purpose = ?");
    $s->execute([$key, $purpose]);
    $r = $s->fetch();
    if (!$r) respond(false, 'No pending code. Please request a new one.');
    if (time() > (int)$r['expires_at']) {
        $pdo->prepare("DELETE FROM email_codes WHERE id = ?")->execute([$r['id']]);
        respond(false, 'This code has expired. Please request a new one.');
    }
    if ((int)$r['attempts'] >= 5) {
        $pdo->prepare("DELETE FROM email_codes WHERE id = ?")->execute([$r['id']]);
        respond(false, 'Too many wrong attempts. Please request a new code.');
    }
    if (strlen($code) !== 6 || !hash_equals($r['code_hash'], codeHash($key, $purpose, $code))) {
        $pdo->prepare("UPDATE email_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$r['id']]);
        respond(false, 'Incorrect code. ' . max(0, 4 - (int)$r['attempts']) . ' attempt(s) left.');
    }
    return $r;
}
function dropCode($pdo, $id) { $pdo->prepare("DELETE FROM email_codes WHERE id = ?")->execute([$id]); }

ensureRateTable($pdo);
ensureAccountSchema($pdo);

switch ($action) {

    case 'register_request': {
        $full = trim($input['full_name'] ?? '');
        $user = trim($input['username'] ?? '');
        $pass = (string)($input['password'] ?? '');
        $role = $input['role'] ?? 'leader';
        if ($full === '' || $user === '' || $pass === '') respond(false, 'Please fill in all required fields.');
        if (strlen($full) > 100 || strlen($user) > 50) respond(false, 'Name or username is too long.');
        if (strlen($pass) < 6) respond(false, 'Password must be at least 6 characters long.');
        if (!in_array($role, ['leader', 'secretariat'], true)) $role = 'leader';
        [$email, $key] = needEmail($input['email'] ?? '');

        $s = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $s->execute([$user]);
        if ($s->fetch()) respond(false, 'That username is already taken. Please choose another.');
        if (emailTaken($pdo, $key)) respond(false, 'This Gmail is already registered. One Gmail can only have one account.');

        $payload = json_encode(['full_name' => $full, 'username' => $user, 'hash' => password_hash($pass, PASSWORD_BCRYPT), 'role' => $role, 'email' => $email]);
        issueCode($pdo, $email, $key, 'register', $payload, 'Your verification code', 'Use this code to verify your email and finish creating your account.');
        respond(true, 'We sent a 6-digit code to ' . $email . '. Enter it below to continue.', ['email' => $email]);
    }

    case 'register_resend': {
        [$email, $key] = needEmail($input['email'] ?? '');
        $s = $pdo->prepare("SELECT payload FROM email_codes WHERE email_key = ? AND purpose = 'register'");
        $s->execute([$key]);
        $r = $s->fetch();
        if (!$r) respond(false, 'Registration session expired. Please fill in the form again.');
        $p = json_decode($r['payload'], true);
        issueCode($pdo, $p['email'] ?? $email, $key, 'register', $r['payload'], 'Your verification code', 'Use this code to verify your email and finish creating your account.');
        respond(true, 'A new code was sent to ' . $email . '.');
    }

    case 'register_verify': {
        [$email, $key] = needEmail($input['email'] ?? '');
        $r = checkCode($pdo, $key, 'register', $input['code'] ?? '');
        $p = json_decode($r['payload'], true);
        if (!is_array($p)) { dropCode($pdo, $r['id']); respond(false, 'Registration session expired. Please fill in the form again.'); }
        $s = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $s->execute([$p['username']]);
        if ($s->fetch()) { dropCode($pdo, $r['id']); respond(false, 'That username was just taken. Please register again with another username.'); }
        if (emailTaken($pdo, $key)) { dropCode($pdo, $r['id']); respond(false, 'This Gmail is already registered.'); }
        try {
            $pdo->prepare("INSERT INTO users (username, password, full_name, role, status, email, email_key) VALUES (?,?,?,?,'pending',?,?)")
                ->execute([$p['username'], $p['hash'], $p['full_name'], $p['role'], $p['email'], $key]);
        } catch (Throwable $e) {
            error_log('register_verify: ' . $e->getMessage());
            respond(false, 'Could not create the account. The username or Gmail may already be in use.');
        }
        dropCode($pdo, $r['id']);
        respond(true, 'Email verified! Your account is now waiting for Secretariat approval.');
    }

    case 'forgot_request': {
        [$email, $key] = needEmail($input['email'] ?? '');
        $s = $pdo->prepare("SELECT id FROM users WHERE email_key = ? LIMIT 1");
        $s->execute([$key]);
        if ($s->fetch()) {
            issueCode($pdo, $email, $key, 'reset', null, 'Password reset code', 'Use this code to reset your password.');
        }
        respond(true, 'If that Gmail is registered, a reset code has been sent. Enter it below.', ['email' => $email]);
    }

    case 'forgot_reset': {
        [$email, $key] = needEmail($input['email'] ?? '');
        $pass = (string)($input['password'] ?? '');
        if (strlen($pass) < 6) respond(false, 'Password must be at least 6 characters long.');
        $r = checkCode($pdo, $key, 'reset', $input['code'] ?? '');
        $s = $pdo->prepare("SELECT id FROM users WHERE email_key = ? LIMIT 1");
        $s->execute([$key]);
        $u = $s->fetch();
        if (!$u) { dropCode($pdo, $r['id']); respond(false, 'Account not found.'); }
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($pass, PASSWORD_BCRYPT), $u['id']]);
        dropCode($pdo, $r['id']);
        respond(true, 'Password updated! You can now log in.');
    }

    case 'admin_accounts': {
        requireSecretariat();
        respond(true, '', $pdo->query("SELECT id, full_name, username, email, role, status FROM users ORDER BY full_name ASC")->fetchAll());
    }

    case 'admin_set_password': {
        requireSecretariat();
        $id = (int)($input['id'] ?? 0);
        $pass = (string)($input['password'] ?? '');
        if (strlen($pass) < 6) respond(false, 'Password must be at least 6 characters long.');
        $s = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $s->execute([$id]);
        if (!$s->fetch()) respond(false, 'User not found.');
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
        respond(true, 'Password updated.');
    }

    case 'admin_set_email': {
        requireSecretariat();
        $id = (int)($input['id'] ?? 0);
        $s = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $s->execute([$id]);
        if (!$s->fetch()) respond(false, 'User not found.');
        $raw = trim((string)($input['email'] ?? ''));
        if ($raw === '') {
            $pdo->prepare("UPDATE users SET email = NULL, email_key = NULL WHERE id = ?")->execute([$id]);
            respond(true, 'Email removed.');
        }
        [$email, $key] = needEmail($raw);
        if (emailTaken($pdo, $key, $id)) respond(false, 'That Gmail is already used by another account.');
        try {
            $pdo->prepare("UPDATE users SET email = ?, email_key = ? WHERE id = ?")->execute([$email, $key, $id]);
        } catch (Throwable $e) { respond(false, 'That Gmail is already used by another account.'); }
        respond(true, 'Email updated.');
    }

    default:
        respond(false, 'Unknown action.');
}
