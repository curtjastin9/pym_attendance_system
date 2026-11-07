<?php
/* Event leaders helpers. Include-only file. */
function ensureEventLeaderSchema($pdo) {
    if (!empty($_SESSION['evlead_ok'])) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `event_leader_users` (
            `id` int(11) NOT NULL,
            `event_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_elu` (`event_id`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['evlead_ok'] = 1;
    } catch (Throwable $e) { error_log('event leaders schema: ' . $e->getMessage()); }
}

/** User ids that lead an event. */
function pymEventLeaderIds($pdo, $eventId) {
    ensureEventLeaderSchema($pdo);
    try {
        $s = $pdo->prepare("SELECT user_id FROM event_leader_users WHERE event_id = ?");
        $s->execute([(int)$eventId]);
        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { return []; }
}

/** 'open' = current user leads it and it is not done yet, 'done' = leads it but it is finished, 'no' = not a leader. */
function pymEventLeaderState($pdo, $eventId) {
    ensureEventLeaderSchema($pdo);
    try {
        $s = $pdo->prepare("SELECT e.event_date FROM event_leader_users l JOIN events e ON e.id = l.event_id WHERE l.event_id = ? AND l.user_id = ? LIMIT 1");
        $s->execute([(int)$eventId, (int)($_SESSION['user_id'] ?? 0)]);
        $d = $s->fetchColumn();
        if (!$d) return 'no';
        return (substr((string)$d, 0, 10) >= date('Y-m-d')) ? 'open' : 'done';
    } catch (Throwable $e) { return 'no'; }
}

/** Ids of events the current user leads that are not finished. */
function pymMyOpenEventIds($pdo) {
    ensureEventLeaderSchema($pdo);
    try {
        $s = $pdo->prepare("SELECT e.id FROM event_leader_users l JOIN events e ON e.id = l.event_id WHERE l.user_id = ? AND e.event_date >= ?");
        $s->execute([(int)($_SESSION['user_id'] ?? 0), date('Y-m-d')]);
        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { return []; }
}
