<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true);
$archivo = basename($input['archivo'] ?? '');
$tipo    = $input['tipo'] ?? 'db';

if (!$archivo) {
    echo json_encode(['ok' => false, 'error' => 'Archivo no especificado']);
    exit;
}

// Validate filename pattern
if (!preg_match('/^backup_(db|full)_[\d\-_]+\.(sql\.gz|zip)$/', $archivo)) {
    echo json_encode(['ok' => false, 'error' => 'Nombre de archivo no valido']);
    exit;
}

$ruta = __DIR__ . '/bkp/' . $archivo;

if (!file_exists($ruta)) {
    echo json_encode(['ok' => false, 'error' => 'Archivo no encontrado']);
    exit;
}

try {
    $sql_content = '';

    // If ZIP (full backup), extract the .sql.gz inside it
    if (str_ends_with($archivo, '.zip')) {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new Exception('No se pudo abrir el archivo ZIP');
        }
        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if (str_ends_with($nombre, '.sql.gz')) {
                $gz_data = $zip->getFromIndex($i);
                $sql_content = gzdecode($gz_data);
                $found = true;
                break;
            }
        }
        $zip->close();
        if (!$found) {
            throw new Exception('No se encontro archivo SQL dentro del backup completo');
        }
    } elseif (str_ends_with($archivo, '.sql.gz')) {
        // Direct .sql.gz file
        $gz = gzopen($ruta, 'rb');
        if (!$gz) {
            throw new Exception('No se pudo abrir el archivo comprimido');
        }
        $sql_content = '';
        while (!gzeof($gz)) {
            $sql_content .= gzread($gz, 65536);
        }
        gzclose($gz);
    }

    if (empty(trim($sql_content))) {
        throw new Exception('El archivo de backup esta vacio');
    }

    // Auto-backup before restore (safety net)
    require_once __DIR__ . '/backup_runner.php';
    backup_database('pre-restore');

    // Execute the SQL
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true
        ]
    );

    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    // Split SQL into individual statements
    $statements = [];
    $current = '';
    $lines = explode("\n", $sql_content);

    foreach ($lines as $line) {
        if (preg_match('/^--/', $line) || trim($line) === '') {
            continue;
        }
        $current .= $line . "\n";
        if (preg_match('/;\s*$/', trim($line))) {
            $statements[] = trim($current);
            $current = '';
        }
    }

    $executed = 0;
    $errors = [];

    foreach ($statements as $stmt) {
        if (empty(trim($stmt)) || $stmt === ';') continue;
        if (preg_match('/^SET FOREIGN_KEY_CHECKS/i', $stmt)) continue;

        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            $errors[] = substr($e->getMessage(), 0, 200);
            if (count($errors) > 10) break;
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    if (count($errors) > 10) {
        echo json_encode([
            'ok' => false,
            'error' => "Demasiados errores durante la restauracion. Se ejecutaron {$executed} sentencias.",
            'errores' => $errors
        ]);
    } else {
        $msg = "Base de datos restaurada correctamente desde {$archivo}. {$executed} sentencias ejecutadas.";
        if (!empty($errors)) {
            $msg .= ' (' . count($errors) . ' advertencias)';
        }
        echo json_encode([
            'ok' => true,
            'mensaje' => $msg,
            'errores' => $errors
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
