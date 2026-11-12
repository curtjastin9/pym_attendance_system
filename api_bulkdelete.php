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

switch ($action) {

    // Deletes many category events at once, together with their attendance, servers and leaders.
    case 'bulk_delete_events': {
        $ids = $input['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) respond(false, 'Select at least one event to delete.');
        if (count($ids) > 500) respond(false, 'Too many events selected at once (max 500).');

        // Only real category events (never the fixed Normal Serve slots)
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT id, title, event_date FROM events WHERE category_id IS NOT NULL AND id IN ($ph)");
        $q->execute($ids);
        $found = $q->fetchAll();
        if (!$found) respond(false, 'The selected events were not found. They may already be deleted.');
        $valid = array_map(fn($r) => (int)$r['id'], $found);
        $vph = implode(',', array_fill(0, count($valid), '?'));

        ensureEventLeaderSchema($pdo); // may create tables, so it runs before the transaction

        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM event_attendance WHERE event_id IN ($vph)")->execute($valid);
            foreach ([
                "DELETE FROM event_servers WHERE event_id IN ($vph)",
                "DELETE FROM event_leader_users WHERE event_id IN ($vph)",
                "DELETE FROM event_leader_youth WHERE event_id IN ($vph)",
                "DELETE FROM event_slot_servers WHERE slot_id IN (SELECT id FROM event_slots WHERE event_id IN ($vph))",
                "DELETE FROM event_slots WHERE event_id IN ($vph)",
            ] as $sql) {
                try { $pdo->prepare($sql)->execute($valid); } catch (Throwable $e) { error_log('bulk delete extra: ' . $e->getMessage()); }
            }
            $pdo->prepare("DELETE FROM events WHERE id IN ($vph)")->execute($valid);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to delete events: ' . $e->getMessage());
        }

        $names = array_map(fn($r) => $r['title'] . ' · ' . $r['event_date'], array_slice($found, 0, 10));
        logActivity($pdo, 'delete_event', count($valid) . ' event(s) (bulk)',
            'Bulk deleted with their attendance: ' . implode('; ', $names) . (count($found) > 10 ? '; +' . (count($found) - 10) . ' more' : ''));
        respond(true, count($valid) . ' event(s) deleted.', ['deleted' => count($valid)]);
    }

    default:
        respond(false, 'Unknown action.');
}
