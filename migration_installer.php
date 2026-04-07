<?php
// ============================================================
// MIGRATION INSTALLER — Export DB + Export Archivos
// ============================================================
set_time_limit(600);
ini_set('memory_limit', '512M');

define('INSTALLER_PASSWORD', 'btl2024admin');

$action = $_POST['action'] ?? '';
$error = '';

// ── Verificar contraseña en cada acción ──
function check_password(): bool {
    return ($_POST['password'] ?? '') === INSTALLER_PASSWORD;
}

// ── EXPORTAR DB ──
if ($action === 'export_db') {
    if (!check_password()) { $error = 'Contraseña incorrecta.'; }
    else {
        require_once __DIR__ . '/config.php';
        try {
            $pdo = pdo();
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            $sql = "-- Backup: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- DB: " . DB_NAME . "\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $create['Create Table'] . ";\n\n";

                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                    $cols = '`' . implode('`, `', array_keys($row)) . '`';
                    $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES (" . implode(', ', $values) . ");\n";
                }
                if ($rows) $sql .= "\n";
            }

            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            $filename = 'btl_db_' . date('Y-m-d') . '.sql';

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($sql));
            echo $sql;
            exit;
        } catch (Exception $e) {
            $error = 'Error al exportar DB: ' . $e->getMessage();
        }
    }
}

// ── EXPORTAR ARCHIVOS (ZIP) ──
if ($action === 'export_files') {
    if (!check_password()) { $error = 'Contraseña incorrecta.'; }
    else {
        $base = __DIR__;
        $exclude = ['.git', 'uploads', 'bkp', 'migration_installer.php'];
        $zipPath = sys_get_temp_dir() . '/btl_archivos_' . date('Y-m-d') . '_' . uniqid() . '.zip';

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('No se pudo crear el archivo ZIP.');
            }

            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iter as $file) {
                $realPath = $file->getRealPath();
                $relativePath = substr($realPath, strlen($base) + 1);

                // Excluir carpetas/archivos
                $skip = false;
                foreach ($exclude as $ex) {
                    if ($relativePath === $ex || str_starts_with($relativePath, $ex . DIRECTORY_SEPARATOR) || str_starts_with($relativePath, $ex . '/')) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($realPath, $relativePath);
                }
            }

            $zip->close();

            $filename = 'btl_archivos_' . date('Y-m-d') . '.zip';
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            @unlink($zipPath);
            exit;
        } catch (Exception $e) {
            if (file_exists($zipPath)) @unlink($zipPath);
            $error = 'Error al crear ZIP: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Migration Installer</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;color:#333;line-height:1.6;min-height:100vh}
.container{max-width:560px;margin:0 auto;padding:40px 16px}
h1{font-size:1.3rem;font-weight:800;text-align:center;margin-bottom:4px}
.subtitle{text-align:center;font-size:0.8rem;color:#999;margin-bottom:32px}
.card{background:#fff;border:1px solid #e0e0e0;padding:28px;margin-bottom:20px}
.card h2{font-size:1rem;font-weight:700;margin-bottom:6px}
.card p{font-size:0.85rem;color:#666;margin-bottom:16px}
.field{margin-bottom:16px}
.field label{display:block;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#666;margin-bottom:6px}
.field input[type=password]{width:100%;padding:10px 12px;border:1px solid #e0e0e0;font-size:0.88rem;font-family:inherit}
.field input:focus{outline:none;border-color:#111}
.btn{display:block;width:100%;padding:16px;border:none;font-size:0.88rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;font-family:inherit;text-align:center}
.btn:hover{opacity:0.9}
.btn-db{background:#1e40af;color:#fff}
.btn-zip{background:#065f46;color:#fff}
.note{font-size:0.75rem;color:#aaa;margin-top:10px}
.msg-error{background:#fef2f2;border:1px solid #fecaca;border-left:3px solid #ef4444;color:#991b1b;padding:12px 16px;font-size:0.85rem;margin-bottom:16px}
.exclude-list{font-size:0.78rem;color:#999;margin-top:8px}
.exclude-list code{background:#f0f0f0;padding:1px 5px;font-size:0.75rem}
</style>
</head>
<body>
<div class="container">
    <h1>Migration Installer</h1>
    <p class="subtitle">Exportar base de datos y archivos del proyecto</p>

<?php if ($error): ?>
    <div class="msg-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

    <!-- EXPORTAR DB -->
    <div class="card">
        <h2>Descargar Base de Datos</h2>
        <p>Genera un dump .sql completo con estructura y datos de todas las tablas.</p>
        <form method="POST">
            <input type="hidden" name="action" value="export_db">
            <div class="field">
                <label>Contrase&ntilde;a</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-db">Descargar .sql</button>
        </form>
        <p class="note">Archivo: btl_db_<?= date('Y-m-d') ?>.sql</p>
    </div>

    <!-- EXPORTAR ARCHIVOS -->
    <div class="card">
        <h2>Descargar Archivos del Proyecto</h2>
        <p>Genera un .zip con todos los archivos del proyecto.</p>
        <form method="POST">
            <input type="hidden" name="action" value="export_files">
            <div class="field">
                <label>Contrase&ntilde;a</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-zip">Descargar .zip</button>
        </form>
        <p class="exclude-list">Excluye: <code>.git/</code> <code>uploads/</code> <code>bkp/</code> <code>migration_installer.php</code></p>
    </div>

</div>
</body>
</html>
