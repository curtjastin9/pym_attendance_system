<?php
/**
 * Security helpers: login/public rate limiting + safe automatic schema fixes.
 * Every step is wrapped in try/catch so it can never break the app.
 */
function clientIp() { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }

function createRateTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `bucket` varchar(20) NOT NULL,
        `rkey` varchar(150) NOT NULL,
        `hit_at` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_rl` (`bucket`,`rkey`,`hit_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** The table is created lazily (only if a query on it fails) — no extra queries per request. */
function ensureRateTable($pdo) { }
function rlCount($pdo, $bucket, $key, $window) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND rkey = ? AND hit_at > ?");
        $s->execute([$bucket, substr($key, 0, 150), time() - $window]);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) { try { createRateTable($pdo); } catch (Throwable $e2) {} return 0; }
}
function rlHit($pdo, $bucket, $key) {
    try {
        $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM rate_limits")->fetchColumn() + 1;
        $pdo->prepare("INSERT INTO rate_limits (id, bucket, rkey, hit_at) VALUES (?,?,?,?)")
            ->execute([$nid, $bucket, substr($key, 0, 150), time()]);
        if (random_int(1, 50) === 1) $pdo->prepare("DELETE FROM rate_limits WHERE hit_at < ?")->execute([time() - 86400]);
    } catch (Throwable $e) { try { createRateTable($pdo); } catch (Throwable $e2) {} }
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

/* ---------- Activity log ---------- */
function createActivityTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_log` (
        `id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL DEFAULT 0,
        `user_name` varchar(100) DEFAULT NULL,
        `role` varchar(20) DEFAULT NULL,
        `action` varchar(40) NOT NULL,
        `target` varchar(150) DEFAULT NULL,
        `details` text DEFAULT NULL,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function actCut($s, $n) { $s = (string)$s; return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); }
/** Never throws. Call AFTER a transaction is committed. */
function logActivity($pdo, $action, $target = '', $details = '') {
    $row = [(int)($_SESSION['user_id'] ?? 0), actCut($_SESSION['full_name'] ?? '', 100), actCut($_SESSION['role'] ?? '', 20),
            actCut($action, 40), actCut($target, 150), actCut($details, 2000), date('Y-m-d H:i:s')];
    for ($i = 0; $i < 2; $i++) {
        try {
            $id = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM activity_log")->fetchColumn() + 1;
            $pdo->prepare("INSERT INTO activity_log (id,user_id,user_name,role,action,target,details,created_at) VALUES (?,?,?,?,?,?,?,?)")
                ->execute(array_merge([$id], $row));
            if (random_int(1, 50) === 1) $pdo->prepare("DELETE FROM activity_log WHERE created_at < ?")->execute([date('Y-m-d H:i:s', time() - 86400 * 180)]);
            return;
        } catch (Throwable $e) {
            if ($i === 0) { try { createActivityTable($pdo); } catch (Throwable $e2) { return; } }
        }
    }
}


