<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security.php';
require_once 'eventleaders_lib.php';

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

function respond($success, $message = '', $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}
if (!isset($_SESSION['user_id'])) respond(false, 'Your session has expired. Please log in again.');
if (($_SESSION['role'] ?? '') !== 'secretariat') respond(false, 'You do not have permission to perform this action.');
ensureEventLeaderSchema($pdo);

switch ($action) {

    case 'users': {
        respond(true, '', $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'approved' ORDER BY full_name ASC")->fetchAll());
    }

    case 'get': {
        respond(true, '', pymEventLeaderIds($pdo, (int)($input['event_id'] ?? 0)));
    }

    case 'set': {
        $eid = (int)($input['event_id'] ?? 0);
        if (!$eid) {
            $q = $pdo->prepare("SELECT id FROM events WHERE category_id = ? AND title = ? AND event_date = ? ORDER BY id DESC LIMIT 1");
            $q->execute([(int)($input['category_id'] ?? 0), trim((string)($input['title'] ?? '')), (string)($input['event_date'] ?? '')]);
            $eid = (int)$q->fetchColumn();
        }
        if (!$eid) respond(false, 'Event not found.');
        $ids = $input['user_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM event_leader_users WHERE event_id = ?")->execute([$eid]);
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $v = $pdo->prepare("SELECT id FROM users WHERE status = 'approved' AND id IN ($ph)");
                $v->execute($ids);
                $valid = array_map('intval', $v->fetchAll(PDO::FETCH_COLUMN));
                $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM event_leader_users")->fetchColumn() + 1;
                $ins = $pdo->prepare("INSERT INTO event_leader_users (id, event_id, user_id) VALUES (?,?,?)");
                foreach ($valid as $u) $ins->execute([$nid++, $eid, $u]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to save event leaders: ' . $e->getMessage());
        }
        respond(true, 'Event leaders saved.');
    }

    default:
        respond(false, 'Unknown action.');
}
