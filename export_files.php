<?php
session_start();
if (empty($_SESSION['admin_auth'])) {
    http_response_code(403);
    exit('Acceso denegado');
}
require_once __DIR__ . '/config.php';

set_time_limit(600);
ini_set('memory_limit', '512M');

$excluir = ['bkp', '.git', 'vendor', 'node_modules'];
$raiz    = __DIR__;
$nombre  = 'archivos_' . date('Y-m-d_H-i') . '.zip';
$tmp     = sys_get_temp_dir() . '/' . $nombre;

$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('No se pudo crear el archivo ZIP');
}

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($raiz, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iter as $item) {
    $rel = str_replace($raiz . DIRECTORY_SEPARATOR, '', $item->getPathname());
    $primera = explode(DIRECTORY_SEPARATOR, $rel)[0];

    if (in_array($primera, $excluir)) continue;
    if (str_ends_with($rel, '.storepack')) continue;
    if ($rel === basename(__FILE__)) continue;

    if ($item->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        // Limpiar credenciales de config.php
        if (basename($item) === 'config.php') {
            $content = file_get_contents($item->getPathname());
            $content = preg_replace(
                ["/define\('DB_HOST',\s*'[^']*'\)/",
                 "/define\('DB_NAME',\s*'[^']*'\)/",
                 "/define\('DB_USER',\s*'[^']*'\)/",
                 "/define\('DB_PASS',\s*'[^']*'\)/"],
                ["define('DB_HOST', 'localhost')",
                 "define('DB_NAME', 'NUEVA_DB')",
                 "define('DB_USER', 'NUEVO_USUARIO')",
                 "define('DB_PASS', 'NUEVA_PASSWORD')"],
                $content
            );
            $zip->addFromString($rel, $content);
        } else {
            $zip->addFile($item->getPathname(), $rel);
        }
    }
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
exit();
