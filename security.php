<?php
/**
 * Security helpers: login/public rate limiting + safe automatic schema fixes.
 * Every step is wrapped in try/catch so it can never break the app.
 */
function clientIp() { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }

function ensureRateTable($pdo) {
    if (!empty($_SESSION['rl_table_ok'])) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `bucket` varchar(20) NOT NULL,
            `rkey` varchar(150) NOT NULL,
            `hit_at` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_rl` (`bucket`,`rkey`,`hit_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['rl_table_ok'] = 1;
    } catch (Throwable $e) { error_log('rate table: ' . $e->getMessage()); }
}
function rlCount($pdo, $bucket, $key, $window) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND rkey = ? AND hit_at > ?");
        $s->execute([$bucket, substr($key, 0, 150), time() - $window]);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
function rlHit($pdo, $bucket, $key) {
    try {
        $pdo->prepare("INSERT INTO rate_limits (bucket, rkey, hit_at) VALUES (?,?,?)")
            ->execute([$bucket, substr($key, 0, 150), time()]);
        if (random_int(1, 100) === 1) $pdo->prepare("DELETE FROM rate_limits WHERE hit_at < ?")->execute([time() - 86400]);
    } catch (Throwable $e) { /* ignore */ }
}
function rlClear($pdo, $bucket, $key) {
    try { $pdo->prepare("DELETE FROM rate_limits WHERE bucket = ? AND rkey = ?")->execute([$bucket, substr($key, 0, 150)]); }
    catch (Throwable $e) { /* ignore */ }
}

function hasUniqueIndex($pdo, $table, array $cols) {
    $rows = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll();
    $by = [];
    foreach ($rows as $r) if ((int)$r['Non_unique'] === 0) $by[$r['Key_name']][(int)$r['Seq_in_index']] = $r['Column_name'];
    foreach ($by as $c) { ksort($c); if (array_values($c) == $cols) return true; }
    return false;
}

/** Runs once per session: rate table, drop plain-text passwords, guarantee unique attendance keys. */
function ensureSecuritySchema($pdo) {
    ensureRateTable($pdo);
    if (!empty($_SESSION['sec_schema_ok'])) return;

    // 1) Never store readable passwords
    try {
        if ($pdo->query("SHOW COLUMNS FROM `users` LIKE 'plain_password'")->fetch()) {
            try { $pdo->exec("ALTER TABLE `users` MODIFY `plain_password` VARCHAR(255) NULL DEFAULT NULL"); } catch (Throwable $e) {}
            try { $pdo->exec("UPDATE `users` SET `plain_password` = NULL"); } catch (Throwable $e) {}
            $pdo->exec("ALTER TABLE `users` DROP COLUMN `plain_password`");
        }
    } catch (Throwable $e) { error_log('drop plain_password: ' . $e->getMessage()); }

    // 2) One attendance row per member per session (prevents duplicates / wrong percentages)
    $keys = [
        ['attendance',       ['member_id', 'slot_id', 'att_date'], 'uniq_att'],
        ['event_attendance', ['member_id', 'event_id'],            'uniq_evatt'],
    ];
    foreach ($keys as [$t, $cols, $name]) {
        try {
            if (hasUniqueIndex($pdo, $t, $cols)) continue;
            $on = implode(' AND ', array_map(fn($c) => "a.`$c` = b.`$c`", $cols));
            try { $pdo->exec("DELETE a FROM `$t` a JOIN `$t` b ON $on AND a.`id` < b.`id`"); } catch (Throwable $e) {}
            $pdo->exec("ALTER TABLE `$t` ADD UNIQUE KEY `$name` (`" . implode('`,`', $cols) . "`)");
        } catch (Throwable $e) { error_log("unique key $t: " . $e->getMessage()); }
    }
    $_SESSION['sec_schema_ok'] = 1;
}
