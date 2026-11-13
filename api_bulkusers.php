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
if (($_SESSION['role'] ?? '') !== 'secretariat') respond(false, 'You do not have permission to perform this action.');

/* ---- Same Recycle Bin helpers as api.php / api_bulkyouth.php (so Restore keeps working) ---- */
function ensureTrash($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `trash` (
        `id` int(11) NOT NULL, `kind` varchar(10) NOT NULL, `label` varchar(150) NOT NULL,
        `payload` mediumtext NOT NULL, `deleted_by` varchar(100) DEFAULT NULL, `deleted_at` datetime NOT NULL,
        PRIMARY KEY (`id`), KEY `idx_del` (`deleted_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->prepare("DELETE FROM trash WHERE deleted_at < ?")->execute([date('Y-m-d H:i:s', time() - 86400 * 30)]);
}
function trashSave($pdo, $kind, $label, $payload) {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) throw new RuntimeException('Could not create backup.');
    $id = nextId($pdo, 'trash');
    $pdo->prepare("INSERT INTO trash (id, kind, label, payload, deleted_by, deleted_at) VALUES (?,?,?,?,?,?)")
        ->execute([$id, $kind, substr($label, 0, 150), $json, $_SESSION['full_name'] ?? '', date('Y-m-d H:i:s')]);
}
function snapshotYouth($pdo, $id) {
    $s = $pdo->prepare("SELECT * FROM youth_members WHERE id = ?"); $s->execute([$id]);
    $m = $s->fetch();
    if (!$m) return null;
    $out = ['member' => $m];
    foreach (['attendance', 'event_attendance', 'coy_holders', 'coy_committee_members'] as $t) {
        try { $q = $pdo->prepare("SELECT * FROM `$t` WHERE member_id = ?"); $q->execute([$id]); $out[$t] = $q->fetchAll(); }
        catch (Throwable $e) { $out[$t] = []; }
    }
    return $out;
}
/** Deletes youth members and EVERYTHING that points to them. */
function purgeYouth($pdo, $ids) {
    $ids = array_values(array_filter(array_map('intval', (array)$ids)));
    if (!$ids) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    foreach (['attendance', 'event_attendance', 'event_servers', 'event_slot_servers', 'event_leader_youth',
              'member_slot_history', 'coy_holders', 'coy_committee_members'] as $t) {
        try { $pdo->prepare("DELETE FROM `$t` WHERE member_id IN ($ph)")->execute($ids); } catch (Throwable $e) { error_log("purge $t: " . $e->getMessage()); }
    }
    try { $pdo->prepare("DELETE FROM slot_change_requests WHERE member_id IN ($ph) OR swap_member_id IN ($ph)")->execute(array_merge($ids, $ids)); }
    catch (Throwable $e) { error_log('purge slot_change_requests: ' . $e->getMessage()); }
    $pdo->prepare("DELETE FROM youth_members WHERE id IN ($ph)")->execute($ids);
}

switch ($action) {

    // Deletes one or many user accounts together with ALL their data:
    // the linked youth record (same full name) and every attendance / event / leader / slot record.
    // A backup goes to the Recycle Bin first (restorable for 30 days).
    case 'bulk_delete_users': {
        $ids = $input['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) respond(false, 'Select at least one account to delete.');
        if (count($ids) > 200) respond(false, 'Too many accounts selected at once (max 200).');

        ensureTrash($pdo); // may create the table, so it runs before any transaction

        $me = (int)$_SESSION['user_id'];
        $done = []; $skipSelf = false; $missing = 0; $failed = 0; $yDeleted = 0;

        $getU  = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $getY  = $pdo->prepare("SELECT id FROM youth_members WHERE LOWER(TRIM(full_name)) = ?");
        $getSL = $pdo->prepare("SELECT * FROM slot_leaders WHERE user_id = ?");

        foreach ($ids as $id) {
            if ($id === $me) { $skipSelf = true; continue; }   // you can never delete your own account
            $getU->execute([$id]);
            $u = $getU->fetch();
            if (!$u) { $missing++; continue; }

            $getY->execute([strtolower(trim($u['full_name']))]);
            $yids = array_map('intval', $getY->fetchAll(PDO::FETCH_COLUMN));
            $getSL->execute([$id]);
            $snap = [
                'user'         => $u,
                'slot_leaders' => $getSL->fetchAll(),
                'youth'        => array_values(array_filter(array_map(fn($y) => snapshotYouth($pdo, (int)$y), $yids))),
            ];

            try {
                $pdo->beginTransaction();
                trashSave($pdo, 'user', $u['full_name'], $snap);          // backup FIRST

                // everything tied to the account
                $pdo->prepare("DELETE FROM slot_leaders WHERE user_id = ?")->execute([$id]);
                $pdo->prepare("UPDATE groups_slots SET leader_id = NULL WHERE leader_id = ?")->execute([$id]);
                foreach ([
                    ["DELETE FROM event_leader_users WHERE user_id = ?", [$id]],
                    ["DELETE FROM slot_change_requests WHERE requested_by = ?", [$id]],
                ] as [$sql, $p]) {
                    try { $pdo->prepare($sql)->execute($p); } catch (Throwable $e) { error_log('user delete extra: ' . $e->getMessage()); }
                }
                if (!empty($u['email_key'])) {
                    try { $pdo->prepare("DELETE FROM email_codes WHERE email_key = ?")->execute([$u['email_key']]); } catch (Throwable $e) {}
                }
                try { $pdo->prepare("DELETE FROM rate_limits WHERE bucket = 'login' AND rkey LIKE ?")->execute(['%|' . strtolower($u['username'])]); } catch (Throwable $e) {}
                try { $pdo->prepare("DELETE FROM php_sessions WHERE data LIKE ?")->execute(['%user_id|i:' . $id . ';%']); } catch (Throwable $e) {}

                // the linked youth record and ALL its attendance
                purgeYouth($pdo, $yids);

                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                $pdo->commit();
                $done[] = $u['full_name'];
                $yDeleted += count($yids);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('bulk_delete_users: ' . $e->getMessage());
                $failed++;
            }
        }

        if ($done) {
            logActivity($pdo, 'delete_user', count($done) . ' account(s)' . (count($done) > 1 ? ' (bulk)' : ''),
                'Moved to Recycle Bin with their youth record and all attendance: ' . implode('; ', array_slice($done, 0, 15)) . (count($done) > 15 ? '; +' . (count($done) - 15) . ' more' : ''));
        }

        if (!$done) {
            if ($skipSelf && count($ids) === 1) respond(false, 'You cannot delete your own account.');
            respond(false, $failed ? 'Failed to delete the selected accounts. Please try again.' : 'The selected accounts were not found.');
        }
        $msg = count($done) . ' account(s) deleted with all their data (' . $yDeleted . ' youth record(s) and their attendance). Moved to the Recycle Bin for 30 days.';
        if ($skipSelf) $msg .= ' Your own account was skipped.';
        if ($missing)  $msg .= ' ' . $missing . ' not found.';
        if ($failed)   $msg .= ' ' . $failed . ' failed.';
        respond(true, $msg, ['deleted' => count($done), 'failed' => $failed]);
    }

    default:
        respond(false, 'Unknown action.');
}
