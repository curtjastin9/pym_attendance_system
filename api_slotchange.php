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

if (empty($_SESSION['sc_ok'])) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `slot_change_requests` (
            `id` int(11) NOT NULL,
            `member_id` int(11) NOT NULL,
            `from_slot` int(11) NOT NULL,
            `to_slot` int(11) NOT NULL,
            `swap_member_id` int(11) DEFAULT NULL,
            `requested_by` int(11) NOT NULL DEFAULT 0,
            `requested_name` varchar(100) DEFAULT NULL,
            `status` varchar(12) NOT NULL DEFAULT 'pending',
            `created_at` datetime NOT NULL,
            `decided_by` varchar(100) DEFAULT NULL,
            `decided_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_scr` (`status`,`to_slot`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        pymEnsureSlotHistory($pdo);
        $_SESSION['sc_ok'] = 1;
    } catch (Throwable $e) { error_log('sc schema: ' . $e->getMessage()); }
}

$ME  = pymLeaderSlot($pdo);   // null = secretariat, -1 = no slot, else slot id
$SEC = ($ME === null);
$UID = (int)$_SESSION['user_id'];

function canDecide($r) { global $SEC, $ME; return $SEC || ($ME > 0 && (int)$ME === (int)$r['to_slot']); }
function getReq($pdo, $id) {
    $s = $pdo->prepare("SELECT * FROM slot_change_requests WHERE id = ?");
    $s->execute([$id]);
    return $s->fetch();
}

