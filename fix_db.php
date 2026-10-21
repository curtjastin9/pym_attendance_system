<?php
// ONE-TIME DATABASE FIX. While logged in as Secretariat open /fix_db.php?run=1 , then DELETE this file from GitHub.
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'secretariat') {
    http_response_code(403);
    exit("Log in as Secretariat first, then open this page again.");
}
if (($_GET['run'] ?? '') !== '1') exit("Open /fix_db.php?run=1 to start.");
set_time_limit(120);

$sql = <<<'SQLEND'
-- Fixes "Field 'id' doesn't have a default value" and KEEPS all your data.
-- TiDB Cloud > SQL Editor > select pym_attendance > paste everything > Run.


SET FOREIGN_KEY_CHECKS = 0;

-- 1) Back up current data

CREATE TABLE IF NOT EXISTS bak_users AS SELECT * FROM users;
CREATE TABLE IF NOT EXISTS bak_groups_slots AS SELECT * FROM groups_slots;
CREATE TABLE IF NOT EXISTS bak_youth_members AS SELECT * FROM youth_members;
CREATE TABLE IF NOT EXISTS bak_slot_leaders AS SELECT * FROM slot_leaders;
CREATE TABLE IF NOT EXISTS bak_attendance AS SELECT * FROM attendance;
CREATE TABLE IF NOT EXISTS bak_coy_positions AS SELECT * FROM coy_positions;
CREATE TABLE IF NOT EXISTS bak_coy_committees AS SELECT * FROM coy_committees;
CREATE TABLE IF NOT EXISTS bak_coy_holders AS SELECT * FROM coy_holders;
CREATE TABLE IF NOT EXISTS bak_coy_committee_members AS SELECT * FROM coy_committee_members;
CREATE TABLE IF NOT EXISTS bak_event_categories AS SELECT * FROM event_categories;
CREATE TABLE IF NOT EXISTS bak_events AS SELECT * FROM events;
CREATE TABLE IF NOT EXISTS bak_event_attendance AS SELECT * FROM event_attendance;

