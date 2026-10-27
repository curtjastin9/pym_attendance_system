<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'config.php';
require_once 'security.php';
ensureRateTable($pdo);
ensureScholarSchema($pdo);

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

function respond($success, $message = '', $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}
function requireSecretariat() {
    if (!isset($_SESSION['user_id'])) respond(false, 'Your session has expired. Please log in again.');
    if ($_SESSION['role'] !== 'secretariat') respond(false, 'You do not have permission to perform this action.');
}
function validDate($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}

// Public, read-only endpoint (no login). Reasons for excused absences are never exposed.

switch ($action) {

    case 'get_record_categories': {
        $rows = $pdo->query("SELECT id, name, is_system FROM event_categories ORDER BY is_system DESC, name ASC")->fetchAll();
        respond(true, '', $rows);
    }

    case 'get_record_members': {
        $rows = $pdo->query("SELECT y.id, y.full_name, y.status, y.scholar_type AS scholar, g.name AS slot FROM youth_members y LEFT JOIN groups_slots g ON g.id = y.slot_id WHERE y.status = 'active' ORDER BY y.full_name ASC")->fetchAll();
        pymApplyLeaderSlot($rows, pymLeaderLabels($pdo), 'id', 'slot');
        respond(true, '', $rows);
    }

    case 'get_records': {
        if (rlCount($pdo, 'public', clientIp(), 60) >= 30) respond(false, 'Too many requests. Please wait a minute and try again.');
        rlHit($pdo, 'public', clientIp());
        $from = $input['from'] ?? '';
        $to   = $input['to'] ?? '';
        if (!validDate($from) || !validDate($to)) respond(false, 'Please select a valid date range.');
        if ($from > $to) respond(false, 'The start date must be before the end date.');
        if ($from < '2026-10-01') $from = '2026-10-01';
        if ($to < $from) respond(false, 'Attendance records start on October 2026.');
        if ((strtotime($to) - strtotime($from)) > 86400 * 366 * 2) respond(false, 'Date range is too large (max 2 years).');

        $ids = $input['category_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

        $mids = $input['member_ids'] ?? [];
        if (!is_array($mids)) $mids = [];
        $mids = array_values(array_unique(array_filter(array_map('intval', $mids), fn($v) => $v > 0)));
        $memSql = $mids ? ' AND a.member_id IN (' . implode(',', $mids) . ')' : '';
        $sch = $input['scholar_types'] ?? [];
        if (!is_array($sch)) $sch = [];
        $sch = array_values(array_intersect(['none', 'shs', 'college'], array_map('strval', $sch)));
        $schSql = ($sch && count($sch) < 3) ? " AND y.scholar_type IN ('" . implode("','", $sch) . "')" : '';

        try {
            $cats = $pdo->query("SELECT id, name, is_system FROM event_categories")->fetchAll();
            $selected = [];
            foreach ($cats as $c) {
                if (!$ids || in_array((int)$c['id'], $ids, true)) $selected[] = $c;
            }
            if (!$selected) respond(false, 'Please select at least one category.');

            $rows = [];

            // Normal Serve (system category) → weekly slot attendance
            $system = null;
            $otherIds = [];
            foreach ($selected as $c) {
                if ((int)$c['is_system'] === 1) $system = $c;
                else $otherIds[] = (int)$c['id'];
            }

            if ($system) {
                $stmt = $pdo->prepare("SELECT a.member_id, a.att_date AS date, g.name AS event, g.slot_time AS event_time, y.full_name AS member, g.name AS slot, a.status, a.time_in, y.scholar_type AS scholar
                    FROM attendance a
                    JOIN youth_members y ON y.id = a.member_id
                    LEFT JOIN groups_slots g ON g.id = a.slot_id
                    WHERE a.att_date BETWEEN ? AND ?$memSql$schSql");
                $stmt->execute([$from, $to]);
                foreach ($stmt->fetchAll() as $r) {
                    $r['category'] = $system['name'];
                    $rows[] = $r;
                }
            }

            if ($otherIds) {
                $ph = implode(',', array_fill(0, count($otherIds), '?'));
                $stmt = $pdo->prepare("SELECT a.member_id, e.event_date AS date, e.title AS event, e.event_time AS event_time, c.name AS category, y.full_name AS member, g.name AS slot, a.status, a.time_in, y.scholar_type AS scholar
                    FROM event_attendance a
                    JOIN events e ON e.id = a.event_id
                    JOIN event_categories c ON c.id = e.category_id
                    JOIN youth_members y ON y.id = a.member_id
                    LEFT JOIN groups_slots g ON g.id = y.slot_id
                    WHERE e.category_id IN ($ph) AND e.event_date BETWEEN ? AND ?$memSql$schSql");
                $stmt->execute(array_merge($otherIds, [$from, $to]));
                foreach ($stmt->fetchAll() as $r) $rows[] = $r;
            }

            // ---- Missing records = ABSENT ----
            // Every scheduled session that already happened counts, even if nobody saved attendance for it.
            // Normal Serve counts from the first date attendance was ever recorded.
            $have = [];
            foreach ($rows as $r) $have[$r['member_id'] . '|' . $r['category'] . '|' . $r['date'] . '|' . $r['event']] = true;
            $att = [];
            try {
                $q = $pdo->prepare("SELECT member_id, att_date FROM attendance WHERE att_date BETWEEN ? AND ? AND status = 'present'");
                $q->execute([$from, $to]);
                foreach ($q->fetchAll() as $t) $att[$t['member_id'] . '|' . $t['att_date']] = true;
            } catch (Throwable $e) {}

            $today = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
            $memFilter = $mids ? ' AND y.id IN (' . implode(',', $mids) . ')' : '';
            $members = $pdo->query("SELECT y.id, y.full_name, y.slot_id, y.scholar_type AS scholar, g.name AS slot FROM youth_members y LEFT JOIN groups_slots g ON g.id = y.slot_id WHERE y.status = 'active'$memFilter$schSql")->fetchAll();

            if ($system) {
                $first = pymFirstDate($pdo);
                $hist = pymSlotHistory($pdo);
                $taken = pymRecordedSessions($pdo, $from, $to);
                $slots = $pdo->query("SELECT id, name, slot_time, dow FROM groups_slots")->fetchAll();

                if ($first) {
                    $d = new DateTime(max($from, $first, '2026-10-01'));
                    $end = new DateTime($to);
                    for (; $d <= $end; $d->modify('+1 day')) {
                        $ds = $d->format('Y-m-d');
                        $dow = (int)$d->format('w');
                        foreach ($slots as $sl) {
                            if ((int)$sl['dow'] !== $dow) continue;
                            if ($ds >= $today && !isset($taken[$sl['id'] . '|' . $ds])) continue; // future/today not yet recorded
                            foreach ($members as $m) {
                                if ((int)pymSlotOn($hist, $m, $ds) !== (int)$sl['id']) continue;
                                if (isset($have[$m['id'] . '|' . $system['name'] . '|' . $ds . '|' . $sl['name']])) continue;
                                if (isset($att[$m['id'] . '|' . $ds])) continue; // attended another slot that day
                                $rows[] = ['member_id' => $m['id'], 'date' => $ds, 'event' => $sl['name'], 'event_time' => $sl['slot_time'],
                                           'category' => $system['name'], 'member' => $m['full_name'], 'slot' => $sl['name'], 'status' => 'absent', 'time_in' => null, 'reason' => null, 'scholar' => $m['scholar']];
                            }
                        }
                    }
                }
            }

            if ($otherIds) {
                $ph = implode(',', array_fill(0, count($otherIds), '?'));
                $st = $pdo->prepare("SELECT e.id, e.title, e.event_date, e.event_time, c.name AS category,
                        (SELECT COUNT(*) FROM event_attendance x WHERE x.event_id = e.id) AS n
                    FROM events e JOIN event_categories c ON c.id = e.category_id
                    WHERE e.category_id IN ($ph) AND e.event_date BETWEEN ? AND ?");
                $st->execute(array_merge($otherIds, [$from, $to]));
                foreach ($st->fetchAll() as $ev) {
                    if ($ev['event_date'] >= $today && (int)$ev['n'] === 0) continue;
                    foreach ($members as $m) {
                        if (isset($have[$m['id'] . '|' . $ev['category'] . '|' . $ev['event_date'] . '|' . $ev['title']])) continue;
                        $rows[] = ['member_id' => $m['id'], 'date' => $ev['event_date'], 'event' => $ev['title'], 'event_time' => $ev['event_time'],
                                   'category' => $ev['category'], 'member' => $m['full_name'], 'slot' => $m['slot'], 'status' => 'absent', 'time_in' => null, 'reason' => null, 'scholar' => $m['scholar']];
                    }
                }
            }

            pymApplyLeaderSlot($rows, pymLeaderLabels($pdo), 'member_id', 'slot');
            usort($rows, function ($a, $b) {
                return [$a['date'], (string)$a['event_time'], $a['category'], $a['event'], strtolower($a['member'])]
                   <=> [$b['date'], (string)$b['event_time'], $b['category'], $b['event'], strtolower($b['member'])];
            });

            if (count($rows) > 50000) respond(false, 'Too many records (' . count($rows) . '). Please narrow the date range or categories.');

            respond(true, '', [
                'rows'       => $rows,
                'categories' => array_map(fn($c) => $c['name'], $selected),
            ]);
        } catch (Throwable $e) {
            respond(false, 'Failed to load records. Please try again.');
        }
    }

    default:
        respond(false, 'Unknown action.');
}