/* ---------- Shared helpers ---------- */
/** null = secretariat, -1 = leader without slot, otherwise slot id */
function pymLeaderSlot($pdo) {
    if (($_SESSION['role'] ?? '') === 'secretariat') return null;
    $s = $pdo->prepare("SELECT slot_id FROM slot_leaders WHERE user_id = ? LIMIT 1");
    $s->execute([$_SESSION['user_id'] ?? 0]);
    $r = $s->fetch();
    if ($r && $r['slot_id']) return (int)$r['slot_id'];
    $s = $pdo->prepare("SELECT slot_id FROM users WHERE id = ?");
    $s->execute([$_SESSION['user_id'] ?? 0]);
    $r = $s->fetch();
    if ($r && $r['slot_id'] !== null) return (int)$r['slot_id'];
    return -1;
}
/** Secretariat or an account holding a Commission On Youth position. */
function isCoyOfficer($pdo) {
    if (!isset($_SESSION['user_id'])) return false;
    if (($_SESSION['role'] ?? '') === 'secretariat') return true;
    try {
        $s = $pdo->prepare("SELECT coy_position_id FROM users WHERE id = ?");
        $s->execute([$_SESSION['user_id']]);
        return (bool)$s->fetchColumn();
    } catch (Throwable $e) { return false; }
}
/** Creates attendance_submissions. Once per session. */
function ensureExtraSchema($pdo) {
    if (!empty($_SESSION['extra_schema_ok'])) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance_submissions` (
            `id` int(11) NOT NULL,
            `slot_id` int(11) NOT NULL,
            `att_date` date NOT NULL,
            `submitted_by` int(11) NOT NULL DEFAULT 0,
            `submitted_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_sub` (`slot_id`,`att_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { error_log('submissions table: ' . $e->getMessage()); }
    $_SESSION['extra_schema_ok'] = 1;
}
/** Sessions that were recorded: any attendance row OR a submission. Returns ["slot|date" => true]. */
function pymRecordedSessions($pdo, $from, $to) {
    $out = [];
    try {
        $st = $pdo->prepare("SELECT slot_id, att_date FROM attendance WHERE att_date BETWEEN ? AND ? UNION SELECT slot_id, att_date FROM attendance_submissions WHERE att_date BETWEEN ? AND ?");
        $st->execute([$from, $to, $from, $to]);
    } catch (Throwable $e) {
        $st = $pdo->prepare("SELECT DISTINCT slot_id, att_date FROM attendance WHERE att_date BETWEEN ? AND ?");
        $st->execute([$from, $to]);
    }
    foreach ($st->fetchAll() as $t) $out[$t['slot_id'] . '|' . $t['att_date']] = true;
    return $out;
}
function pymFirstDate($pdo) {
    $a = $pdo->query("SELECT MIN(att_date) FROM attendance")->fetchColumn();
    try { $b = $pdo->query("SELECT MIN(att_date) FROM attendance_submissions")->fetchColumn(); } catch (Throwable $e) { $b = null; }
    if (!$a) return $b ?: null;
    if (!$b) return $a;
    return min($a, $b);
}


