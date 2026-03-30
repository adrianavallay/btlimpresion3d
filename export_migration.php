<?php
// ============================================================
// EXPORT MIGRATION — Genera un .storepack con todo el sitio
// ============================================================
require_once __DIR__ . '/config.php';

if (!is_admin()) { http_response_code(403); exit('No autorizado'); }

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metodo no permitido'); }

set_time_limit(300);

$pdo = pdo();

// ── 1. Crear directorio temporal ──
$tmp_id   = 'migration_' . uniqid();
$tmp_dir  = sys_get_temp_dir() . '/' . $tmp_id;
$files_dir = $tmp_dir . '/files';
mkdir($files_dir, 0755, true);

// ── 2. Generar dump SQL ──
function dump_sql(PDO $pdo): string {
    $sql  = "-- Studio Digital Store Migration\n";
    $sql .= "-- Generado: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";

    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tablas as $tabla) {
        $create = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$tabla}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) continue;

        $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $batch = [];
        foreach ($rows as $i => $r) {
            $v = array_map(fn($x) => $x === null ? 'NULL' : $pdo->quote($x), $r);
            $batch[] = '(' . implode(', ', $v) . ')';
            if (count($batch) >= 500 || $i === count($rows) - 1) {
                $sql .= "INSERT INTO `{$tabla}` ({$cols}) VALUES\n" . implode(",\n", $batch) . ";\n\n";
                $batch = [];
            }
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

$sql_dump = dump_sql($pdo);
file_put_contents($tmp_dir . '/database.sql', $sql_dump);

// ── 3. Copiar archivos ──
$excluir = ['bkp', '.git', 'vendor', 'node_modules'];
$raiz    = realpath(__DIR__);

function copiar_recursivo(string $src, string $dst, array $excluir, string $raiz): int {
    $count = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $item) {
        $rel = ltrim(str_replace($raiz, '', $item->getPathname()), DIRECTORY_SEPARATOR . '/');
        $primera = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $rel))[0];
        if (in_array($primera, $excluir)) continue;
        if (str_ends_with($rel, '.storepack')) continue;

        $dest = $dst . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($dest)) mkdir($dest, 0755, true);
        } else {
            $dir = dirname($dest);
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            if (basename($item) === 'config.php' && dirname($item->getPathname()) === $raiz) {
                $content = file_get_contents($item->getPathname());
                $content = preg_replace(
                    [
                        "/define\('DB_HOST',\s*'[^']*'\)/",
                        "/define\('DB_NAME',\s*'[^']*'\)/",
                        "/define\('DB_USER',\s*'[^']*'\)/",
                        "/define\('DB_PASS',\s*'[^']*'\)/",
                        "/define\('SITE_URL',\s*'[^']*'\)/",
                        "/define\('ADMIN_PASS_PLAIN',\s*'[^']*'\)/",
                    ],
                    [
                        "define('DB_HOST', 'localhost')        // Completar en instalacion",
                        "define('DB_NAME', 'NUEVA_DB')         // Completar en instalacion",
                        "define('DB_USER', 'NUEVO_USUARIO')    // Completar en instalacion",
                        "define('DB_PASS', 'NUEVA_PASSWORD')   // Completar en instalacion",
                        "define('SITE_URL', 'https://tu-sitio.com') // Completar en instalacion",
                        "define('ADMIN_PASS_PLAIN', 'CAMBIAR') // Cambiar despues de instalar",
                    ],
                    $content
                );
                file_put_contents($dest, $content);
            } else {
                copy($item->getPathname(), $dest);
            }
            $count++;
        }
    }
    return $count;
}

$total_archivos = copiar_recursivo($raiz, $files_dir, $excluir, $raiz);

// ── 4. Copiar installer al raíz del paquete ──
if (file_exists(__DIR__ . '/migration_installer.php')) {
    copy(__DIR__ . '/migration_installer.php', $tmp_dir . '/installer.php');
}

// ── 5. Manifest ──
$tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$manifest = [
    'version'           => '1.0',
    'fecha_exportacion' => date('Y-m-d H:i:s'),
    'sitio_origen'      => SITE_URL,
    'tablas'            => $tablas,
    'total_archivos'    => $total_archivos,
    'tamano_db'         => strlen($sql_dump),
    'generado_por'      => 'Studio Digital Store Migration v1.0',
];
file_put_contents($tmp_dir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── 6. Crear ZIP ──
if (!is_dir(__DIR__ . '/bkp')) mkdir(__DIR__ . '/bkp', 0755, true);
$zip_name = 'migration_' . date('Y-m-d_H-i') . '.storepack';
$zip_path = __DIR__ . '/bkp/' . $zip_name;

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Error al crear el archivo ZIP');
}

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iter as $file) {
    if ($file->isFile()) {
        $rel = ltrim(str_replace($tmp_dir, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/');
        $zip->addFile($file->getPathname(), $rel);
    }
}
$zip->close();

// ── 7. Limpiar tmp ──
function eliminar_dir(string $dir): void {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
eliminar_dir($tmp_dir);

// ── 8. Servir descarga ──
$size = filesize($zip_path);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $zip_name . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-cache');
readfile($zip_path);

// Eliminar después de servir
@unlink($zip_path);
exit();
