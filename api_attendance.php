<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'config.php';
require_once 'attendance_schema.php';

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
    $reason = trim((string)($r['reason'] ?? ''));
    if (function_exists('mb_substr')) $reason = mb_substr($reason, 0, 255); else $reason = substr($reason, 0, 255);
    if ($status === 'present' && $time === null) $time = date('H:i:s');
    if ($status !== 'present') $time = null;
    if ($status !== 'excused') $reason = null;
    return [(int)($r['member_id'] ?? 0), $status, $time, $reason];
}

requireLogin();
ensureExcusedSchema($pdo);
require_once 'security.php';
ensureSecuritySchema($pdo);

function checkSlotAccess($pdo, $slot_id, $date) {
    if (!$slot_id || !$date) respond(false, 'Please select a slot and date.');
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
        if (!is_array($records)) $records = [];

        $chk  = $pdo->prepare("SELECT full_name, slot_id FROM youth_members WHERE id = ?");
        $clean = [];
        foreach ($records as $r) {
            [$mid, $status, $time, $reason] = cleanRecord($r);
            $chk->execute([$mid]);
            $m = $chk->fetch();
            if (!$m || (int)$m['slot_id'] !== $slot_id) continue;
            if ($status === 'excused' && ($reason === null || $reason === '')) {
                respond(false, 'Please type the reason for ' . $m['full_name'] . ' (Excused).');
            }
            $clean[] = [$mid, $status, $time, $reason];
        }

        $ins = $pdo->prepare("INSERT INTO attendance (member_id, slot_id, att_date, status, time_in, reason) VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in), reason = VALUES(reason)");
        try {
            $pdo->beginTransaction();
            foreach ($clean as [$mid, $status, $time, $reason]) $ins->execute([$mid, $slot_id, $date, $status, $time, $reason]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to save attendance. Please try again.');
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

        $ins = $pdo->prepare("INSERT INTO event_attendance (member_id, event_id, status, time_in, reason) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in), reason = VALUES(reason)");
        try {
            $pdo->beginTransaction();
            foreach ($clean as [$mid, $status, $time, $reason]) $ins->execute([$mid, $event_id, $status, $time, $reason]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to save attendance. Please try again.');
        }
        respond(true, 'Attendance saved successfully.');
    }

    default:
        respond(false, 'Unknown action.');
}
