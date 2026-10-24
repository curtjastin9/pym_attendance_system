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
// Secretariat OR Commission On Youth officers (accounts with a position)
function requireEventMarker($pdo) {
    requireLogin();
    if (!isCoyOfficer($pdo)) respond(false, 'Only the Secretariat or Commission On Youth officers can mark special events.');
}
// null = secretariat, -1 = leader without slot, otherwise slot id
function leaderSlotId($pdo) { return pymLeaderSlot($pdo); }
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
function nextId($pdo, $table) {
    return (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM `$table`")->fetchColumn() + 1;
}

requireLogin();
ensureExtraSchema($pdo);
ensureEventServerSchema($pdo);

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
    return $slot;
}
function eventCounts() { return ['present' => 0, 'excused' => 0, 'absent' => 0]; }
function countLine($cnt, $n) {
    return 'Present ' . $cnt['present'] . ', Excused ' . $cnt['excused'] . ', Absent ' . $cnt['absent'] . ' — ' . $n . ' change(s): ';
}

switch ($action) {

    case 'get_slot_attendance': {
        $slot_id = (int)($input['slot_id'] ?? 0);
        $date    = $input['date'] ?? '';
        $slot = checkSlotAccess($pdo, $slot_id, $date);
        $hist = pymSlotHistory($pdo);
        $LSM = pymLeaderSlotMap($pdo);

        // Existing records of this session
        $rec = [];
        $rs = $pdo->prepare("SELECT member_id, status, time_in, reason FROM attendance WHERE slot_id = ? AND att_date = ?");
        $rs->execute([$slot_id, $date]);
        foreach ($rs->fetchAll() as $r) $rec[(int)$r['member_id']] = $r;

        // Present in another slot the same day
        $el = [];
        $q = $pdo->prepare("SELECT a.member_id, g.name FROM attendance a JOIN groups_slots g ON g.id = a.slot_id WHERE a.att_date = ? AND a.slot_id <> ? AND a.status = 'present'");
        $q->execute([$date, $slot_id]);
        foreach ($q->fetchAll() as $r) $el[(int)$r['member_id']] = $r['name'];

        $ys = $pdo->query("SELECT y.id, y.full_name, y.slot_id, y.status, g.name AS cur_slot FROM youth_members y LEFT JOIN groups_slots g ON g.id = y.slot_id")->fetchAll();
        $out = [];
        foreach ($ys as $m) {
            $mid = (int)$m['id'];
            $a = $rec[$mid] ?? null;
            // Roster = members who belonged to this slot ON THAT DATE (old attendance stays with the old slot)
            $home = (int)pymSlotOn($hist, $m, $date) === $slot_id || (($LSM[$mid] ?? 0) === $slot_id);
            if ($home && ($m['status'] === 'active' || $a)) {
                $out[] = ['member_id' => $mid, 'full_name' => $m['full_name'], 'slot_name' => $slot['name'],
                          'status' => $a['status'] ?? null, 'time_in' => $a['time_in'] ?? null, 'reason' => $a['reason'] ?? null,
                          'visitor' => 0, 'elsewhere' => $el[$mid] ?? null];
            } elseif ($a) {
                $out[] = ['member_id' => $mid, 'full_name' => $m['full_name'], 'slot_name' => $m['cur_slot'],
                          'status' => $a['status'], 'time_in' => $a['time_in'], 'reason' => $a['reason'],
                          'visitor' => 1, 'elsewhere' => null];
            }
        }
        usort($out, function ($x, $y) {
            return ($x['visitor'] <=> $y['visitor']) ?: strcasecmp($x['full_name'], $y['full_name']);
        });
        respond(true, '', $out);
    }

    case 'search_members': {
        $slot_id = (int)($input['slot_id'] ?? 0);
        $q = trim((string)($input['q'] ?? ''));
        if (!$slot_id) respond(false, 'Please select a service time first.');
        $mine = leaderSlotId($pdo);
        if ($mine !== null && (int)$mine !== $slot_id) respond(false, 'You do not have access to this slot.');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $s = $pdo->prepare("SELECT y.id, y.full_name, g.name AS slot_name FROM youth_members y LEFT JOIN groups_slots g ON g.id = y.slot_id
            WHERE y.status = 'active' AND (y.slot_id IS NULL OR y.slot_id <> ?) AND y.full_name LIKE ? ORDER BY y.full_name ASC LIMIT 40");
        $s->execute([$slot_id, $like]);
        respond(true, '', $s->fetchAll());
    }

    case 'save_slot_attendance': {
        $slot_id = (int)($input['slot_id'] ?? 0);
        $date    = $input['date'] ?? '';
        $records = $input['records'] ?? [];
        checkSlotAccess($pdo, $slot_id, $date);
        $hist = pymSlotHistory($pdo);
        $LSM = pymLeaderSlotMap($pdo);
        $names = [];
        if (!is_array($records)) $records = [];

        $chk  = $pdo->prepare("SELECT id, full_name, slot_id, status FROM youth_members WHERE id = ?");
        $clean = [];
        $seen = [];
        foreach ($records as $r) {
            [$mid, $status, $time, $reason] = cleanRecord($r);
            if (!$mid || isset($seen[$mid])) continue;
            $chk->execute([$mid]);
            $m = $chk->fetch();
            if (!$m) continue;
            $home = (int)pymSlotOn($hist, $m, $date) === $slot_id || (($LSM[$mid] ?? 0) === $slot_id);
            if (!$home && (empty($r['visitor']) || $m['status'] !== 'active')) continue;
            $seen[$mid] = true;
            $names[$mid] = $m['full_name'] . ($home ? '' : ' (volunteer)');
            if ($status === 'excused' && ($reason === null || $reason === '')) {
                respond(false, 'Please type the reason for ' . $m['full_name'] . ' (Excused).');
            }
            $clean[] = [$mid, $status, $time, $reason];
        }

        $old = [];
        $os = $pdo->prepare("SELECT member_id, status FROM attendance WHERE slot_id = ? AND att_date = ?");
        $os->execute([$slot_id, $date]);
        foreach ($os->fetchAll() as $o) $old[(int)$o['member_id']] = $o['status'];
        $chg = []; $cnt = eventCounts();
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
                if ($status === 'absent') $del->execute([$mid, $slot_id, $date]);   // absent = no row
                else $ins->execute([$id++, $mid, $slot_id, $date, $status, $time, $reason]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('save_slot_attendance: ' . $e->getMessage());
            respond(false, 'Failed to save attendance: ' . $e->getMessage());
        }

        // Mark this session as submitted (so "not submitted" alerts and follow-ups are accurate)
        try {
            $ex = $pdo->prepare("SELECT id FROM attendance_submissions WHERE slot_id = ? AND att_date = ?");
            $ex->execute([$slot_id, $date]);
            $sid = $ex->fetchColumn();
            if ($sid) $pdo->prepare("UPDATE attendance_submissions SET submitted_by = ?, submitted_at = ? WHERE id = ?")
                ->execute([(int)$_SESSION['user_id'], date('Y-m-d H:i:s'), $sid]);
            else $pdo->prepare("INSERT INTO attendance_submissions (id, slot_id, att_date, submitted_by, submitted_at) VALUES (?,?,?,?,?)")
                ->execute([nextId($pdo, 'attendance_submissions'), $slot_id, $date, (int)$_SESSION['user_id'], date('Y-m-d H:i:s')]);
        } catch (Throwable $e) { error_log('submission: ' . $e->getMessage()); }

        if ($chg) {
            $sn = $pdo->prepare("SELECT name FROM groups_slots WHERE id = ?"); $sn->execute([$slot_id]);
            logActivity($pdo, 'attendance', ($sn->fetchColumn() ?: 'Slot') . ' · ' . $date, countLine($cnt, count($chg)) . implode('; ', $chg));
        }
        respond(true, 'Attendance saved successfully.');
    }

    case 'get_event_att': {
        requireEventMarker($pdo);
        $event_id = (int)($input['event_id'] ?? 0);
        $s = $pdo->prepare("SELECT id FROM events WHERE id = ? AND category_id IS NOT NULL");
        $s->execute([$event_id]);
        if (!$s->fetch()) respond(false, 'Invalid event.');
        $stmt = $pdo->prepare("SELECT y.id AS member_id, y.full_name, g.name AS slot_name, a.status, a.time_in, a.reason,
                (CASE WHEN sv.id IS NULL THEN 0 ELSE 1 END) AS is_server
            FROM youth_members y
            LEFT JOIN groups_slots g ON g.id = y.slot_id
            LEFT JOIN event_attendance a ON a.member_id = y.id AND a.event_id = ?
            LEFT JOIN event_servers sv ON sv.member_id = y.id AND sv.event_id = ?
            WHERE (y.status = 'active' OR sv.id IS NOT NULL) ORDER BY is_server DESC, y.full_name ASC");
        $stmt->execute([$event_id, $event_id]);
        $erows = $stmt->fetchAll();
        pymApplyLeaderSlot($erows, pymLeaderLabels($pdo), 'member_id', 'slot_name');
        respond(true, '', $erows);
    }

    case 'save_event_att': {
        requireEventMarker($pdo);
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
        $chg = []; $cnt = eventCounts();
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
            logActivity($pdo, 'event_attendance', ($e ? $e['title'] . ' · ' . $e['event_date'] : 'Event #' . $event_id), countLine($cnt, count($clean) ? count($chg) : 0) . implode('; ', $chg));
        }
        respond(true, 'Attendance saved successfully.');
    }

    default:
        respond(false, 'Unknown action.');
}