/* ---------- Slot history: a member keeps old attendance under the old slot when they move ---------- */
function pymEnsureSlotHistory($pdo) {
    if (!empty($_SESSION['slothist_ok'])) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `member_slot_history` (
            `id` int(11) NOT NULL,
            `member_id` int(11) NOT NULL,
            `slot_id` int(11) DEFAULT NULL,
            `effective_from` date NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_msh` (`member_id`,`effective_from`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['slothist_ok'] = 1;
    } catch (Throwable $e) { error_log('slot history table: ' . $e->getMessage()); }
}
/** Call when a member's slot changes. Effective from today; earlier dates keep the old slot. */
function pymSlotChange($pdo, $memberId, $oldSlot, $newSlot) {
    $oldSlot = ($oldSlot === null || $oldSlot === false || $oldSlot === '') ? null : (int)$oldSlot;
    $newSlot = ($newSlot === null || $newSlot === false || $newSlot === '') ? null : (int)$newSlot;
    if ($oldSlot === $newSlot) return;
    try {
        pymEnsureSlotHistory($pdo);
        $memberId = (int)$memberId;
        $nid = function () use ($pdo) { return (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM member_slot_history")->fetchColumn() + 1; };
        $c = $pdo->prepare("SELECT COUNT(*) FROM member_slot_history WHERE member_id = ?");
        $c->execute([$memberId]);
        if ((int)$c->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO member_slot_history (id, member_id, slot_id, effective_from) VALUES (?,?,?,?)")
                ->execute([$nid(), $memberId, $oldSlot, '2026-10-01']);
        }
        $today = max(date('Y-m-d'), '2026-10-01');
        $s = $pdo->prepare("SELECT id FROM member_slot_history WHERE member_id = ? AND effective_from = ?");
        $s->execute([$memberId, $today]);
        $id = $s->fetchColumn();
        if ($id) $pdo->prepare("UPDATE member_slot_history SET slot_id = ? WHERE id = ?")->execute([$newSlot, $id]);
        else $pdo->prepare("INSERT INTO member_slot_history (id, member_id, slot_id, effective_from) VALUES (?,?,?,?)")
                ->execute([$nid(), $memberId, $newSlot, $today]);
    } catch (Throwable $e) { error_log('slot change: ' . $e->getMessage()); }
}
/** [member_id => [[from, slot_id], ...]] */
function pymSlotHistory($pdo) {
    $out = [];
    try {
        foreach ($pdo->query("SELECT member_id, slot_id, effective_from FROM member_slot_history ORDER BY member_id, effective_from, id")->fetchAll() as $r)
            $out[(int)$r['member_id']][] = [$r['effective_from'], $r['slot_id'] === null ? null : (int)$r['slot_id']];
    } catch (Throwable $e) { try { pymEnsureSlotHistory($pdo); } catch (Throwable $e2) {} }
    return $out;
}
/** The member's slot on a given date (falls back to current slot if no history). */
function pymSlotOn($hist, $m, $date) {
    $id = (int)$m['id'];
    if (!isset($hist[$id])) return $m['slot_id'] === null ? null : (int)$m['slot_id'];
    $r = null;
    foreach ($hist[$id] as [$from, $sid]) { if ($from <= $date) $r = $sid; else break; }
    return $r;
}


/* ---------- Every user account must also exist as a Youth Member (matched by full name) ---------- */
function pymSyncUserYouth($pdo) {
    try {
        $rows = $pdo->query("SELECT DISTINCT TRIM(u.full_name) AS n FROM users u WHERE TRIM(u.full_name) <> '' AND NOT EXISTS (SELECT 1 FROM youth_members y WHERE LOWER(TRIM(y.full_name)) = LOWER(TRIM(u.full_name)))")->fetchAll(PDO::FETCH_COLUMN);
        if (!$rows) return;
        $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM youth_members")->fetchColumn() + 1;
        $ins = $pdo->prepare("INSERT INTO youth_members (id, full_name, status) VALUES (?,?,'active')");
        foreach ($rows as $n) $ins->execute([$nid++, substr($n, 0, 100)]);
    } catch (Throwable $e) { error_log('sync user youth: ' . $e->getMessage()); }
}


/* ---------- Slot leaders show as "Slot Leader · <slot>" in the Group/Slot column ---------- */
function pymLeaderLabels($pdo) {
    $out = [];
    try {
        foreach ($pdo->query("SELECT y.id, g.name FROM youth_members y
            JOIN users u ON LOWER(TRIM(u.full_name)) = LOWER(TRIM(y.full_name))
            JOIN slot_leaders sl ON sl.user_id = u.id
            JOIN groups_slots g ON g.id = sl.slot_id")->fetchAll() as $r) $out[(int)$r['id']] = 'Slot Leader · ' . $r['name'];
    } catch (Throwable $e) { error_log('leader labels: ' . $e->getMessage()); }
    return $out;
}
function pymApplyLeaderSlot(&$rows, $labels, $idKey, $slotKey) {
    if (!$labels) return;
    foreach ($rows as &$r) { $k = (int)($r[$idKey] ?? 0); if (isset($labels[$k])) $r[$slotKey] = $labels[$k]; }
    unset($r);
}

/** [member_id => slot_id] for youth members whose user account is a slot leader (matched by full name). */
function pymLeaderSlotMap($pdo) {
    $out = [];
    try {
        foreach ($pdo->query("SELECT y.id, sl.slot_id FROM youth_members y
            JOIN users u ON LOWER(TRIM(u.full_name)) = LOWER(TRIM(y.full_name))
            JOIN slot_leaders sl ON sl.user_id = u.id")->fetchAll() as $r) $out[(int)$r['id']] = (int)$r['slot_id'];
    } catch (Throwable $e) { error_log('leader slot map: ' . $e->getMessage()); }
    return $out;
}


/* ---------- Event servers (events that need servers) ---------- */
function ensureEventServerSchema($pdo) {
    if (!empty($_SESSION['evsrv_ok'])) return;
    try {
        if (!$pdo->query("SHOW COLUMNS FROM `events` LIKE 'has_servers'")->fetch())
            $pdo->exec("ALTER TABLE `events` ADD COLUMN `has_servers` TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `event_servers` (
            `id` int(11) NOT NULL,
            `event_id` int(11) NOT NULL,
            `member_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_evsrv` (`event_id`,`member_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['evsrv_ok'] = 1;
    } catch (Throwable $e) { error_log('event servers schema: ' . $e->getMessage()); }
}
/** [event_id => [[member_id, full_name], ...]] */
function pymEventServers($pdo) {
    $out = [];
    try {
        foreach ($pdo->query("SELECT s.event_id, y.id AS member_id, y.full_name FROM event_servers s JOIN youth_members y ON y.id = s.member_id ORDER BY y.full_name ASC")->fetchAll() as $r)
            $out[(int)$r['event_id']][] = ['member_id' => (int)$r['member_id'], 'full_name' => $r['full_name']];
    } catch (Throwable $e) { error_log('event servers: ' . $e->getMessage()); }
    return $out;
}
