<?php
require_once __DIR__ . '/config.php';

if (!is_admin()) {
    http_response_code(403);
    exit('No autorizado');
}

$file = $_GET['file'] ?? '';

// Sanitize: only allow valid backup filenames, no path traversal
if (!preg_match('/^backup_(db|files|full)_[\d\-_]+\.(sql\.gz|zip)$/', $file)) {
    http_response_code(400);
    exit('Archivo no valido');
}

$path = __DIR__ . '/bkp/' . $file;

if (!file_exists($path)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

// Determine content type
$ext = pathinfo($file, PATHINFO_EXTENSION);
if (str_ends_with($file, '.sql.gz')) {
    $content_type = 'application/gzip';
} else {
    $content_type = 'application/zip';
}

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache, no-store, must-revalidate');

readfile($path);
exit;
