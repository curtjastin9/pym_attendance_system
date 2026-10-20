<?php
/**
 * Adds "excused" status + reason column to attendance tables (runs safely every time).
 */
function ensureExcusedSchema($pdo) {
    foreach (['attendance', 'event_attendance'] as $t) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM `$t` LIKE 'status'")->fetch();
            if ($col && stripos($col['Type'], 'enum') === 0 && stripos($col['Type'], 'excused') === false) {
                preg_match_all("/'([^']*)'/", $col['Type'], $m);
                $vals = $m[1];
                $vals[] = 'excused';
                $list = implode(',', array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $vals));
                $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
                if ($col['Default'] !== null) $def = "DEFAULT '" . str_replace("'", "''", $col['Default']) . "'";
                else $def = $col['Null'] === 'YES' ? 'DEFAULT NULL' : '';
                $pdo->exec("ALTER TABLE `$t` MODIFY `status` ENUM($list) $null $def");
            }
            if (!$pdo->query("SHOW COLUMNS FROM `$t` LIKE 'reason'")->fetch()) {
                $pdo->exec("ALTER TABLE `$t` ADD COLUMN `reason` VARCHAR(255) DEFAULT NULL");
            }
        } catch (Throwable $e) { /* ignore */ }
    }
}
