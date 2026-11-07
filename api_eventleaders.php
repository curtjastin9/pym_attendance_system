<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security.php';

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

function respond($success, $message = '', $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}
function nextId($pdo, $table) {
    return (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM `$table`")->fetchColumn() + 1;
}
if (!isset($_SESSION['user_id'])) respond(false, 'Your session has expired. Please log in again.');

if (empty($_SESSION['evl_ok'])) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `event_leaders` (
            `id` int(11) NOT NULL,
            `event_id` int(11) NOT NULL,
            `user_id` int(11) DEFAULT NULL,
            `member_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_el_event` (`event_id`),
            KEY `idx_el_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $_SESSION['evl_ok'] = 1;
    } catch (Throwable $e) { error_log('event leaders schema: ' . $e->getMessage()); }
}

$SEC = (($_SESSION['role'] ?? '') === 'secretariat');
function needSec() {
    global $SEC;
    if (!$SEC) respond(false, 'You do not have permission to perform this action.');
}
function ids($v) {
    if (!is_array($v)) return [];
    return array_values(array_unique(array_filter(array_map('intval', $v), fn($x) => $x > 0)));
}

switch ($action) {

    // Accounts + youth that can be picked as event leaders
    case 'options': {
        needSec();
        $users = $pdo->query("SELECT id, full_name, role FROM users WHERE status='approved' ORDER BY full_name ASC")->fetchAll();
        $youth = $pdo->query("SELECT id, full_name FROM youth_members WHERE status='active' ORDER BY full_name ASC")->fetchAll();
        respond(true, '', ['users' => $users, 'youth' => $youth]);
    }

    // Leaders of the given events: { event_id: [ {type,id,full_name} ] }
    case 'get': {
        needSec();
        $eids = ids($input['event_ids'] ?? []);
        if (!$eids) respond(true, '', new stdClass());
        $ph = implode(',', array_fill(0, count($eids), '?'));
        $q = $pdo->prepare("SELECT l.event_id, l.user_id, l.member_id, u.full_name AS uname, y.full_name AS yname
            FROM event_leaders l
            LEFT JOIN users u ON u.id = l.user_id
            LEFT JOIN youth_members y ON y.id = l.member_id
            WHERE l.event_id IN ($ph)");
        $q->execute($eids);
        $out = [];
        foreach ($q->fetchAll() as $r) {
            if ($r['user_id'] && $r['uname'] !== null) $out[(int)$r['event_id']][] = ['type' => 'user', 'id' => (int)$r['user_id'], 'full_name' => $r['uname']];
            elseif ($r['member_id'] && $r['yname'] !== null) $out[(int)$r['event_id']][] = ['type' => 'youth', 'id' => (int)$r['member_id'], 'full_name' => $r['yname']];
        }
        respond(true, '', $out ?: new stdClass());
    }

    // Replace the leaders of one event
    case 'set': {
        needSec();
        $eid = (int)($input['event_id'] ?? 0);
        if (!$eid) {
            $f = $pdo->prepare("SELECT id FROM events WHERE category_id = ? AND title = ? AND event_date = ? ORDER BY id DESC LIMIT 1");
            $f->execute([(int)($input['category_id'] ?? 0), trim((string)($input['title'] ?? '')), (string)($input['event_date'] ?? '')]);
            $eid = (int)$f->fetchColumn();
        }
        $chk = $pdo->prepare("SELECT title, event_date FROM events WHERE id = ? AND category_id IS NOT NULL");
        $chk->execute([$eid]);
        $ev = $chk->fetch();
        if (!$ev) respond(false, 'Event not found.');

        $uids = ids($input['user_ids'] ?? []);
        $mids = ids($input['member_ids'] ?? []);
        if ($uids) {
            $q = $pdo->prepare("SELECT id FROM users WHERE status='approved' AND id IN (" . implode(',', array_fill(0, count($uids), '?')) . ")");
            $q->execute($uids);
            $uids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($mids) {
            $q = $pdo->prepare("SELECT id FROM youth_members WHERE id IN (" . implode(',', array_fill(0, count($mids), '?')) . ")");
            $q->execute($mids);
            $mids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM event_leaders WHERE event_id = ?")->execute([$eid]);
            $nid = nextId($pdo, 'event_leaders');
            $ins = $pdo->prepare("INSERT INTO event_leaders (id, event_id, user_id, member_id) VALUES (?,?,?,?)");
            foreach ($uids as $u) $ins->execute([$nid++, $eid, $u, null]);
            foreach ($mids as $m) $ins->execute([$nid++, $eid, null, $m]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to save leaders: ' . $e->getMessage());
        }
        logActivity($pdo, 'event_leaders', $ev['title'] . ' · ' . $ev['event_date'], count($uids) . ' account leader(s), ' . count($mids) . ' youth leader(s)');
        respond(true, 'Leaders saved.');
    }

    // Events (this month) that the logged-in user leads and that are not finished yet
    case 'my_events': {
        if ($SEC) respond(true, '', []);
        $year  = (int)($input['year']  ?? date('Y'));
        $month = (int)($input['month'] ?? date('n'));
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $today = date('Y-m-d');
        try {
            $st = $pdo->prepare("SELECT e.id, e.title, e.event_date, e.event_time, e.location, c.name AS category,
                    (SELECT COUNT(*) FROM event_attendance a WHERE a.event_id = e.id AND a.status='present') AS present
                FROM events e JOIN event_categories c ON c.id = e.category_id
                WHERE e.category_id IS NOT NULL AND e.event_date BETWEEN ? AND ? AND e.event_date >= ?
                  AND EXISTS (SELECT 1 FROM event_leaders l LEFT JOIN youth_members y ON y.id = l.member_id
                              WHERE l.event_id = e.id AND (l.user_id = ? OR LOWER(TRIM(y.full_name)) = ?))
                ORDER BY e.event_date ASC, e.event_time ASC");
            $st->execute([$from, $to, $today, (int)$_SESSION['user_id'], strtolower(trim((string)($_SESSION['full_name'] ?? '')))]);
            $rows = $st->fetchAll();
        } catch (Throwable $e) { respond(true, '', []); }
        $total = (int)$pdo->query("SELECT COUNT(*) FROM youth_members WHERE status='active'")->fetchColumn();
        foreach ($rows as &$r) { $r['present'] = (int)$r['present']; $r['total'] = $total; }
        unset($r);
        respond(true, '', $rows);
    }

    default:
        respond(false, 'Unknown action.');
}
