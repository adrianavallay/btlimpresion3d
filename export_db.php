<?php
session_start();
if (empty($_SESSION['admin_auth'])) {
    http_response_code(403);
    exit('Acceso denegado');
}
require_once __DIR__ . '/config.php';

set_time_limit(300);
ini_set('memory_limit', '256M');

$pdo = pdo();
$sql  = "-- Studio Digital Store — DB Export\n";
$sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Antes de importar: crea la DB y configura config.php\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

$tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tablas as $tabla) {
    $create = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch();
    $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n";
    $sql .= $create['Create Table'] . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `{$tabla}`")->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $sql .= "INSERT INTO `{$tabla}` ({$cols}) VALUES\n";
        $vals = [];
        foreach ($rows as $r) {
            $v = array_map(fn($x) => $x === null ? 'NULL' : $pdo->quote($x), $r);
            $vals[] = '(' . implode(', ', $v) . ')';
        }
        $sql .= implode(",\n", $vals) . ";\n\n";
    }
}
$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

$filename = 'db_export_' . date('Y-m-d_H-i') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql));
echo $sql;
exit();
