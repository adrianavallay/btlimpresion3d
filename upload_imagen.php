<?php
// ============================================================
// UPLOAD IMAGEN — API JSON
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// Require admin session
if (!is_admin()) {
    json_response(['ok' => false, 'mensaje' => 'No autorizado'], 401);
}

$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_size = 5 * 1024 * 1024; // 5 MB

// ── DELETE action ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $file = basename($_GET['file'] ?? '');
    if ($file === '') {
        json_response(['ok' => false, 'mensaje' => 'Nombre de archivo no proporcionado']);
    }

    $path = UPLOAD_DIR . $file;
    if (!file_exists($path)) {
        json_response(['ok' => false, 'mensaje' => 'Archivo no encontrado']);
    }

    if (unlink($path)) {
        json_response(['ok' => true, 'mensaje' => 'Imagen eliminada']);
    } else {
        json_response(['ok' => false, 'mensaje' => 'No se pudo eliminar el archivo'], 500);
    }
}

// ── UPLOAD (POST) ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'mensaje' => 'Metodo no permitido'], 405);
}

// Check file exists
if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] === UPLOAD_ERR_NO_FILE) {
    json_response(['ok' => false, 'mensaje' => 'No se recibio ningun archivo']);
}

$file = $_FILES['imagen'];

// Check upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamano maximo permitido por el servidor',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamano maximo del formulario',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subio parcialmente',
        UPLOAD_ERR_NO_TMP_DIR => 'No se encontro el directorio temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco',
        UPLOAD_ERR_EXTENSION  => 'Extension de PHP detuvo la subida',
    ];
    $msg = $errors[$file['error']] ?? 'Error desconocido al subir el archivo';
    json_response(['ok' => false, 'mensaje' => $msg]);
}

// Validate extension
$original_name = $file['name'];
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    json_response(['ok' => false, 'mensaje' => 'Extension no permitida. Permitidas: ' . implode(', ', $allowed_ext)]);
}

// Validate size
if ($file['size'] > $max_size) {
    json_response(['ok' => false, 'mensaje' => 'El archivo excede el tamano maximo de 5MB']);
}

// Sanitize original filename
$safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
$safe_name = substr($safe_name, 0, 80); // limit length
$filename = uniqid() . '_' . $safe_name . '.' . $ext;

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        json_response(['ok' => false, 'mensaje' => 'No se pudo crear el directorio de uploads'], 500);
    }
}

// Move file
$target = UPLOAD_DIR . $filename;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    json_response(['ok' => false, 'mensaje' => 'Error al mover el archivo subido'], 500);
}

// Success
json_response([
    'ok'       => true,
    'url'      => UPLOAD_URL . $filename,
    'filename' => $filename
]);