switch ($action) {

    case 'options': {
        $slots = $pdo->query("SELECT id, name, dow, slot_time FROM groups_slots ORDER BY dow ASC, slot_time ASC")->fetchAll();
        $lead = [];
        foreach ($pdo->query("SELECT sl.slot_id, u.full_name FROM slot_leaders sl JOIN users u ON u.id = sl.user_id")->fetchAll() as $r) $lead[$r['slot_id']][] = $r['full_name'];
        foreach ($slots as &$s) $s['leaders'] = $lead[$s['id']] ?? [];
        unset($s);
        $mem = $pdo->query("SELECT id, full_name, slot_id FROM youth_members WHERE status='active' AND slot_id IS NOT NULL ORDER BY full_name ASC")->fetchAll();
        respond(true, '', ['slots' => $slots, 'members' => $mem]);
    }

    case 'create': {
        $mid  = (int)($input['member_id'] ?? 0);
        $to   = (int)($input['to_slot'] ?? 0);
        $swap = (int)($input['swap_member_id'] ?? 0);
        $s = $pdo->prepare("SELECT id, full_name, slot_id, status FROM youth_members WHERE id = ?");
        $s->execute([$mid]);
        $m = $s->fetch();
        if (!$m) respond(false, 'Member not found.');
        if ($m['status'] !== 'active') respond(false, 'Only active members can change slot.');
        if (!$m['slot_id']) respond(false, 'This member has no slot yet.');
        $from = (int)$m['slot_id'];
        if (!$SEC && (int)$ME !== $from) respond(false, 'You can only request a change for members of your own slot.');
        $g = $pdo->prepare("SELECT id FROM groups_slots WHERE id = ?");
        $g->execute([$to]);
        if (!$g->fetch()) respond(false, 'Please choose the new slot.');
        if ($to === $from) respond(false, 'The member is already in that slot.');
        if ($swap) {
            $s->execute([$swap]);
            $w = $s->fetch();
            if (!$w || $w['status'] !== 'active' || (int)$w['slot_id'] !== $to) respond(false, 'The swap member must be an active member of the new slot.');
        }
        $ids = $swap ? [$mid, $swap] : [$mid];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $d = $pdo->prepare("SELECT id FROM slot_change_requests WHERE status='pending' AND (member_id IN ($ph) OR swap_member_id IN ($ph)) LIMIT 1");
        $d->execute(array_merge($ids, $ids));
        if ($d->fetch()) respond(false, 'There is already a pending request for this member.');
        $pdo->prepare("INSERT INTO slot_change_requests (id, member_id, from_slot, to_slot, swap_member_id, requested_by, requested_name, status, created_at) VALUES (?,?,?,?,?,?,?,'pending',?)")
            ->execute([nextId($pdo, 'slot_change_requests'), $mid, $from, $to, $swap ?: null, $UID, $_SESSION['full_name'] ?? '', date('Y-m-d H:i:s')]);
        logActivity($pdo, 'slot_request', $m['full_name'], 'Requested slot change #' . $from . ' → #' . $to . ($swap ? ' (swap)' : ''));
        respond(true, 'Request sent to the leader of the new slot. It will apply once they confirm.');
    }

    case 'list': {
        $rows = $pdo->query("SELECT r.*, y.full_name AS member_name, s.full_name AS swap_name, gf.name AS from_name, gt.name AS to_name
            FROM slot_change_requests r
            JOIN youth_members y ON y.id = r.member_id
            LEFT JOIN youth_members s ON s.id = r.swap_member_id
            LEFT JOIN groups_slots gf ON gf.id = r.from_slot
            LEFT JOIN groups_slots gt ON gt.id = r.to_slot
            ORDER BY r.id DESC LIMIT 150")->fetchAll();
        $pending = []; $hist = [];
        foreach ($rows as $r) {
            $involved = $SEC || (int)$r['requested_by'] === $UID || ($ME > 0 && ((int)$ME === (int)$r['to_slot'] || (int)$ME === (int)$r['from_slot']));
            if (!$involved) continue;
            if ($r['status'] === 'pending') {
                $r['can_decide'] = canDecide($r) ? 1 : 0;
                $r['can_cancel'] = ($SEC || (int)$r['requested_by'] === $UID) ? 1 : 0;
                $pending[] = $r;
            } elseif (count($hist) < 8) $hist[] = $r;
        }
        respond(true, '', ['pending' => $pending, 'history' => $hist]);
    }

    case 'approve': {
        $r = getReq($pdo, (int)($input['id'] ?? 0));
        if (!$r || $r['status'] !== 'pending') respond(false, 'This request is no longer pending.');
        if (!canDecide($r)) respond(false, 'Only the leader of the new slot can confirm this request.');
        $mid = (int)$r['member_id']; $from = (int)$r['from_slot']; $to = (int)$r['to_slot']; $swap = (int)$r['swap_member_id'];
        $q = $pdo->prepare("SELECT slot_id FROM youth_members WHERE id = ?");
        $q->execute([$mid]);
        if ((int)$q->fetchColumn() !== $from) respond(false, 'The member is no longer in the original slot. Please decline this request.');
        if ($swap) {
            $q->execute([$swap]);
            if ((int)$q->fetchColumn() !== $to) respond(false, 'The swap member is no longer in the new slot. Please decline this request.');
        }
        pymEnsureSlotHistory($pdo);
        try {
            $pdo->beginTransaction();
            // History keeps every old attendance under the old slot; the new slot applies from today
            pymSlotChange($pdo, $mid, $from, $to);
            $pdo->prepare("UPDATE youth_members SET slot_id = ? WHERE id = ?")->execute([$to, $mid]);
            if ($swap) {
                pymSlotChange($pdo, $swap, $to, $from);
                $pdo->prepare("UPDATE youth_members SET slot_id = ? WHERE id = ?")->execute([$from, $swap]);
            }
            $pdo->prepare("UPDATE slot_change_requests SET status='approved', decided_by=?, decided_at=? WHERE id=?")
                ->execute([$_SESSION['full_name'] ?? '', date('Y-m-d H:i:s'), $r['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to change slot: ' . $e->getMessage());
        }
        logActivity($pdo, 'slot_change', 'Member #' . $mid, 'Slot #' . $from . ' → #' . $to . ($swap ? ' (swapped with #' . $swap . ')' : '') . ' · past attendance kept');
        respond(true, 'Confirmed. The slot was changed; past attendance stays in the old slot.');
    }

    case 'reject': {
        $r = getReq($pdo, (int)($input['id'] ?? 0));
        if (!$r || $r['status'] !== 'pending') respond(false, 'This request is no longer pending.');
        if (!canDecide($r)) respond(false, 'Only the leader of the new slot can decline this request.');
        $pdo->prepare("UPDATE slot_change_requests SET status='rejected', decided_by=?, decided_at=? WHERE id=?")
            ->execute([$_SESSION['full_name'] ?? '', date('Y-m-d H:i:s'), $r['id']]);
        respond(true, 'Request declined.');
    }

    case 'cancel': {
        $r = getReq($pdo, (int)($input['id'] ?? 0));
        if (!$r || $r['status'] !== 'pending') respond(false, 'This request is no longer pending.');
        if (!$SEC && (int)$r['requested_by'] !== $UID) respond(false, 'Only the requester can cancel.');
        $pdo->prepare("UPDATE slot_change_requests SET status='cancelled', decided_by=?, decided_at=? WHERE id=?")
            ->execute([$_SESSION['full_name'] ?? '', date('Y-m-d H:i:s'), $r['id']]);
        respond(true, 'Request cancelled.');
    }

    default:
        respond(false, 'Unknown action.');
}
