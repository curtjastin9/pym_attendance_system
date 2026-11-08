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

    // Accounts that can be picked as event leaders (secretariat is never limited, so it is not listed)
    case 'users': {
        respond(true, '', $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'approved' AND role <> 'secretariat' ORDER BY full_name ASC")->fetchAll());
    }

    // Leaders of one event: account ids + youth ids
    case 'get': {
        $eid = (int)($input['event_id'] ?? 0);
        respond(true, '', ['user_ids' => pymEventLeaderIds($pdo, $eid), 'youth_ids' => pymEventLeaderYouthIds($pdo, $eid)]);
    }

    case 'set': {
        $eid = (int)($input['event_id'] ?? 0);
        if (!$eid) respond(false, 'Event not found.');
        try {
            $pdo->beginTransaction();
            pymSaveEventLeaders($pdo, $eid, $input['user_ids'] ?? [], $input['youth_ids'] ?? []);
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
