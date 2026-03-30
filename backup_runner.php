<?php
require_once __DIR__ . '/config.php';

// Security: only admin or CLI
if (php_sapi_name() !== 'cli' && !is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}

define('BKP_DIR', __DIR__ . '/bkp/');
define('MAX_BACKUPS', 10);

// Ensure directory exists and is protected
if (!is_dir(BKP_DIR)) {
    mkdir(BKP_DIR, 0755, true);
}
if (!file_exists(BKP_DIR . '.htaccess')) {
    file_put_contents(BKP_DIR . '.htaccess', "Order deny,allow\nDeny from all\n");
}

// ============================================================
// DATABASE BACKUP
// ============================================================
function backup_database(string $tipo = 'auto'): string|false {
    $filename = BKP_DIR . "backup_db_" . date('Y-m-d_H-i') . ".sql";

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql = "-- Backup generado: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Base de datos: " . DB_NAME . "\n";
        $sql .= "-- Tipo: {$tipo}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tablas as $tabla) {
            // Structure
            $create = $pdo->query("SHOW CREATE TABLE `{$tabla}`")->fetch();
            $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n";
            $sql .= $create['Create Table'] . ";\n\n";

            // Data in batches
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
            if ($count === 0) continue;

            $batch_size = 500;
            for ($offset = 0; $offset < $count; $offset += $batch_size) {
                $rows = $pdo->query("SELECT * FROM `{$tabla}` LIMIT {$batch_size} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
                if (empty($rows)) break;

                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $sql .= "INSERT INTO `{$tabla}` ({$cols}) VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                    $values[] = '(' . implode(', ', $vals) . ')';
                }
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Compress with gzip
        $gz_file = $filename . '.gz';
        $gz = gzopen($gz_file, 'wb9');
        gzwrite($gz, $sql);
        gzclose($gz);

        limpiar_backups_viejos('backup_db_');
        return $gz_file;

    } catch (Exception $e) {
        error_log("Backup DB error: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// FILES BACKUP
// ============================================================
function backup_archivos(): string|false {
    $filename = BKP_DIR . "backup_files_" . date('Y-m-d_H-i') . ".zip";
    $raiz = realpath(__DIR__);

    $zip = new ZipArchive();
    if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $excluir = ['bkp', '.git', 'node_modules', 'vendor'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($raiz, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $ruta_relativa = substr($file->getPathname(), strlen($raiz) + 1);
        $primera_carpeta = explode(DIRECTORY_SEPARATOR, $ruta_relativa)[0];

        if (in_array($primera_carpeta, $excluir)) continue;

        if ($file->isDir()) {
            $zip->addEmptyDir($ruta_relativa);
        } elseif ($file->isFile() && $file->isReadable()) {
            $zip->addFile($file->getPathname(), $ruta_relativa);
        }
    }

    $zip->close();
    limpiar_backups_viejos('backup_files_');
    return $filename;
}

// ============================================================
// FULL BACKUP (DB + FILES)
// ============================================================
function backup_completo(): string|false {
    $filename = BKP_DIR . "backup_full_" . date('Y-m-d_H-i') . ".zip";

    // Generate DB dump first
    $db_file = backup_database('full');
    if (!$db_file) return false;

    // Create ZIP with all files + DB dump
    $raiz = realpath(__DIR__);
    $zip = new ZipArchive();
    if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $excluir = ['bkp', '.git', 'node_modules', 'vendor'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($raiz, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $ruta_relativa = substr($file->getPathname(), strlen($raiz) + 1);
        $primera_carpeta = explode(DIRECTORY_SEPARATOR, $ruta_relativa)[0];

        if (in_array($primera_carpeta, $excluir)) continue;

        if ($file->isDir()) {
            $zip->addEmptyDir($ruta_relativa);
        } elseif ($file->isFile() && $file->isReadable()) {
            $zip->addFile($file->getPathname(), $ruta_relativa);
        }
    }

    // Add DB dump inside the ZIP
    $zip->addFile($db_file, 'database/' . basename($db_file));
    $zip->close();

    // Remove the standalone DB dump (it's inside the full ZIP now)
    unlink($db_file);

    limpiar_backups_viejos('backup_full_');
    return $filename;
}

// ============================================================
// CLEANUP OLD BACKUPS
// ============================================================
function limpiar_backups_viejos(string $prefijo): void {
    $archivos = glob(BKP_DIR . $prefijo . '*');
    if (!$archivos) return;
    usort($archivos, fn($a, $b) => filemtime($a) - filemtime($b));
    while (count($archivos) > MAX_BACKUPS) {
        @unlink(array_shift($archivos));
    }
}

// ============================================================
// EXECUTE
// ============================================================
header('Content-Type: application/json; charset=utf-8');

$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'db';

$start = microtime(true);

switch ($tipo) {
    case 'db':
        $result = backup_database('manual');
        break;
    case 'archivos':
        $result = backup_archivos();
        break;
    case 'completo':
        $result = backup_completo();
        break;
    case 'auto':
        $result = backup_completo();
        if ($result) {
            file_put_contents(BKP_DIR . '.last_backup', time());
        }
        break;
    default:
        $result = false;
}

$duration = round(microtime(true) - $start, 2);

echo json_encode([
    'ok' => (bool) $result,
    'archivo' => $result ? basename($result) : '',
    'tamano' => $result ? filesize($result) : 0,
    'duracion' => $duration
]);