-- 2) Rebuild tables with AUTO_INCREMENT
DROP TABLE IF EXISTS `event_attendance`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `event_categories`;
DROP TABLE IF EXISTS `coy_committee_members`;
DROP TABLE IF EXISTS `coy_holders`;
DROP TABLE IF EXISTS `coy_committees`;
DROP TABLE IF EXISTS `coy_positions`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `slot_leaders`;
DROP TABLE IF EXISTS `youth_members`;
DROP TABLE IF EXISTS `groups_slots`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `php_sessions`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('secretariat','leader') NOT NULL DEFAULT 'leader',
  `slot_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `coy_position_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `groups_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) DEFAULT '#4F8EF7',
  `dow` tinyint(1) NOT NULL,
  `slot_time` time NOT NULL,
  `leader_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leader_id` (`leader_id`),
  CONSTRAINT `groups_slots_ibfk_1` FOREIGN KEY (`leader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `youth_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `slot_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `slot_id` (`slot_id`),
  CONSTRAINT `youth_members_ibfk_1` FOREIGN KEY (`slot_id`) REFERENCES `groups_slots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `slot_leaders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slot_user` (`slot_id`,`user_id`),
  UNIQUE KEY `uniq_user` (`user_id`),
  CONSTRAINT `sl_slot_fk` FOREIGN KEY (`slot_id`) REFERENCES `groups_slots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sl_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `att_date` date NOT NULL,
  `status` enum('present','absent','excused') DEFAULT 'present',
  `time_in` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_attendance` (`member_id`,`slot_id`,`att_date`),
  KEY `slot_id` (`slot_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `youth_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `groups_slots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `coy_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `coy_committees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `coy_holders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pos_member` (`position_id`,`member_id`),
  KEY `ch_mem_fk` (`member_id`),
  CONSTRAINT `ch_mem_fk` FOREIGN KEY (`member_id`) REFERENCES `youth_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ch_pos_fk` FOREIGN KEY (`position_id`) REFERENCES `coy_positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `coy_committee_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `committee_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_member` (`committee_id`,`member_id`),
  KEY `cc_mem_fk` (`member_id`),
  CONSTRAINT `cc_com_fk` FOREIGN KEY (`committee_id`) REFERENCES `coy_committees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cc_mem_fk` FOREIGN KEY (`member_id`) REFERENCES `youth_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'mass',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `events_cat_fk` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `status` enum('present','absent','excused') DEFAULT 'present',
  `time_in` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_att` (`member_id`,`event_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `ea_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ea_member_fk` FOREIGN KEY (`member_id`) REFERENCES `youth_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bucket` varchar(20) NOT NULL,
  `rkey` varchar(150) NOT NULL,
  `hit_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rl` (`bucket`,`rkey`,`hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `php_sessions` (
  `id` varchar(128) NOT NULL,
  `data` mediumtext NOT NULL,
  `ts` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ts` (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Restore data
INSERT INTO users (id, username, password, full_name, role, slot_id, status, created_at, coy_position_id)
  SELECT id, username, password, full_name, role, slot_id, status, created_at, coy_position_id FROM bak_users;
INSERT INTO groups_slots (id, name, color, dow, slot_time, leader_id)
  SELECT id, name, color, dow, slot_time, leader_id FROM bak_groups_slots;
INSERT INTO youth_members (id, full_name, contact, address, birthdate, slot_id, status, created_at)
  SELECT id, full_name, contact, address, birthdate, slot_id, status, created_at FROM bak_youth_members;
INSERT INTO slot_leaders (id, slot_id, user_id)
  SELECT id, slot_id, user_id FROM bak_slot_leaders;
INSERT INTO attendance (id, member_id, slot_id, att_date, status, time_in, created_at, reason)
  SELECT id, member_id, slot_id, att_date, status, time_in, created_at, reason FROM bak_attendance;
INSERT INTO coy_positions (id, name, sort_order)
  SELECT id, name, sort_order FROM bak_coy_positions;
INSERT INTO coy_committees (id, name, sort_order)
  SELECT id, name, sort_order FROM bak_coy_committees;
INSERT INTO coy_holders (id, position_id, member_id)
  SELECT id, position_id, member_id FROM bak_coy_holders;
INSERT INTO coy_committee_members (id, committee_id, member_id)
  SELECT id, committee_id, member_id FROM bak_coy_committee_members;
INSERT INTO event_categories (id, name, is_system, created_at)
  SELECT id, name, is_system, created_at FROM bak_event_categories;
INSERT INTO events (id, title, event_date, event_time, location, type, created_at, category_id)
  SELECT id, title, event_date, event_time, location, type, created_at, category_id FROM bak_events;
INSERT INTO event_attendance (id, member_id, event_id, status, time_in, created_at, reason)
  SELECT id, member_id, event_id, status, time_in, created_at, reason FROM bak_event_attendance;

SET FOREIGN_KEY_CHECKS = 1;

-- 4) Check the counts, then run the DROP lines below if they look right
SELECT 'users' t, COUNT(*) c FROM users UNION ALL
SELECT 'youth_members', COUNT(*) FROM youth_members UNION ALL
SELECT 'attendance', COUNT(*) FROM attendance UNION ALL
SELECT 'events', COUNT(*) FROM events;

-- DROP TABLE bak_users, bak_groups_slots, bak_youth_members, bak_slot_leaders, bak_attendance, bak_coy_positions, bak_coy_committees, bak_coy_holders, bak_coy_committee_members, bak_event_categories, bak_events, bak_event_attendance;

SQLEND;

$sql = preg_replace('/^--.*$/m', '', $sql);
$parts = preg_split('/;\s*(\n|$)/', $sql);
$n = 0;
foreach ($parts as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') continue;
    $n++;
    $label = substr(preg_replace('/\s+/', ' ', $stmt), 0, 70);
    try {
        if (stripos($stmt, 'SELECT') === 0) {
            $rows = $pdo->query($stmt)->fetchAll();
            echo "#$n OK  $label\n";
            foreach ($rows as $r) echo '     ' . json_encode($r) . "\n";
        } else {
            $pdo->exec($stmt);
            echo "#$n OK  $label\n";
        }
    } catch (Throwable $e) {
        echo "#$n FAILED  $label\n     " . $e->getMessage() . "\n\nStopped. Your data is safe in the bak_* tables. Send me this message.\n";
        exit;
    }
}
echo "\nDONE. Now delete fix_db.php from GitHub.\n";
