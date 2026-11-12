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

/* ---- Same Recycle Bin helpers as api.php (so Restore in the Recycle Bin works the same way) ---- */
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
function purgeYouth($pdo, $ids) {
    $ids = array_values(array_filter(array_map('intval', (array)$ids)));
    if (!$ids) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    foreach (['attendance', 'event_attendance', 'event_servers', 'coy_holders', 'coy_committee_members'] as $t) {
        try { $pdo->prepare("DELETE FROM `$t` WHERE member_id IN ($ph)")->execute($ids); } catch (Throwable $e) { error_log("purge $t: " . $e->getMessage()); }
    }
    $pdo->prepare("DELETE FROM youth_members WHERE id IN ($ph)")->execute($ids);
}

switch ($action) {

    // Youth members that belong to a user account (Slot Leader or Admin/Secretariat, matched by full name).
    // The Youth Members page hides their bulk-select checkbox; they are deleted in Users & Roles instead.
    case 'locked_ids': {
        $rows = $pdo->query("SELECT DISTINCT y.id FROM youth_members y
            JOIN users u ON LOWER(TRIM(u.full_name)) = LOWER(TRIM(y.full_name))")->fetchAll(PDO::FETCH_COLUMN);
        respond(true, '', array_map('intval', $rows));
    }

    // Moves many youth members (with ALL their attendance data) to the Recycle Bin, then removes them.
    case 'bulk_delete_youth': {
        $ids = $input['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) respond(false, 'Select at least one member to delete.');
        if (count($ids) > 500) respond(false, 'Too many members selected at once (max 500).');

        ensureTrash($pdo); // may create the table, so it runs before any transaction

        $lk = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(full_name)) = ? LIMIT 1");
        $done = []; $linked = []; $missing = 0; $failed = 0;

        foreach ($ids as $id) {
            $snap = snapshotYouth($pdo, $id);
            if (!$snap) { $missing++; continue; }
            $name = $snap['member']['full_name'];

            // Members that belong to a user account (leaders, secretariat) are deleted from Users & Roles instead
            $lk->execute([strtolower(trim($name))]);
            if ($lk->fetch()) { $linked[] = $name; continue; }

            try {
                $pdo->beginTransaction();
                trashSave($pdo, 'youth', $name, $snap);   // backup FIRST
                purgeYouth($pdo, [$id]);                  // only then remove
                $pdo->commit();
                $done[] = $name;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('bulk_delete_youth: ' . $e->getMessage());
                $failed++;
            }
        }

        if ($done) {
            logActivity($pdo, 'delete_youth', count($done) . ' member(s) (bulk)',
                'Moved to Recycle Bin: ' . implode('; ', array_slice($done, 0, 15)) . (count($done) > 15 ? '; +' . (count($done) - 15) . ' more' : ''));
        }

        if (!$done) {
            $why = $linked ? 'Leaders and admins cannot be bulk deleted. Delete them in Users & Roles instead.' : ($failed ? 'Failed to delete the selected members.' : 'The selected members were not found.');
            respond(false, $why);
        }
        $msg = count($done) . ' member(s) moved to the Recycle Bin (can be restored for 30 days).';
        if ($linked) $msg .= ' Skipped ' . count($linked) . ' leader/admin account(s) (delete them in Users & Roles): ' . implode(', ', array_slice($linked, 0, 5)) . (count($linked) > 5 ? '…' : '') . '.';
        if ($failed) $msg .= ' ' . $failed . ' failed.';
        respond(true, $msg, ['deleted' => count($done), 'skipped_linked' => count($linked), 'failed' => $failed]);
    }

    default:
        respond(false, 'Unknown action.');
}
