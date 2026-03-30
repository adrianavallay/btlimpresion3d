<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$upload_dir = __DIR__ . '/uploads/slides/';

// Ensure directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    file_put_contents($upload_dir . '.htaccess', "<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>\n");
}

if (empty($_FILES['imagen'])) {
    echo json_encode(['ok' => false, 'error' => 'No se recibio archivo']);
    exit;
}

$file = $_FILES['imagen'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Error al subir: codigo ' . $file['error']]);
    exit;
}

// Validate size (10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Archivo demasiado grande (max 10MB)']);
    exit;
}

// Validate type
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    echo json_encode(['ok' => false, 'error' => 'Tipo no permitido. Solo JPG, PNG o WebP']);
    exit;
}

$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$ext = $ext_map[$mime];
$filename = 'slide_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok' => true, 'url' => 'uploads/slides/' . $filename]);
} else {
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo']);
}
