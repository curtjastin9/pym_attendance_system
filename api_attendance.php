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
function requireLogin() {
    if (!isset($_SESSION['user_id'])) respond(false, 'Your session has expired. Please log in again.');
}
function requireSecretariat() {
    requireLogin();
    if ($_SESSION['role'] !== 'secretariat') respond(false, 'You do not have permission to perform this action.');
}
// null = secretariat, -1 = leader without slot, otherwise slot id
function leaderSlotId($pdo) {
    if ($_SESSION['role'] === 'secretariat') return null;
    $s = $pdo->prepare("SELECT slot_id FROM slot_leaders WHERE user_id = ? LIMIT 1");
    $s->execute([$_SESSION['user_id']]);
    $r = $s->fetch();
    if ($r && $r['slot_id']) return (int)$r['slot_id'];
    $s = $pdo->prepare("SELECT slot_id FROM users WHERE id = ?");
    $s->execute([$_SESSION['user_id']]);
    $r = $s->fetch();
    if ($r && $r['slot_id'] !== null) return (int)$r['slot_id'];
    return -1;
}
function cleanRecord($r) {
    $status = $r['status'] ?? 'absent';
    if (!in_array($status, ['present', 'absent', 'excused'], true)) $status = 'absent';
    $time = $r['time_in'] ?? null;
    if ($time === '') $time = null;
    if ($time !== null) $time = substr((string)$time, 0, 5) . ':00';
    $reason = trim((string)($r['reason'] ?? ''));
    if (function_exists('mb_substr')) $reason = mb_substr($reason, 0, 255); else $reason = substr($reason, 0, 255);
    if ($status === 'present' && $time === null) $time = date('H:i:s');
    if ($status !== 'present') $time = null;
    if ($status !== 'excused') $reason = null;
    return [(int)($r['member_id'] ?? 0), $status, $time, $reason];
}
// Works even when the table has no AUTO_INCREMENT on id (TiDB fix)
function nextId($pdo, $table) {
    return (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM `$table`")->fetchColumn() + 1;
}

requireLogin();

function checkSlotAccess($pdo, $slot_id, $date) {
    if (!$slot_id || !$date) respond(false, 'Please select a slot and date.');
    if ($date < '2026-10-01') respond(false, 'Attendance starts on October 2026.');
    $s = $pdo->prepare("SELECT * FROM groups_slots WHERE id = ?");
    $s->execute([$slot_id]);
    $slot = $s->fetch();
    if (!$slot) respond(false, 'Invalid slot.');
    if ((int)$slot['dow'] !== (int)date('w', strtotime($date))) respond(false, 'This slot is not scheduled on the selected date.');
    $mine = leaderSlotId($pdo);
    if ($mine !== null && (int)$mine !== (int)$slot_id) respond(false, 'You do not have access to mark attendance for this slot.');
}

switch ($action) {

    case 'get_slot_attendance': {
        $slot_id = (int)($input['slot_id'] ?? 0);
        $date    = $input['date'] ?? '';
        checkSlotAccess($pdo, $slot_id, $date);
        $stmt = $pdo->prepare("SELECT y.id AS member_id, y.full_name, g.name AS slot_name, a.status, a.time_in, a.reason
            FROM youth_members y
            LEFT JOIN groups_slots g ON g.id = y.slot_id
            LEFT JOIN attendance a ON a.member_id = y.id AND a.slot_id = ? AND a.att_date = ?
            WHERE y.status = 'active' AND y.slot_id = ? ORDER BY y.full_name ASC");
        $stmt->execute([$slot_id, $date, $slot_id]);
        respond(true, '', $stmt->fetchAll());
    }

    case 'save_slot_attendance': {
        $slot_id = (int)($input['slot_id'] ?? 0);
        $date    = $input['date'] ?? '';
        $records = $input['records'] ?? [];
        checkSlotAccess($pdo, $slot_id, $date);
        if (leaderSlotId($pdo) !== null) {
            if ($date < date('Y-m-d', strtotime('-6 day')) || $date > date('Y-m-d'))
                respond(false, 'Slot leaders can only mark attendance for the last 7 days. Please ask the Secretariat to make this change.');
        }
        $names = [];
        if (!is_array($records)) $records = [];

        $chk  = $pdo->prepare("SELECT full_name, slot_id FROM youth_members WHERE id = ?");
        $clean = [];
        foreach ($records as $r) {
            [$mid, $status, $time, $reason] = cleanRecord($r);
            $chk->execute([$mid]);
            $m = $chk->fetch();
            if (!$m || (int)$m['slot_id'] !== $slot_id) continue;
            $names[$mid] = $m['full_name'];
            if ($status === 'excused' && ($reason === null || $reason === '')) {
                respond(false, 'Please type the reason for ' . $m['full_name'] . ' (Excused).');
            }
            $clean[] = [$mid, $status, $time, $reason];
        }

        $old = [];
        $os = $pdo->prepare("SELECT member_id, status FROM attendance WHERE slot_id = ? AND att_date = ?");
        $os->execute([$slot_id, $date]);
        foreach ($os->fetchAll() as $o) $old[(int)$o['member_id']] = $o['status'];
        $chg = []; $cnt = ['present' => 0, 'excused' => 0, 'absent' => 0];
        foreach ($clean as [$mid, $status]) {
            $cnt[$status]++;
            $was = $old[$mid] ?? 'absent';
            if ($was !== $status) $chg[] = ($names[$mid] ?? $mid) . ': ' . $was . ' → ' . $status;
        }
        $ins = $pdo->prepare("INSERT INTO attendance (id, member_id, slot_id, att_date, status, time_in, reason) VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in), reason = VALUES(reason)");
        try {
            $pdo->beginTransaction();
            $id = nextId($pdo, 'attendance');
            $del = $pdo->prepare("DELETE FROM attendance WHERE member_id = ? AND slot_id = ? AND att_date = ?");
            foreach ($clean as [$mid, $status, $time, $reason]) {
                if ($status === 'absent') $del->execute([$mid, $slot_id, $date]);   // absent = no row (keeps the database small)
                else $ins->execute([$id++, $mid, $slot_id, $date, $status, $time, $reason]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('save_slot_attendance: ' . $e->getMessage());
            respond(false, 'Failed to save attendance: ' . $e->getMessage());
        }
        if ($chg) {
            $sn = $pdo->prepare("SELECT name FROM groups_slots WHERE id = ?"); $sn->execute([$slot_id]);
            logActivity($pdo, 'attendance', ($sn->fetchColumn() ?: 'Slot') . ' · ' . $date,
                'Present ' . $cnt['present'] . ', Excused ' . $cnt['excused'] . ', Absent ' . $cnt['absent'] . ' — ' . count($chg) . ' change(s): ' . implode('; ', $chg));
        }
        respond(true, 'Attendance saved successfully.');
    }

    case 'get_event_att': {
        requireSecretariat();
        $event_id = (int)($input['event_id'] ?? 0);
        $s = $pdo->prepare("SELECT id FROM events WHERE id = ? AND category_id IS NOT NULL");
        $s->execute([$event_id]);
        if (!$s->fetch()) respond(false, 'Invalid event.');
        $stmt = $pdo->prepare("SELECT y.id AS member_id, y.full_name, g.name AS slot_name, a.status, a.time_in, a.reason
            FROM youth_members y
            LEFT JOIN groups_slots g ON g.id = y.slot_id
            LEFT JOIN event_attendance a ON a.member_id = y.id AND a.event_id = ?
            WHERE y.status = 'active' ORDER BY y.full_name ASC");
        $stmt->execute([$event_id]);
        respond(true, '', $stmt->fetchAll());
    }

    case 'save_event_att': {
        requireSecretariat();
        $event_id = (int)($input['event_id'] ?? 0);
        $records  = $input['records'] ?? [];
        if (!is_array($records)) $records = [];
        $s = $pdo->prepare("SELECT id FROM events WHERE id = ? AND category_id IS NOT NULL");
        $s->execute([$event_id]);
        if (!$s->fetch()) respond(false, 'Invalid event.');

        $nm = $pdo->prepare("SELECT full_name FROM youth_members WHERE id = ?");
        $clean = [];
        foreach ($records as $r) {
            [$mid, $status, $time, $reason] = cleanRecord($r);
            if (!$mid) continue;
            if ($status === 'excused' && ($reason === null || $reason === '')) {
                $nm->execute([$mid]);
                $n = $nm->fetch();
                respond(false, 'Please type the reason for ' . ($n['full_name'] ?? 'the member') . ' (Excused).');
            }
            $clean[] = [$mid, $status, $time, $reason];
        }

        $nmap = [];
        if ($clean) {
            $ids = array_map(fn($c) => (int)$c[0], $clean);
            $q = $pdo->prepare("SELECT id, full_name FROM youth_members WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $q->execute($ids);
            foreach ($q->fetchAll() as $r) $nmap[(int)$r['id']] = $r['full_name'];
        }
        $old = [];
        $os = $pdo->prepare("SELECT member_id, status FROM event_attendance WHERE event_id = ?");
        $os->execute([$event_id]);
        foreach ($os->fetchAll() as $o) $old[(int)$o['member_id']] = $o['status'];
        $chg = []; $cnt = ['present' => 0, 'excused' => 0, 'absent' => 0];
        foreach ($clean as [$mid, $status]) {
            $cnt[$status]++;
            $was = $old[$mid] ?? 'absent';
            if ($was !== $status) $chg[] = ($nmap[$mid] ?? $mid) . ': ' . $was . ' → ' . $status;
        }
        $ins = $pdo->prepare("INSERT INTO event_attendance (id, member_id, event_id, status, time_in, reason) VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in), reason = VALUES(reason)");
        try {
            $pdo->beginTransaction();
            $id = nextId($pdo, 'event_attendance');
            $del = $pdo->prepare("DELETE FROM event_attendance WHERE member_id = ? AND event_id = ?");
            foreach ($clean as [$mid, $status, $time, $reason]) {
                if ($status === 'absent') $del->execute([$mid, $event_id]);
                else $ins->execute([$id++, $mid, $event_id, $status, $time, $reason]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('save_event_att: ' . $e->getMessage());
            respond(false, 'Failed to save attendance: ' . $e->getMessage());
        }
        if ($chg) {
            $en = $pdo->prepare("SELECT title, event_date FROM events WHERE id = ?"); $en->execute([$event_id]);
            $e = $en->fetch();
            logActivity($pdo, 'event_attendance', ($e ? $e['title'] . ' · ' . $e['event_date'] : 'Event #' . $event_id),
                'Present ' . $cnt['present'] . ', Excused ' . $cnt['excused'] . ', Absent ' . $cnt['absent'] . ' — ' . count($chg) . ' change(s): ' . implode('; ', $chg));
        }
        respond(true, 'Attendance saved successfully.');
    }

    default:
        respond(false, 'Unknown action.');
}
