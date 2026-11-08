<?php
/* Event leaders helpers. Include-only file.
 *
 * A leader of an event is either:
 *   - a user account picked in `event_leader_users`, or
 *   - a youth member picked in `event_leader_youth` whose name matches an approved account
 *     (youth without an account are only listed, they cannot log in so they get no privilege).
 *
 * Privilege rule: a leader may open / mark that event ONLY while it is not done
 * (event_date >= today). Once the event date has passed the privilege is gone,
 * but everything they saved stays. Secretariat is never limited (handled by callers).
 */
function ensureEventLeaderSchema($pdo) {
    if (!empty($_SESSION['evlead_ok2'])) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `event_leader_users` (
            `id` int(11) NOT NULL,
            `event_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_elu` (`event_id`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `event_leader_youth` (
            `id` int(11) NOT NULL,
            `event_id` int(11) NOT NULL,
            `member_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_ely` (`event_id`,`member_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['evlead_ok2'] = 1;
    } catch (Throwable $e) { error_log('event leaders schema: ' . $e->getMessage()); }
}

/** User account ids that lead an event (picked directly). */
function pymEventLeaderIds($pdo, $eventId) {
    ensureEventLeaderSchema($pdo);
    try {
        $s = $pdo->prepare("SELECT user_id FROM event_leader_users WHERE event_id = ?");
        $s->execute([(int)$eventId]);
        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { return []; }
}

/** Youth member ids picked as leaders of an event. */
function pymEventLeaderYouthIds($pdo, $eventId) {
    ensureEventLeaderSchema($pdo);
    try {
        $s = $pdo->prepare("SELECT member_id FROM event_leader_youth WHERE event_id = ?");
        $s->execute([(int)$eventId]);
        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { return []; }
}

/** Replaces the leaders of an event. Call OUTSIDE of a transaction the first time (it may create tables). */
function pymSaveEventLeaders($pdo, $eventId, $userIds, $youthIds) {
    ensureEventLeaderSchema($pdo);
    $eventId = (int)$eventId;
    $clean = function ($a) {
        if (!is_array($a)) $a = [];
        return array_values(array_unique(array_filter(array_map('intval', $a), fn($v) => $v > 0)));
    };
    $userIds = $clean($userIds);
    $youthIds = $clean($youthIds);

    $pdo->prepare("DELETE FROM event_leader_users WHERE event_id = ?")->execute([$eventId]);
    $pdo->prepare("DELETE FROM event_leader_youth WHERE event_id = ?")->execute([$eventId]);

    if ($userIds) {
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $v = $pdo->prepare("SELECT id FROM users WHERE status = 'approved' AND id IN ($ph)");
        $v->execute($userIds);
        $valid = array_map('intval', $v->fetchAll(PDO::FETCH_COLUMN));
        $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM event_leader_users")->fetchColumn() + 1;
        $ins = $pdo->prepare("INSERT INTO event_leader_users (id, event_id, user_id) VALUES (?,?,?)");
        foreach ($valid as $u) $ins->execute([$nid++, $eventId, $u]);
    }
    if ($youthIds) {
        $ph = implode(',', array_fill(0, count($youthIds), '?'));
        $v = $pdo->prepare("SELECT id FROM youth_members WHERE id IN ($ph)");
        $v->execute($youthIds);
        $valid = array_map('intval', $v->fetchAll(PDO::FETCH_COLUMN));
        $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM event_leader_youth")->fetchColumn() + 1;
        $ins = $pdo->prepare("INSERT INTO event_leader_youth (id, event_id, member_id) VALUES (?,?,?)");
        foreach ($valid as $m) $ins->execute([$nid++, $eventId, $m]);
    }
}

/** Date (Y-m-d) of the event if the current user leads it, otherwise null. */
function pymLeaderEventDate($pdo, $eventId) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid) return null;
    $eid = (int)$eventId;
    // 1) picked directly as an account
    $s = $pdo->prepare("SELECT e.event_date FROM event_leader_users l JOIN events e ON e.id = l.event_id WHERE l.event_id = ? AND l.user_id = ? LIMIT 1");
    $s->execute([$eid, $uid]);
    $d = $s->fetchColumn();
    if ($d) return (string)$d;
    // 2) picked as a youth whose name matches this account
    $s = $pdo->prepare("SELECT e.event_date FROM event_leader_youth l
        JOIN events e ON e.id = l.event_id
        JOIN youth_members y ON y.id = l.member_id
        JOIN users u ON u.status = 'approved' AND LOWER(TRIM(u.full_name)) = LOWER(TRIM(y.full_name))
        WHERE l.event_id = ? AND u.id = ? LIMIT 1");
    $s->execute([$eid, $uid]);
    $d = $s->fetchColumn();
    return $d ? (string)$d : null;
}

/** 'open' = current user leads it and it is not done yet, 'done' = leads it but it is finished, 'no' = not a leader. */
function pymEventLeaderState($pdo, $eventId) {
    ensureEventLeaderSchema($pdo);
    try {
        $d = pymLeaderEventDate($pdo, $eventId);
        if (!$d) return 'no';
        return (substr($d, 0, 10) >= date('Y-m-d')) ? 'open' : 'done';
    } catch (Throwable $e) { return 'no'; }
}

/** Ids of events the current user leads that are not finished. */
function pymMyOpenEventIds($pdo) {
    ensureEventLeaderSchema($pdo);
    try {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $today = date('Y-m-d');
        $s = $pdo->prepare("SELECT e.id FROM event_leader_users l JOIN events e ON e.id = l.event_id WHERE l.user_id = ? AND e.event_date >= ?");
        $s->execute([$uid, $today]);
        $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        $s = $pdo->prepare("SELECT e.id FROM event_leader_youth l
            JOIN events e ON e.id = l.event_id
            JOIN youth_members y ON y.id = l.member_id
            JOIN users u ON u.status = 'approved' AND LOWER(TRIM(u.full_name)) = LOWER(TRIM(y.full_name))
            WHERE u.id = ? AND e.event_date >= ?");
        $s->execute([$uid, $today]);
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[] = (int)$id;
        return array_values(array_unique($ids));
    } catch (Throwable $e) { return []; }
}
