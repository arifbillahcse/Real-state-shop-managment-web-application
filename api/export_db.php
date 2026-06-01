<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';

if (!isLoggedIn() || !User::isAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = Database::getInstance();

// ---- collect table names ----
$tables = [];
foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
    $tables[] = array_values($row)[0];
}

// ---- build dump ----
$dump  = "-- SQL Backup\n";
$dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$dump .= "-- Database : " . DB_NAME . "\n\n";
$dump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$dump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$dump .= "SET time_zone = '+06:00';\n\n";

foreach ($tables as $table) {
    $dump .= "-- -----------------------------------------------\n";
    $dump .= "-- Table: `$table`\n";
    $dump .= "-- -----------------------------------------------\n";

    // CREATE TABLE
    $row  = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    $dump .= "DROP TABLE IF EXISTS `$table`;\n";
    $dump .= $row[1] . ";\n\n";

    // Rows
    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $cols   = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $chunks = array_chunk($rows, 100);          // batch 100 rows per INSERT
        foreach ($chunks as $chunk) {
            $values = array_map(function ($r) use ($pdo) {
                $escaped = array_map(function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, $r);
                return '(' . implode(', ', $escaped) . ')';
            }, $chunk);
            $dump .= "INSERT INTO `$table` ($cols) VALUES\n";
            $dump .= implode(",\n", $values) . ";\n";
        }
        $dump .= "\n";
    }
}

$dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

$filename = 'backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($dump));
header('Cache-Control: no-cache');

echo $dump;
