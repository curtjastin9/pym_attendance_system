<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'config.php';

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

// null = secretariat (no filter), -1 = leader without slot, otherwise slot id
function leaderSlotId($pdo) {
    if ($_SESSION['role'] === 'secretariat') return null;
    $stmt = $pdo->prepare("SELECT slot_id FROM slot_leaders WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row && $row['slot_id']) return (int)$row['slot_id'];
    $stmt = $pdo->prepare("SELECT slot_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row && $row['slot_id'] !== null) return (int)$row['slot_id'];
    return -1;
}

function nextId($pdo, $table) {
    return (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM `$table`")->fetchColumn() + 1;
}

function tableExists($pdo, $t) {
    return (bool)$pdo->query("SHOW TABLES LIKE '$t'")->fetch();
}

function ensureCoy($pdo) {
    if (!tableExists($pdo, 'coy_positions')) {
        $pdo->exec("CREATE TABLE `coy_positions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `sort_order` int(11) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $ins = $pdo->prepare("INSERT INTO coy_positions (id, name, sort_order) VALUES (?, ?, ?)");
        $defaults = ['Coordinator','Deputy','Finance Head','Finance Assistant Head','Secretary Head','Secretary Assistant Head','Module Head','Module Assistant Head','Animation Head','Animation Assistant Head','Creatives'];
        foreach ($defaults as $i => $n) $ins->execute([$i + 1, $n, $i + 1]);
    }
    if (!tableExists($pdo, 'coy_committees')) {
        $pdo->exec("CREATE TABLE `coy_committees` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `sort_order` int(11) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $ins = $pdo->prepare("INSERT INTO coy_committees (id, name, sort_order) VALUES (?, ?, ?)");
        foreach (['Secretary','Module','Creatives','Finance','Animation'] as $i => $n) $ins->execute([$i + 1, $n, $i + 1]);
    }
    if (!tableExists($pdo, 'coy_holders')) {
        $pdo->exec("CREATE TABLE `coy_holders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `position_id` int(11) NOT NULL,
          `member_id` int(11) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_pos_member` (`position_id`,`member_id`),
          CONSTRAINT `ch_pos_fk` FOREIGN KEY (`position_id`) REFERENCES `coy_positions` (`id`) ON DELETE CASCADE,
          CONSTRAINT `ch_mem_fk` FOREIGN KEY (`member_id`) REFERENCES `youth_members` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    if (!tableExists($pdo, 'coy_committee_members')) {
        $pdo->exec("CREATE TABLE `coy_committee_members` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `committee_id` int(11) NOT NULL,
          `member_id` int(11) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_com_member` (`committee_id`,`member_id`),
          CONSTRAINT `cc_com_fk` FOREIGN KEY (`committee_id`) REFERENCES `coy_committees` (`id`) ON DELETE CASCADE,
          CONSTRAINT `cc_mem_fk` FOREIGN KEY (`member_id`) REFERENCES `youth_members` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}

function ensureUserPositionColumn($pdo) {
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'coy_position_id'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN coy_position_id int(11) DEFAULT NULL");
    }
}

function coyMemberIds($pdo) {
    $ids = $pdo->query("SELECT member_id FROM coy_holders UNION SELECT member_id FROM coy_committee_members")->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $ids);
}

// Unassigned, active youth that are NOT slot leaders and NOT Commission On Youth
function availableYouth($pdo) {
    $leaderNames = $pdo->query("SELECT LOWER(TRIM(u.full_name)) FROM slot_leaders sl JOIN users u ON u.id = sl.user_id")->fetchAll(PDO::FETCH_COLUMN);
    $coyIds = coyMemberIds($pdo);
    $rows = $pdo->query("SELECT id, full_name FROM youth_members WHERE status='active' AND slot_id IS NULL ORDER BY full_name ASC")->fetchAll();
    return array_values(array_filter($rows, function ($r) use ($leaderNames, $coyIds) {
        if (in_array((int)$r['id'], $coyIds, true)) return false;
        if (in_array(strtolower(trim($r['full_name'])), $leaderNames, true)) return false;
        return true;
    }));
}

function youthExists($pdo, $id) {
    $s = $pdo->prepare("SELECT id FROM youth_members WHERE id = ?");
    $s->execute([$id]);
    return (bool)$s->fetch();
}

ensureCoy($pdo);
ensureUserPositionColumn($pdo);

switch ($action) {

    case 'get_user_positions': {
        requireSecretariat();
        $rows = $pdo->query("SELECT u.id, u.role, u.coy_position_id AS position_id, p.name AS position_name, g.name AS slot_name FROM users u LEFT JOIN coy_positions p ON p.id = u.coy_position_id LEFT JOIN slot_leaders sl ON sl.user_id = u.id LEFT JOIN groups_slots g ON g.id = sl.slot_id")->fetchAll();
        respond(true, '', $rows);
    }

    case 'set_user_position': {
        requireSecretariat();
        $uid = (int)($input['user_id'] ?? 0);
        if (!$uid && !empty($input['username'])) {
            $s = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $s->execute([trim($input['username'])]);
            $r = $s->fetch();
            $uid = $r ? (int)$r['id'] : 0;
        }
        if (!$uid) respond(false, 'User not found.');
        $pos = $input['position_id'] ?? null;
        if ($pos === '' || $pos === 0 || $pos === '0') $pos = null;
        if ($pos !== null) {
            $s = $pdo->prepare("SELECT id FROM coy_positions WHERE id = ?");
            $s->execute([$pos]);
            if (!$s->fetch()) respond(false, 'Invalid position.');
        }
        $pdo->prepare("UPDATE users SET coy_position_id = ? WHERE id = ?")->execute([$pos, $uid]);
        respond(true, 'Position saved.');
    }

    case 'get_my_position': {
        requireLogin();
        $s = $pdo->prepare("SELECT p.id, p.name FROM users u JOIN coy_positions p ON p.id = u.coy_position_id WHERE u.id = ?");
        $s->execute([$_SESSION['user_id']]);
        respond(true, '', $s->fetch() ?: null);
    }

    case 'get_coy': {
        requireLogin();
        $positions = $pdo->query("SELECT * FROM coy_positions ORDER BY sort_order ASC, id ASC")->fetchAll();
        $holders = $pdo->query("SELECT h.id, h.position_id, h.member_id, y.full_name FROM coy_holders h JOIN youth_members y ON y.id = h.member_id ORDER BY y.full_name ASC")->fetchAll();
        $byPos = [];
        foreach ($holders as $h) $byPos[$h['position_id']][] = ['id'=>(int)$h['id'],'member_id'=>(int)$h['member_id'],'full_name'=>$h['full_name']];
        $accs = $pdo->query("SELECT id, full_name, coy_position_id FROM users WHERE coy_position_id IS NOT NULL AND status='approved' ORDER BY full_name ASC")->fetchAll();
        $accBy = [];
        foreach ($accs as $a) $accBy[$a['coy_position_id']][] = ['id'=>(int)$a['id'],'full_name'=>$a['full_name']];
        foreach ($positions as &$p) { $p['holders'] = $byPos[$p['id']] ?? []; $p['accounts'] = $accBy[$p['id']] ?? []; }
        unset($p);

        $coms = $pdo->query("SELECT * FROM coy_committees ORDER BY sort_order ASC, id ASC")->fetchAll();
        $cm = $pdo->query("SELECT c.id, c.committee_id, c.member_id, y.full_name FROM coy_committee_members c JOIN youth_members y ON y.id = c.member_id ORDER BY y.full_name ASC")->fetchAll();
        $byCom = [];
        foreach ($cm as $m) $byCom[$m['committee_id']][] = ['id'=>(int)$m['id'],'member_id'=>(int)$m['member_id'],'full_name'=>$m['full_name']];
        foreach ($coms as &$c) $c['members'] = $byCom[$c['id']] ?? [];
        unset($c);

        respond(true, '', ['positions' => $positions, 'committees' => $coms]);
    }

    case 'get_all_youth': {
        requireSecretariat();
        respond(true, '', $pdo->query("SELECT id, full_name FROM youth_members WHERE status='active' ORDER BY full_name ASC")->fetchAll());
    }

    case 'save_coy_position': {
        requireSecretariat();
        $id = $input['id'] ?? '';
        $name = trim($input['name'] ?? '');
        if ($name === '') respond(false, 'Position name is required.');
        if ($id) {
            $pdo->prepare("UPDATE coy_positions SET name = ? WHERE id = ?")->execute([$name, $id]);
            respond(true, 'Position updated.');
        }
        $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) m FROM coy_positions")->fetch()['m'];
        $pdo->prepare("INSERT INTO coy_positions (id, name, sort_order) VALUES (?, ?, ?)")->execute([nextId($pdo, 'coy_positions'), $name, $max + 1]);
        respond(true, 'Position added.');
    }

    case 'delete_coy_position': {
        requireSecretariat();
        $pdo->prepare("UPDATE users SET coy_position_id = NULL WHERE coy_position_id = ?")->execute([$input['id'] ?? 0]);
        $pdo->prepare("DELETE FROM coy_positions WHERE id = ?")->execute([$input['id'] ?? 0]);
        respond(true, 'Position removed.');
    }

    case 'add_coy_holder': {
        requireSecretariat();
        $pos = (int)($input['position_id'] ?? 0);
        $mem = (int)($input['member_id'] ?? 0);
        if (!$pos || !$mem) respond(false, 'Select a youth member.');
        if (!youthExists($pdo, $mem)) respond(false, 'Only registered Youth Members can be added.');
        $s = $pdo->prepare("SELECT id FROM coy_positions WHERE id = ?");
        $s->execute([$pos]);
        if (!$s->fetch()) respond(false, 'Invalid position.');
        $pdo->prepare("INSERT IGNORE INTO coy_holders (id, position_id, member_id) VALUES (?, ?, ?)")->execute([nextId($pdo, 'coy_holders'), $pos, $mem]);
        respond(true, 'Member assigned to position.');
    }

    case 'remove_coy_holder': {
        requireSecretariat();
        $pdo->prepare("DELETE FROM coy_holders WHERE id = ?")->execute([$input['id'] ?? 0]);
        respond(true, 'Removed.');
    }

    case 'add_committee_member': {
        requireSecretariat();
        $com = (int)($input['committee_id'] ?? 0);
        $mem = (int)($input['member_id'] ?? 0);
        if (!$com || !$mem) respond(false, 'Select a youth member.');
        if (!youthExists($pdo, $mem)) respond(false, 'Only registered Youth Members can be added.');
        $s = $pdo->prepare("SELECT id FROM coy_committees WHERE id = ?");
        $s->execute([$com]);
        if (!$s->fetch()) respond(false, 'Invalid committee.');
        $pdo->prepare("INSERT IGNORE INTO coy_committee_members (id, committee_id, member_id) VALUES (?, ?, ?)")->execute([nextId($pdo, 'coy_committee_members'), $com, $mem]);
        respond(true, 'Member added to committee.');
    }

    case 'remove_committee_member': {
        requireSecretariat();
        $pdo->prepare("DELETE FROM coy_committee_members WHERE id = ?")->execute([$input['id'] ?? 0]);
        respond(true, 'Removed.');
    }

    case 'get_available_youth': {
        requireLogin();
        $slot = leaderSlotId($pdo);
        if ($slot === -1) respond(false, 'You have no slot assigned. Ask the Secretariat to assign you to a slot.');
        respond(true, '', availableYouth($pdo));
    }

    case 'assign_youth_slot': {
        requireLogin();
        $ids = $input['member_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) respond(false, 'Select at least one member.');

        $slot = leaderSlotId($pdo);
        if ($slot === -1) respond(false, 'You have no slot assigned. Ask the Secretariat to assign you to a slot.');
        if ($slot === null) {
            $slot = (int)($input['slot_id'] ?? 0);
            $s = $pdo->prepare("SELECT id FROM groups_slots WHERE id = ?");
            $s->execute([$slot]);
            if (!$s->fetch()) respond(false, 'Invalid slot.');
        }

        $avail = array_map('intval', array_column(availableYouth($pdo), 'id'));
        $upd = $pdo->prepare("UPDATE youth_members SET slot_id = ? WHERE id = ?");
        $count = 0;
        foreach ($ids as $mid) {
            if (!in_array($mid, $avail, true)) continue;
            $upd->execute([$slot, $mid]);
            $count++;
        }
        if ($count === 0) respond(false, 'None of the selected members could be added (already assigned, slot leader, or Commission On Youth).');
        respond(true, $count . ' member(s) added to the slot.');
    }

    case 'unassign_youth': {
        requireLogin();
        $id = (int)($input['id'] ?? 0);
        $slot = leaderSlotId($pdo);
        if ($slot !== null) {
            $s = $pdo->prepare("SELECT slot_id FROM youth_members WHERE id = ?");
            $s->execute([$id]);
            $m = $s->fetch();
            if (!$m || (int)$m['slot_id'] !== (int)$slot) respond(false, 'You can only remove members of your assigned slot.');
        }
        $pdo->prepare("UPDATE youth_members SET slot_id = NULL WHERE id = ?")->execute([$id]);
        respond(true, 'Member removed from the slot.');
    }

    default:
        respond(false, 'Unknown action.');
}
