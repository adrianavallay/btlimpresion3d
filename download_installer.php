<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

if (!is_admin()) {
    http_response_code(403);
    exit('No autorizado');
}

$file = __DIR__ . '/migration_installer.php';
if (!file_exists($file)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="migration_installer.php"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
readfile($file);
exit;
