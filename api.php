<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'config.php';

const MAX_LEADERS_PER_SLOT = 5;
const CAL_START = '2026-10-01';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) $input = [];

require_once 'security.php';
ensureSecuritySchema($pdo);
ensureExtraSchema($pdo);

function respond($success, $message = '', $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function nextId($pdo, $table) {
    return (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM `$table`")->fetchColumn() + 1;
}

function purgeYouth($pdo, $ids) {
    $ids = array_values(array_filter(array_map('intval', (array)$ids)));
    if (!$ids) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    foreach (['attendance', 'event_attendance', 'coy_holders', 'coy_committee_members'] as $t) {
        try { $pdo->prepare("DELETE FROM `$t` WHERE member_id IN ($ph)")->execute($ids); } catch (Throwable $e) { error_log("purge $t: " . $e->getMessage()); }
    }
    $pdo->prepare("DELETE FROM youth_members WHERE id IN ($ph)")->execute($ids);
}

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
function restoreRows($pdo, $table, $rows) {
    foreach ((array)$rows as $r) {
        if (!is_array($r) || !$r) continue;
        $cols = array_keys($r);
        try {
            $pdo->prepare("INSERT IGNORE INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute(array_values($r));
        } catch (Throwable $e) { error_log("restore $table: " . $e->getMessage()); }
    }
}
function restoreYouth($pdo, $p) {
    restoreRows($pdo, 'youth_members', [$p['member']]);
    foreach (['attendance', 'event_attendance', 'coy_holders', 'coy_committee_members'] as $t) restoreRows($pdo, $t, $p[$t] ?? []);
}
function userLabel($pdo, $id) {
    $s = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?"); $s->execute([$id]);
    $r = $s->fetch();
    return $r ? $r['full_name'] . ' (' . $r['username'] . ')' : 'User #' . $id;
}

function currentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        respond(false, 'Your session has expired. Please log in again.');
    }
}

function requireSecretariat() {
    requireLogin();
    if ($_SESSION['role'] !== 'secretariat') {
        respond(false, 'You do not have permission to perform this action.');
    }
}

// Returns the slot_id assigned to the current leader (null if secretariat or unassigned)
function getLeaderSlotId($pdo) {
    // Secretariat sees everything — return null to skip all slot filtering
    if ($_SESSION['role'] === 'secretariat') return null;

    // PRIMARY: look up from slot_leaders
    $stmt = $pdo->prepare("SELECT slot_id FROM slot_leaders WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row && $row['slot_id']) {
        $sync = $pdo->prepare("UPDATE users SET slot_id = ? WHERE id = ?");
        $sync->execute([$row['slot_id'], $_SESSION['user_id']]);
        return (int)$row['slot_id'];
    }

    // FALLBACK: check users.slot_id directly
    $stmt = $pdo->prepare("SELECT slot_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row && $row['slot_id'] !== null) {
        return (int)$row['slot_id'];
    }

    // No slot assigned — return -1 so leader sees empty calendar (not all slots)
    return -1;
}

switch ($action) {

    case 'login': {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        if ($username === '' || $password === '') respond(false, 'Please enter both username and password.');
        $rlKey = clientIp() . '|' . strtolower($username);
        if (rlCount($pdo, 'login', $rlKey, 900) >= 5 || rlCount($pdo, 'loginip', clientIp(), 900) >= 25) {
            respond(false, 'Too many failed attempts. Please wait 15 minutes and try again.');
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            rlHit($pdo, 'login', $rlKey);
            rlHit($pdo, 'loginip', clientIp());
            respond(false, 'Invalid username or password.');
        }
        if ($user['status'] === 'pending') respond(false, 'Your account is still pending approval by the Secretariat.');
        if ($user['status'] === 'rejected') respond(false, 'Your account registration was rejected.');
        rlClear($pdo, 'login', $rlKey);
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        respond(true, 'Login successful! Redirecting...', currentUser());
    }

    case 'register': {
        $full_name = trim($input['full_name'] ?? '');
        $username  = trim($input['username'] ?? '');
        $password  = $input['password'] ?? '';
        $role      = $input['role'] ?? 'leader';
        if ($full_name === '' || $username === '' || $password === '') respond(false, 'Please fill in all required fields.');
        if (strlen($password) < 6) respond(false, 'Password must be at least 6 characters long.');
        if (!in_array($role, ['leader', 'secretariat'])) $role = 'leader';
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) respond(false, 'That username is already taken. Please choose another.');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, "pending")');
        $stmt->execute([$username, $hash, $full_name, $role]);
        respond(true, 'Registration successful! Your account is now pending approval by the Secretariat.');
    }

    case 'logout': {
        $_SESSION = [];
        session_destroy();
        respond(true, 'Logged out successfully.');
    }

    case 'check_session': {
        $u = currentUser();
        if (!$u) respond(false, 'Not logged in.');
        respond(true, '', $u);
    }

    case 'get_dashboard_stats': {
        requireLogin();
        $data = [];
        $today = date('Y-m-d');

        $data['total_youth'] = (int)$pdo->query("SELECT COUNT(*) c FROM youth_members WHERE status='active'")->fetch()['c'];

        $todayDow = (int)date('w');
        $leaderSlotId = getLeaderSlotId($pdo);

        $stmt = $pdo->prepare("SELECT * FROM groups_slots WHERE dow = ? ORDER BY slot_time ASC");
        $stmt->execute([$todayDow]);
        $todaySlots = $stmt->fetchAll();

        // Filter slots for leader
        if ($leaderSlotId !== null) {
            $todaySlots = array_values(array_filter($todaySlots, fn($s) => (int)$s['id'] === (int)$leaderSlotId));
        }

        $mc = [];
        foreach ($pdo->query("SELECT slot_id, COUNT(*) c FROM youth_members WHERE status='active' AND slot_id IS NOT NULL GROUP BY slot_id")->fetchAll() as $r) $mc[(int)$r['slot_id']] = (int)$r['c'];
        $att = [];
        $st2 = $pdo->prepare("SELECT slot_id, att_date, status, COUNT(*) c FROM attendance WHERE att_date >= ? GROUP BY slot_id, att_date, status");
        $st2->execute([date('Y-m-d', strtotime('-7 day'))]);
        foreach ($st2->fetchAll() as $r) $att[(int)$r['slot_id']][$r['att_date']][$r['status']] = (int)$r['c'];
        $nowT = date('H:i:s');

        $presentToday = 0; $absentToday = 0; $services = [];
        foreach ($todaySlots as $slot) {
            $sid = (int)$slot['id'];
            $p = $att[$sid][$today]['present'] ?? 0;
            $x = $att[$sid][$today]['excused'] ?? 0;
            $m = $mc[$sid] ?? 0;
            $a = ($nowT >= $slot['slot_time']) ? max(0, $m - $p) : $x;
            $presentToday += $p; $absentToday += $a;
            $services[] = ['id'=>$slot['id'],'title'=>$slot['name'],'time'=>$slot['slot_time'],'type'=>'mass','present'=>$p,'absent'=>$a,'total'=>$m];
        }

        $data['present_today']  = $presentToday;
        $data['absent_today']   = $absentToday;
        $data['today_services'] = $services;
        $data['today_date']     = date('l, F j, Y');

        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM events WHERE event_date > ? AND event_date <= DATE_ADD(?, INTERVAL 7 DAY)");
        $stmt->execute([$today, $today]);
        $data['upcoming_events_count'] = (int)$stmt->fetch()['c'];

        $stmt = $pdo->prepare("SELECT * FROM events WHERE event_date >= ? AND event_date <= DATE_ADD(?, INTERVAL 7 DAY) ORDER BY event_date ASC, event_time ASC LIMIT 6");
        $stmt->execute([$today, $today]);
        $data['upcoming_events'] = $stmt->fetchAll();

        // Recent attendance filtered by leader's slot
        if ($leaderSlotId !== null) {
            $stmt = $pdo->prepare("SELECT a.*, y.full_name, g.name as slot_name, g.name as event_title FROM attendance a JOIN youth_members y ON y.id = a.member_id LEFT JOIN groups_slots g ON g.id = a.slot_id WHERE a.slot_id = ? ORDER BY a.created_at DESC LIMIT 8");
            $stmt->execute([$leaderSlotId]);
        } else {
            $stmt = $pdo->query("SELECT a.*, y.full_name, g.name as slot_name, g.name as event_title FROM attendance a JOIN youth_members y ON y.id = a.member_id LEFT JOIN groups_slots g ON g.id = a.slot_id ORDER BY a.created_at DESC LIMIT 8");
        }
        $data['recent_attendance'] = $stmt->fetchAll();

        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $cnt = 0;
            foreach ($att as $sid => $byDate) {
                if ($leaderSlotId !== null && (int)$sid !== (int)$leaderSlotId) continue;
                $cnt += $byDate[$d]['present'] ?? 0;
            }
            $chart[] = ['date'=>$d,'label'=>date('D', strtotime($d)),'count'=>$cnt];
        }
        $data['weekly_chart'] = $chart;

        $slots = $pdo->query("SELECT * FROM groups_slots ORDER BY dow ASC, slot_time ASC")->fetchAll();
        if ($leaderSlotId !== null) {
            $slots = array_values(array_filter($slots, fn($s) => (int)$s['id'] === (int)$leaderSlotId));
        }
        $slotSummary = [];
        foreach ($slots as $slot) {
            $sid = (int)$slot['id'];
            $total = $mc[$sid] ?? 0;
            $diff = ((int)date('w') - (int)$slot['dow'] + 7) % 7;
            $recent = date('Y-m-d', strtotime("-$diff day"));
            if ($diff === 0 && $nowT < $slot['slot_time']) $recent = date('Y-m-d', strtotime('-7 day'));
            $present = $att[$sid][$recent]['present'] ?? 0;
            $excused = $att[$sid][$recent]['excused'] ?? 0;
            $pct = $total > 0 ? min(100, round(($present / $total) * 100)) : 0;
            $slotSummary[] = ['id'=>$slot['id'],'name'=>$slot['name'],'color'=>$slot['color'],'total'=>$total,'present'=>$present,'pct'=>$pct,'date'=>$recent,'excused'=>$excused,'recorded'=>1];
        }
        $data['slot_summary'] = $slotSummary;
        respond(true, '', $data);
    }

    case 'get_youth': {
        requireLogin();
        $leaderSlotId = getLeaderSlotId($pdo);
        if ($leaderSlotId !== null) {
            $stmt = $pdo->prepare("SELECT y.*, g.name as slot_name, g.color as slot_color FROM youth_members y LEFT JOIN groups_slots g ON g.id = y.slot_id WHERE y.slot_id = ? ORDER BY y.full_name ASC");
            $stmt->execute([$leaderSlotId]);
        } else {
            $stmt = $pdo->query("SELECT y.*, g.name as slot_name, g.color as slot_color FROM youth_members y LEFT JOIN groups_slots g ON g.id = y.slot_id ORDER BY y.full_name ASC");
        }
        respond(true, '', $stmt->fetchAll());
    }

    case 'save_youth': {
        requireLogin();
        $id        = $input['id'] ?? '';
        $full_name = trim($input['full_name'] ?? '');
        $contact   = trim($input['contact'] ?? '');
        $address   = trim($input['address'] ?? '');
        $birthdate = $input['birthdate'] ?? null;
        $slot_id   = $input['slot_id'] ?? null;
        $status    = $input['status'] ?? 'active';

        if ($full_name === '') respond(false, 'Full name is required.');
        if ($birthdate === '') $birthdate = null;
        if ($slot_id === '') $slot_id = null;

        // Leaders can only add/edit members in their own slot
        $leaderSlotId = getLeaderSlotId($pdo);
        if ($leaderSlotId !== null) {
            $slot_id = $leaderSlotId; // Force their slot
        }

        if ($leaderSlotId !== null && !$id) respond(false, 'Slot leaders can only add existing registered Youth Members to their slot.');
        if ($id) {
            // Leaders can only edit members in their slot
            if ($leaderSlotId !== null) {
                $chk = $pdo->prepare("SELECT slot_id FROM youth_members WHERE id=?");
                $chk->execute([$id]);
                $mem = $chk->fetch();
                if (!$mem || (int)$mem['slot_id'] !== (int)$leaderSlotId) respond(false, 'You can only edit members in your assigned slot.');
            }
            $stmt = $pdo->prepare("UPDATE youth_members SET full_name=?, contact=?, address=?, birthdate=?, slot_id=?, status=? WHERE id=?");
            $stmt->execute([$full_name, $contact, $address, $birthdate, $slot_id, $status, $id]);
            respond(true, 'Member updated successfully.');
        } else {
            if (!empty($input['skip_duplicates'])) {
                $d = $pdo->prepare("SELECT id FROM youth_members WHERE TRIM(full_name) = ? LIMIT 1");
                $d->execute([$full_name]);
                if ($d->fetch()) respond(false, 'duplicate');
            }
            $stmt = $pdo->prepare("INSERT INTO youth_members (id, full_name, contact, address, birthdate, slot_id, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([nextId($pdo, 'youth_members'), $full_name, $contact, $address, $birthdate, $slot_id, $status]);
            respond(true, 'Member added successfully.');
        }
    }

    case 'delete_youth': {
        requireLogin();
        $id = $input['id'] ?? 0;
        if (getLeaderSlotId($pdo) !== null) respond(false, 'Only the Secretariat can delete members. Use remove from slot instead.');
        ensureTrash($pdo);
        $snap = snapshotYouth($pdo, (int)$id);
        if (!$snap) respond(false, 'Member not found.');
        try {
            $pdo->beginTransaction();
            trashSave($pdo, 'youth', $snap['member']['full_name'], $snap);
            purgeYouth($pdo, [$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to delete member: ' . $e->getMessage());
        }
        logActivity($pdo, 'delete_youth', $snap['member']['full_name'], 'Moved to Recycle Bin with ' . count($snap['attendance']) . ' slot and ' . count($snap['event_attendance']) . ' event attendance record(s)');
        respond(true, 'Member moved to the Recycle Bin (can be restored for 30 days).');
    }

    case 'get_events': {
        requireLogin();
        $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC, event_time DESC");
        respond(true, '', $stmt->fetchAll());
    }

    case 'save_event': {
        requireSecretariat();
        $id         = $input['id'] ?? '';
        $title      = trim($input['title'] ?? '');
        $type       = $input['type'] ?? 'mass';
        $event_date = $input['event_date'] ?? '';
        $event_time = $input['event_time'] ?? null;
        $location   = trim($input['location'] ?? '');
        if ($title === '' || $event_date === '') respond(false, 'Title and date are required.');
        if ($event_time === '') $event_time = null;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE events SET title=?, type=?, event_date=?, event_time=?, location=? WHERE id=?");
            $stmt->execute([$title, $type, $event_date, $event_time, $location, $id]);
            respond(true, 'Event updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO events (id, title, type, event_date, event_time, location) VALUES (?,?,?,?,?,?)");
            $stmt->execute([nextId($pdo, 'events'), $title, $type, $event_date, $event_time, $location]);
            respond(true, 'Event added successfully.');
        }
    }

    case 'delete_event': {
        requireSecretariat();
        $id = $input['id'] ?? 0;
        $en = $pdo->prepare("SELECT title, event_date FROM events WHERE id=?"); $en->execute([$id]); $evr = $en->fetch();
        $pdo->prepare("DELETE FROM event_attendance WHERE event_id=?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM events WHERE id=?");
        $stmt->execute([$id]);
        logActivity($pdo, 'delete_event', $evr ? $evr['title'] . ' · ' . $evr['event_date'] : 'Event #' . $id, 'Event and its attendance deleted');
        respond(true, 'Event deleted.');
    }

    case 'get_slots': {
        requireLogin();
        $slots = $pdo->query("SELECT g.*, (SELECT COUNT(*) FROM youth_members y WHERE y.slot_id = g.id) AS member_count FROM groups_slots g ORDER BY g.dow ASC, g.slot_time ASC")->fetchAll();
        $rows = $pdo->query("SELECT sl.slot_id, u.id, u.full_name FROM slot_leaders sl JOIN users u ON u.id = sl.user_id ORDER BY u.full_name ASC")->fetchAll();
        $by = [];
        foreach ($rows as $r) $by[$r['slot_id']][] = ['id' => (int)$r['id'], 'full_name' => $r['full_name']];
        foreach ($slots as &$s) { $s['leaders'] = $by[$s['id']] ?? []; }
        unset($s);
        respond(true, '', $slots);
    }

    case 'get_leaders': {
        requireLogin();
        try {
            $stmt = $pdo->query("SELECT u.id, u.full_name, u.role, p.name AS position_name, sl.slot_id AS assigned_slot_id, g.name AS assigned_slot_name
                                 FROM users u
                                 LEFT JOIN coy_positions p ON p.id = u.coy_position_id
                                 LEFT JOIN slot_leaders sl ON sl.user_id = u.id
                                 LEFT JOIN groups_slots g ON g.id = sl.slot_id
                                 WHERE u.status='approved' ORDER BY u.full_name ASC");
        } catch (Throwable $e) {
            $stmt = $pdo->query("SELECT u.id, u.full_name, u.role, sl.slot_id AS assigned_slot_id, g.name AS assigned_slot_name
                                 FROM users u
                                 LEFT JOIN slot_leaders sl ON sl.user_id = u.id
                                 LEFT JOIN groups_slots g ON g.id = sl.slot_id
                                 WHERE u.status='approved' ORDER BY u.full_name ASC");
        }
        respond(true, '', $stmt->fetchAll());
    }

    case 'save_slot': {
        requireSecretariat();
        $id         = $input['id'] ?? '';
        $color      = $input['color'] ?? '#4F8EF7';
        $leader_ids = $input['leader_ids'] ?? [];
        if (!$id) respond(false, 'Invalid slot. The 6 schedule slots are fixed and cannot be added.');

        if (!is_array($leader_ids)) $leader_ids = [];
        $leader_ids = array_values(array_unique(array_filter(array_map('intval', $leader_ids), fn($v) => $v > 0)));

        if (count($leader_ids) > MAX_LEADERS_PER_SLOT) {
            respond(false, 'A slot can have a maximum of ' . MAX_LEADERS_PER_SLOT . ' leaders.');
        }

        if ($leader_ids) {
            $ph = implode(',', array_fill(0, count($leader_ids), '?'));

            // Only approved users
            $stmt = $pdo->prepare("SELECT COUNT(*) c FROM users WHERE status='approved' AND id IN ($ph)");
            $stmt->execute($leader_ids);
            if ((int)$stmt->fetch()['c'] !== count($leader_ids)) respond(false, 'One or more selected users are invalid or not approved.');

            // A leader can't already belong to a different slot
            $stmt = $pdo->prepare("SELECT u.full_name, g.name FROM slot_leaders sl JOIN users u ON u.id = sl.user_id JOIN groups_slots g ON g.id = sl.slot_id WHERE sl.user_id IN ($ph) AND sl.slot_id != ? LIMIT 1");
            $stmt->execute(array_merge($leader_ids, [$id]));
            if ($c = $stmt->fetch()) {
                respond(false, "{$c['full_name']} is already a leader of {$c['name']}. Remove them there first.");
            }
        }

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE users SET slot_id = NULL WHERE slot_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM slot_leaders WHERE slot_id = ?")->execute([$id]);

            $ins  = $pdo->prepare("INSERT INTO slot_leaders (id, slot_id, user_id) VALUES (?, ?, ?)");
            $nid  = nextId($pdo, 'slot_leaders');
            $sync = $pdo->prepare("UPDATE users SET slot_id = ? WHERE id = ?");
            foreach ($leader_ids as $uid) {
                $ins->execute([$nid++, $id, $uid]);
                $sync->execute([$id, $uid]);
            }

            // keep legacy column in sync (first leader or NULL)
            $pdo->prepare("UPDATE groups_slots SET color = ?, leader_id = ? WHERE id = ?")
                ->execute([$color, $leader_ids[0] ?? null, $id]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to save slot. Please try again.');
        }

        logActivity($pdo, 'save_slot', 'Slot #' . $id, 'Leaders set: ' . ($leader_ids ? implode(', ', array_map(fn($x) => userLabel($pdo, $x), $leader_ids)) : 'none'));
        respond(true, 'Slot updated successfully.');
    }

    case 'delete_slot': {
        requireSecretariat();
        respond(false, 'The 6 schedule slots are fixed and cannot be deleted.');
    }

    case 'get_attendance_for_slot': {
        requireLogin();
        $slot_id = $input['slot_id'] ?? $_GET['slot_id'] ?? 0;
        $date    = $input['date'] ?? $_GET['date'] ?? '';
        if (!$slot_id || !$date) respond(false, 'Please select a slot and date.');

        $stmt = $pdo->prepare("SELECT * FROM groups_slots WHERE id = ?");
        $stmt->execute([$slot_id]);
        $slot = $stmt->fetch();
        if (!$slot) respond(false, 'Invalid slot.');

        $dateDow = (int)date('w', strtotime($date));
        if ((int)$slot['dow'] !== $dateDow) respond(false, 'This slot is not scheduled on the selected date.');

        // Leaders can only access their own assigned slot
        $leaderSlotId = getLeaderSlotId($pdo);
        if ($leaderSlotId !== null && (int)$leaderSlotId !== (int)$slot_id) {
            respond(false, 'You do not have access to mark attendance for this slot.');
        }

        $stmt = $pdo->prepare("SELECT y.id as member_id, y.full_name, g.name as slot_name, g.id as slot_id, a.status, a.time_in FROM youth_members y LEFT JOIN groups_slots g ON g.id = y.slot_id LEFT JOIN attendance a ON a.member_id = y.id AND a.slot_id = ? AND a.att_date = ? WHERE y.status = 'active' AND y.slot_id = ? ORDER BY y.full_name ASC");
        $stmt->execute([$slot_id, $date, $slot_id]);
        respond(true, '', $stmt->fetchAll());
    }

    case 'save_attendance': {
        requireLogin();
        $slot_id = $input['slot_id'] ?? 0;
        $date    = $input['date'] ?? '';
        $records = $input['records'] ?? [];
        if (!$slot_id || !$date) respond(false, 'Please select a slot and date first.');

        $stmt = $pdo->prepare("SELECT * FROM groups_slots WHERE id = ?");
        $stmt->execute([$slot_id]);
        $slot = $stmt->fetch();
        if (!$slot) respond(false, 'Invalid slot.');

        $dateDow = (int)date('w', strtotime($date));
        if ((int)$slot['dow'] !== $dateDow) respond(false, 'This slot is not scheduled on the selected date.');

        // Leaders can only save attendance for their own slot
        $leaderSlotId = getLeaderSlotId($pdo);
        if ($leaderSlotId !== null && (int)$leaderSlotId !== (int)$slot_id) {
            respond(false, 'You do not have access to mark attendance for this slot.');
        }

        $stmt = $pdo->prepare("INSERT INTO attendance (member_id, slot_id, att_date, status, time_in) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in)");

        foreach ($records as $r) {
            $member_id = $r['member_id'] ?? 0;
            $status    = $r['status'] ?? 'absent';
            $time_in   = $r['time_in'] ?? null;
            if ($time_in === '') $time_in = null;
            if ($status === 'present' && $time_in === null) $time_in = date('H:i:s');
            if ($status === 'absent') $time_in = null;

            $chk = $pdo->prepare("SELECT slot_id FROM youth_members WHERE id = ?");
            $chk->execute([$member_id]);
            $mem = $chk->fetch();
            if (!$mem || (int)$mem['slot_id'] !== (int)$slot_id) continue;

            $stmt->execute([$member_id, $slot_id, $date, $status, $time_in]);
        }

        respond(true, 'Attendance saved successfully.');
    }

    case 'get_monthly_calendar': {
        requireLogin();
        $year  = (int)($input['year']  ?? date('Y'));
        $month = (int)($input['month'] ?? date('n'));
        if ($year * 12 + $month < 2026 * 12 + 10) { $year = 2026; $month = 10; }
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = (int)date('t', $firstDay);

        $allSlots = $pdo->query("SELECT * FROM groups_slots ORDER BY dow ASC, slot_time ASC")->fetchAll();
        $slotsByDow = [];
        foreach ($allSlots as $s) $slotsByDow[(int)$s['dow']][] = $s;

        $leaderSlotId = getLeaderSlotId($pdo);

        $pm = [];
        $pst = $pdo->prepare("SELECT slot_id, att_date, COUNT(*) c FROM attendance WHERE att_date BETWEEN ? AND ? AND status='present' GROUP BY slot_id, att_date");
        $pst->execute([date('Y-m-d', $firstDay), date('Y-m-t', $firstDay)]);
        foreach ($pst->fetchAll() as $r) $pm[$r['slot_id'] . '|' . $r['att_date']] = (int)$r['c'];
        $tm = [];
        foreach ($pdo->query("SELECT slot_id, COUNT(*) c FROM youth_members WHERE status='active' AND slot_id IS NOT NULL GROUP BY slot_id")->fetchAll() as $r) $tm[(int)$r['slot_id']] = (int)$r['c'];

        $catByDate = [];
        if ($leaderSlotId === null || isCoyOfficer($pdo)) {
            try {
                $from = date('Y-m-d', $firstDay); $to = date('Y-m-t', $firstDay);
                $st = $pdo->prepare("SELECT e.id, e.title, e.event_date, e.event_time, e.location, c.name AS category,
                    (SELECT COUNT(*) FROM event_attendance a WHERE a.event_id = e.id AND a.status='present') AS present
                    FROM events e JOIN event_categories c ON c.id = e.category_id
                    WHERE e.category_id IS NOT NULL AND e.event_date BETWEEN ? AND ? ORDER BY e.event_time ASC");
                $st->execute([$from, $to]);
                $totalActive = (int)$pdo->query("SELECT COUNT(*) FROM youth_members WHERE status='active'")->fetchColumn();
                foreach ($st->fetchAll() as $ce) {
                    $catByDate[$ce['event_date']][] = ['id'=>$ce['id'],'kind'=>'event','title'=>$ce['title'],'time'=>$ce['event_time'],'color'=>'#D97706','category'=>$ce['category'],'location'=>$ce['location'],'present'=>(int)$ce['present'],'total'=>$totalActive];
                }
            } catch (Throwable $e) { error_log('cal events: ' . $e->getMessage()); }
        }

        $calendar = [];
        $startDow = (int)date('w', $firstDay);
        for ($i = 0; $i < $startDow; $i++) $calendar[] = null;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $ts  = mktime(0, 0, 0, $month, $d, $year);
            $dow = (int)date('w', $ts);
            $dateStr = date('Y-m-d', $ts);
            $isWeekend = ($dow === 0 || $dow === 6);
            $daySlots = $slotsByDow[$dow] ?? [];

            $slotSummary = [];
            foreach ($daySlots as $slot) {
                // Leaders only see their assigned slot
                if ($leaderSlotId !== null && (int)$slot['id'] !== (int)$leaderSlotId) continue;

                $present = $pm[$slot['id'] . '|' . $dateStr] ?? 0;
                $total = $tm[(int)$slot['id']] ?? 0;

                $slotSummary[] = ['id'=>$slot['id'],'title'=>$slot['name'],'time'=>$slot['slot_time'],'color'=>$slot['color'],'present'=>$present,'total'=>$total];
            }

            foreach ($catByDate[$dateStr] ?? [] as $ce) $slotSummary[] = $ce;
            $calendar[] = ['day'=>$d,'date'=>$dateStr,'dow'=>$dow,'is_weekend'=>$isWeekend,'events'=>$slotSummary,'is_today'=>($dateStr===date('Y-m-d'))];
        }

        respond(true, '', ['calendar'=>$calendar,'year'=>$year,'month'=>$month,'month_name'=>date('F Y', $firstDay)]);
    }

    case 'get_my_slot': {
        requireLogin();
        // First try groups_slots.leader_id (set by secretariat in Admin Panel)
        $stmt = $pdo->prepare("SELECT g.id as slot_id, g.name as slot_name, g.color as slot_color, g.dow, g.slot_time FROM slot_leaders sl JOIN groups_slots g ON g.id = sl.slot_id WHERE sl.user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if (!$row) {
            // Fallback: try users.slot_id
            $stmt = $pdo->prepare("SELECT u.slot_id, g.name as slot_name, g.color as slot_color, g.dow, g.slot_time FROM users u LEFT JOIN groups_slots g ON g.id = u.slot_id WHERE u.id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();
        }
        respond(true, '', $row);
    }

    case 'get_events_for_date': {
        requireLogin();
        $date = $input['date'] ?? '';
        if (!$date) respond(false, 'Date required.');
        $dow = (int)date('w', strtotime($date));

        $leaderSlotId = getLeaderSlotId($pdo);

        $stmt = $pdo->prepare("SELECT * FROM groups_slots WHERE dow = ? ORDER BY slot_time ASC");
        $stmt->execute([$dow]);
        $slots = $stmt->fetchAll();

        // Leaders can only mark their slot
        if ($leaderSlotId !== null) {
            $slots = array_values(array_filter($slots, fn($s) => (int)$s['id'] === (int)$leaderSlotId));
        }

        $out = array_map(fn($s) => ['id'=>$s['id'],'title'=>$s['name'],'event_time'=>$s['slot_time'],'type'=>'mass'], $slots);
        respond(true, '', $out);
    }

    case 'get_report': {
        requireLogin();
        $from    = $input['from'] ?? '';
        $to      = $input['to'] ?? '';
        $slot_id = $input['slot_id'] ?? '';
        if ($from === '' || $to === '') respond(false, 'Please select a date range.');

        $leaderSlotId = getLeaderSlotId($pdo);
        // Leaders can only see reports for their slot
        if ($leaderSlotId !== null) $slot_id = $leaderSlotId;

        $allSlots = $pdo->query("SELECT * FROM groups_slots ORDER BY dow ASC, slot_time ASC")->fetchAll();
        if ($slot_id !== '') {
            $allSlots = array_values(array_filter($allSlots, fn($s) => (int)$s['id'] === (int)$slot_id));
        }

        $services = [];
        $startTs = strtotime($from); $endTs = strtotime($to);
        for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
            $dow = (int)date('w', $ts); $dateStr = date('Y-m-d', $ts);
            foreach ($allSlots as $s) {
                if ((int)$s['dow'] === $dow) $services[] = ['slot_id'=>$s['id'],'date'=>$dateStr,'name'=>$s['name'],'time'=>$s['slot_time']];
            }
        }

        $eventRows = [];
        foreach ($services as $svc) {
            $stmt = $pdo->prepare("SELECT COUNT(*) c FROM attendance WHERE slot_id=? AND att_date=? AND status='present'");
            $stmt->execute([$svc['slot_id'], $svc['date']]);
            $present = (int)$stmt->fetch()['c'];
            $stmt = $pdo->prepare("SELECT COUNT(*) c FROM youth_members WHERE slot_id=? AND status='active'");
            $stmt->execute([$svc['slot_id']]);
            $total = (int)$stmt->fetch()['c'];
            $eventRows[] = ['title'=>$svc['name'],'date'=>$svc['date'],'type'=>'mass','present'=>$present,'total'=>$total];
        }

        $sql = "SELECT y.id, y.full_name, y.slot_id, g.name as slot_name FROM youth_members y LEFT JOIN groups_slots g ON g.id=y.slot_id WHERE y.status='active'";
        $params = [];
        if ($slot_id !== '') { $sql .= " AND y.slot_id = ?"; $params[] = $slot_id; }
        $sql .= " ORDER BY y.full_name ASC";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $members = $stmt->fetchAll();

        $memberRows = [];
        foreach ($members as $m) {
            $memberServices = array_values(array_filter($services, fn($svc) => (int)$svc['slot_id'] === (int)$m['slot_id']));
            $totalServices = count($memberServices);
            $stmt = $pdo->prepare("SELECT COUNT(*) c FROM attendance WHERE member_id=? AND status='present' AND att_date BETWEEN ? AND ?");
            $stmt->execute([$m['id'], $from, $to]);
            $present = (int)$stmt->fetch()['c'];
            $pct = $totalServices > 0 ? round(($present / $totalServices) * 100) : 0;
            $memberRows[] = ['full_name'=>$m['full_name'],'slot_name'=>$m['slot_name']??'—','present'=>$present,'pct'=>$pct];
        }

        respond(true, '', ['events'=>$eventRows,'members'=>$memberRows]);
    }

    case 'get_alerts': {
        requireLogin();
        $leaderSlotId = getLeaderSlotId($pdo);
        $today = date('Y-m-d');
        $slots = $pdo->query("SELECT id, name, dow, slot_time FROM groups_slots")->fetchAll();
        if ($leaderSlotId !== null) $slots = array_values(array_filter($slots, fn($s) => (int)$s['id'] === (int)$leaderSlotId));
        $mc = [];
        foreach ($pdo->query("SELECT slot_id, COUNT(*) c FROM youth_members WHERE status='active' AND slot_id IS NOT NULL GROUP BY slot_id")->fetchAll() as $r) $mc[(int)$r['slot_id']] = (int)$r['c'];

        // 1) Sessions that already happened but were never submitted
        $ns = [];
        $from = max(CAL_START, date('Y-m-d', strtotime($leaderSlotId !== null ? '-6 day' : '-13 day')));
        if ($from <= $today) {
            $sub = pymRecordedSessions($pdo, $from, $today);
            for ($ts = strtotime($from); $ts <= strtotime($today); $ts += 86400) {
                $d = date('Y-m-d', $ts); $dow = (int)date('w', $ts);
                foreach ($slots as $sl) {
                    if ((int)$sl['dow'] !== $dow || empty($mc[(int)$sl['id']])) continue;
                    if (strtotime($d . ' ' . $sl['slot_time']) + 7200 > time()) continue;
                    if (isset($sub[$sl['id'] . '|' . $d])) continue;
                    $ns[] = ['slot_id' => (int)$sl['id'], 'name' => $sl['name'], 'date' => $d];
                }
            }
            usort($ns, fn($a, $b) => strcmp($b['date'], $a['date']));
        }

        // 2) Members who missed 3+ recorded services in a row
        $min = 3; $out = [];
        $rec = pymRecordedSessions($pdo, CAL_START, $today);
        $att = []; $exc = [];
        $q = $pdo->prepare("SELECT member_id, att_date, status FROM attendance WHERE att_date >= ?");
        $q->execute([CAL_START]);
        foreach ($q->fetchAll() as $r) {
            $k = $r['member_id'] . '|' . $r['att_date'];
            if ($r['status'] === 'present') $att[$k] = true;
            elseif ($r['status'] === 'excused') $exc[$k] = true;
        }
        $mq = $pdo->prepare("SELECT id, full_name FROM youth_members WHERE status='active' AND slot_id = ? ORDER BY full_name ASC");
        foreach ($slots as $sl) {
            $dates = [];
            foreach ($rec as $k => $_) { [$sid, $d] = explode('|', $k); if ((int)$sid === (int)$sl['id']) $dates[] = $d; }
            rsort($dates);
            if (count($dates) < $min) continue;
            $mq->execute([$sl['id']]);
            foreach ($mq->fetchAll() as $m) {
                $streak = 0; $ex = 0;
                foreach ($dates as $d) {
                    $k = $m['id'] . '|' . $d;
                    if (isset($att[$k])) break;
                    $streak++; if (isset($exc[$k])) $ex++;
                }
                if ($streak < $min) continue;
                $last = null;
                foreach ($dates as $d) if (isset($att[$m['id'] . '|' . $d])) { $last = $d; break; }
                $out[] = ['member_id' => (int)$m['id'], 'full_name' => $m['full_name'], 'slot_name' => $sl['name'], 'streak' => $streak, 'excused' => $ex, 'last_attended' => $last];
            }
        }
        usort($out, fn($a, $b) => ($b['streak'] <=> $a['streak']) ?: strcmp($a['full_name'], $b['full_name']));
        respond(true, '', ['not_submitted' => $ns, 'followup' => $out]);
    }

    case 'get_users': {
        requireSecretariat();
        $stmt = $pdo->query("SELECT u.id, u.username, u.full_name, u.role, u.slot_id, u.status, u.created_at, g.name as slot_name FROM users u LEFT JOIN groups_slots g ON g.id = u.slot_id ORDER BY u.created_at DESC");
        respond(true, '', $stmt->fetchAll());
    }

    case 'get_pending_users': {
        requireSecretariat();
        $stmt = $pdo->query("SELECT id, username, full_name, role, slot_id, created_at FROM users WHERE status='pending' ORDER BY created_at ASC");
        respond(true, '', $stmt->fetchAll());
    }

    case 'get_pending_accounts': {
        requireSecretariat();
        $stmt = $pdo->query("SELECT u.id, u.username, u.full_name, u.role, u.slot_id, u.status, u.created_at, g.name as slot_name FROM users u LEFT JOIN groups_slots g ON g.id = u.slot_id WHERE u.status = 'pending' ORDER BY u.created_at ASC");
        respond(true, '', $stmt->fetchAll());
    }

    case 'get_registered_accounts': {
        requireSecretariat();
        $stmt = $pdo->query("SELECT u.id, u.username, u.full_name, u.role, u.slot_id, u.status, u.created_at, g.name as slot_name FROM users u LEFT JOIN groups_slots g ON g.id = u.slot_id WHERE u.status = 'approved' ORDER BY u.created_at DESC");
        respond(true, '', $stmt->fetchAll());
    }

    case 'approve_user': {
        requireSecretariat();
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE users SET status='approved' WHERE id=?");
        $stmt->execute([$id]);
        logActivity($pdo, 'approve_user', userLabel($pdo, $id));
        respond(true, 'User approved successfully.');
    }

    case 'reject_user': {
        requireSecretariat();
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE users SET status='rejected' WHERE id=?");
        $stmt->execute([$id]);
        logActivity($pdo, 'reject_user', userLabel($pdo, $id));
        respond(true, 'User rejected.');
    }

    case 'save_user': {
        requireSecretariat();
        $id        = $input['id'] ?? '';
        $full_name = trim($input['full_name'] ?? '');
        $username  = trim($input['username'] ?? '');
        $password  = $input['password'] ?? '';
        $role      = $input['role'] ?? 'leader';
        $status    = $input['status'] ?? 'approved';
        $slot_id   = $input['slot_id'] ?? null;
        if ($slot_id === '' || $slot_id === 'null') $slot_id = null;
        if ($full_name === '' || $username === '') respond(false, 'Full name and username are required.');
        if ($id) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username=? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) respond(false, 'That username is already taken.');
            if ($password !== '') {
                if (strlen($password) < 6) respond(false, 'Password must be at least 6 characters.');
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, password=?, role=?, slot_id=?, status=? WHERE id=?");
                $stmt->execute([$full_name, $username, $hash, $role, $slot_id, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, role=?, slot_id=?, status=? WHERE id=?");
                $stmt->execute([$full_name, $username, $role, $slot_id, $status, $id]);
            }
            logActivity($pdo, 'save_user', $full_name . ' (' . $username . ')', "Updated: role=$role, status=$status" . ($password !== '' ? ', password changed' : ''));
            respond(true, 'User updated successfully.');
        } else {
            if ($password === '' || strlen($password) < 6) respond(false, 'Password is required and must be at least 6 characters.');
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) respond(false, 'That username is already taken.');
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (id, full_name, username, password, role, slot_id, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([nextId($pdo, 'users'), $full_name, $username, $hash, $role, $slot_id, $status]);
            logActivity($pdo, 'save_user', $full_name . ' (' . $username . ')', "Created: role=$role, status=$status");
            respond(true, 'User added successfully.');
        }
    }

    case 'delete_user': {
        requireSecretariat();
        $id = $input['id'] ?? 0;
        if ($id == $_SESSION['user_id']) respond(false, 'You cannot delete your own account.');
        $id = (int)$id;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u) respond(false, 'User not found.');
        ensureTrash($pdo);
        $m = $pdo->prepare("SELECT id FROM youth_members WHERE LOWER(TRIM(full_name)) = ?");
        $m->execute([strtolower(trim($u['full_name']))]);
        $yids = $m->fetchAll(PDO::FETCH_COLUMN);
        $sl = $pdo->prepare("SELECT * FROM slot_leaders WHERE user_id=?"); $sl->execute([$id]);
        $snapU = ['user' => $u, 'slot_leaders' => $sl->fetchAll(), 'youth' => array_values(array_filter(array_map(fn($y) => snapshotYouth($pdo, (int)$y), $yids)))];
        try {
            $pdo->beginTransaction();
            trashSave($pdo, 'user', $u['full_name'], $snapU);
            $pdo->prepare("DELETE FROM slot_leaders WHERE user_id=?")->execute([$id]);
            $pdo->prepare("UPDATE groups_slots SET leader_id = NULL WHERE leader_id=?")->execute([$id]);
            if (!empty($u['email_key'])) {
                try { $pdo->prepare("DELETE FROM email_codes WHERE email_key=?")->execute([$u['email_key']]); } catch (Throwable $e) {}
            }
            try { $pdo->prepare("DELETE FROM rate_limits WHERE bucket='login' AND rkey LIKE ?")->execute(['%|' . strtolower($u['username'])]); } catch (Throwable $e) {}
            try { $pdo->prepare("DELETE FROM php_sessions WHERE data LIKE ?")->execute(['%user_id|i:' . $id . ';%']); } catch (Throwable $e) {}
            purgeYouth($pdo, $yids);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Failed to delete user: ' . $e->getMessage());
        }
        logActivity($pdo, 'delete_user', $u['full_name'] . ' (' . $u['username'] . ')', 'Moved to Recycle Bin' . ($yids ? ' with ' . count($yids) . ' linked youth record(s)' : ''));
        respond(true, 'User moved to the Recycle Bin (can be restored for 30 days).');
    }

    case 'get_activity': {
        requireSecretariat();
        $q = trim($input['q'] ?? '');
        $lim = min(500, max(20, (int)($input['limit'] ?? 100)));
        try {
            $st = $pdo->prepare("SELECT id, user_name, role, action, target, details, created_at FROM activity_log WHERE (? = '' OR CONCAT_WS(' ', user_name, action, target, details) LIKE ?) ORDER BY id DESC LIMIT $lim");
            $st->execute([$q, '%' . $q . '%']);
            respond(true, '', $st->fetchAll());
        } catch (Throwable $e) {
            try { createActivityTable($pdo); } catch (Throwable $e2) {}
            respond(true, '', []);
        }
    }

    case 'get_trash': {
        requireSecretariat();
        ensureTrash($pdo);
        respond(true, '', $pdo->query("SELECT id, kind, label, deleted_by, deleted_at FROM trash ORDER BY id DESC")->fetchAll());
    }

    case 'restore_trash': {
        requireSecretariat();
        ensureTrash($pdo);
        $tid = (int)($input['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM trash WHERE id = ?"); $st->execute([$tid]);
        $t = $st->fetch();
        if (!$t) respond(false, 'Item not found or already expired.');
        $p = json_decode($t['payload'], true);
        if (!is_array($p)) respond(false, 'The backup data is damaged.');
        $ex = $pdo->prepare("SELECT id FROM youth_members WHERE id = ?");
        if ($t['kind'] === 'youth') {
            $ex->execute([(int)$p['member']['id']]);
            if ($ex->fetch()) respond(false, 'This member already exists.');
        } else {
            $c = $pdo->prepare("SELECT id FROM users WHERE id = ? OR username = ?");
            $c->execute([(int)$p['user']['id'], $p['user']['username']]);
            if ($c->fetch()) respond(false, 'A user with this username or ID already exists.');
        }
        try {
            $pdo->beginTransaction();
            if ($t['kind'] === 'youth') {
                restoreYouth($pdo, $p);
            } else {
                restoreRows($pdo, 'users', [$p['user']]);
                restoreRows($pdo, 'slot_leaders', $p['slot_leaders'] ?? []);
                foreach (($p['slot_leaders'] ?? []) as $r) {
                    $pdo->prepare("UPDATE groups_slots SET leader_id = ? WHERE id = ? AND leader_id IS NULL")->execute([$r['user_id'], $r['slot_id']]);
                }
                foreach (($p['youth'] ?? []) as $y) {
                    $ex->execute([(int)$y['member']['id']]);
                    if (!$ex->fetch()) restoreYouth($pdo, $y);
                }
            }
            $pdo->prepare("DELETE FROM trash WHERE id = ?")->execute([$tid]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(false, 'Restore failed: ' . $e->getMessage());
        }
        logActivity($pdo, 'restore', $t['label'], 'Restored ' . $t['kind'] . ' from Recycle Bin');
        respond(true, '"' . $t['label'] . '" restored.');
    }

    case 'purge_trash': {
        requireSecretariat();
        $tid = (int)($input['id'] ?? 0);
        $st = $pdo->prepare("SELECT label FROM trash WHERE id = ?"); $st->execute([$tid]);
        $lbl = $st->fetchColumn();
        $pdo->prepare("DELETE FROM trash WHERE id = ?")->execute([$tid]);
        logActivity($pdo, 'purge', (string)$lbl, 'Permanently deleted from Recycle Bin');
        respond(true, 'Permanently deleted.');
    }

    case 'get_categories': {
        requireSecretariat();
        $cats = $pdo->query("SELECT * FROM event_categories ORDER BY is_system DESC, name ASC")->fetchAll();
        $evs  = $pdo->query("SELECT * FROM events WHERE category_id IS NOT NULL ORDER BY event_date DESC, event_time DESC")->fetchAll();
        $by = [];
        foreach ($evs as $e) $by[$e['category_id']][] = $e;
        foreach ($cats as &$c) { $c['events'] = $by[$c['id']] ?? []; }
        unset($c);
        respond(true, '', $cats);
    }

    case 'save_category': {
        requireSecretariat();
        $id   = $input['id'] ?? '';
        $name = trim($input['name'] ?? '');
        if ($name === '') respond(false, 'Category name is required.');
        $stmt = $pdo->prepare("SELECT id FROM event_categories WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id ?: 0]);
        if ($stmt->fetch()) respond(false, 'A category with that name already exists.');
        if ($id) {
            $pdo->prepare("UPDATE event_categories SET name = ? WHERE id = ?")->execute([$name, $id]);
            respond(true, 'Category renamed successfully.');
        }
        $pdo->prepare("INSERT INTO event_categories (id, name, is_system) VALUES (?, ?, 0)")->execute([nextId($pdo, 'event_categories'), $name]);
        respond(true, 'Category added successfully.');
    }

    case 'delete_category': {
        requireSecretariat();
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT is_system FROM event_categories WHERE id = ?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) respond(false, 'Category not found.');
        if ((int)$c['is_system'] === 1) respond(false, 'The Normal Serve category cannot be deleted.');
        $pdo->prepare("DELETE FROM event_attendance WHERE event_id IN (SELECT id FROM events WHERE category_id = ?)")->execute([$id]);
        $pdo->prepare("DELETE FROM events WHERE category_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM event_categories WHERE id = ?")->execute([$id]);
        logActivity($pdo, 'delete_category', 'Category #' . $id, 'Category, its events and their attendance deleted');
        respond(true, 'Category and its events deleted.');
    }

    case 'save_category_event': {
        requireSecretariat();
        $id          = $input['id'] ?? '';
        $category_id = $input['category_id'] ?? 0;
        $title       = trim($input['title'] ?? '');
        $event_date  = $input['event_date'] ?? '';
        $event_time  = $input['event_time'] ?? null;
        $location    = trim($input['location'] ?? '');
        if ($title === '' || $event_date === '') respond(false, 'Title and date are required.');
        if ($event_time === '') $event_time = null;
        $stmt = $pdo->prepare("SELECT is_system FROM event_categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $c = $stmt->fetch();
        if (!$c) respond(false, 'Invalid category.');
        if ((int)$c['is_system'] === 1) respond(false, 'Normal Serve uses the fixed weekly slots. Events cannot be added to it.');
        if ($id) {
            $pdo->prepare("UPDATE events SET title=?, event_date=?, event_time=?, location=?, category_id=? WHERE id=?")
                ->execute([$title, $event_date, $event_time, $location, $category_id, $id]);
            respond(true, 'Event updated successfully.');
        }
        $pdo->prepare("INSERT INTO events (id, title, type, event_date, event_time, location, category_id) VALUES (?,?,?,?,?,?,?)")
            ->execute([nextId($pdo, 'events'), $title, 'category', $event_date, $event_time, $location, $category_id]);
        respond(true, 'Event added successfully.');
    }

    case 'get_event_attendance': {
        requireSecretariat();
        $event_id = $input['event_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND category_id IS NOT NULL");
        $stmt->execute([$event_id]);
        if (!$stmt->fetch()) respond(false, 'Invalid event.');
        $stmt = $pdo->prepare("SELECT y.id AS member_id, y.full_name, g.name AS slot_name, a.status, a.time_in
            FROM youth_members y
            LEFT JOIN groups_slots g ON g.id = y.slot_id
            LEFT JOIN event_attendance a ON a.member_id = y.id AND a.event_id = ?
            WHERE y.status = 'active' ORDER BY y.full_name ASC");
        $stmt->execute([$event_id]);
        respond(true, '', $stmt->fetchAll());
    }

    case 'save_event_attendance': {
        requireSecretariat();
        $event_id = $input['event_id'] ?? 0;
        $records  = $input['records'] ?? [];
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND category_id IS NOT NULL");
        $stmt->execute([$event_id]);
        if (!$stmt->fetch()) respond(false, 'Invalid event.');
        $ins = $pdo->prepare("INSERT INTO event_attendance (member_id, event_id, status, time_in) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in)");
        foreach ($records as $r) {
            $member_id = $r['member_id'] ?? 0;
            $status    = ($r['status'] ?? 'absent') === 'present' ? 'present' : 'absent';
            $time_in   = $r['time_in'] ?? null;
            if ($time_in === '') $time_in = null;
            if ($status === 'present' && $time_in === null) $time_in = date('H:i:s');
            if ($status === 'absent') $time_in = null;
            $ins->execute([$member_id, $event_id, $status, $time_in]);
        }
        respond(true, 'Attendance saved successfully.');
    }

    default:
        respond(false, 'Unknown action.');
}
