<?php
/**
 * Instalador de Bolivians Reformes.
 * Abrir en el navegador: https://TU-DOMINIO/install.php
 * Pide las credenciales de MySQL, crea config.php, importa schema.sql
 * y se borra a sí mismo al terminar.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$root = __DIR__;
$already = false;

// Si ya hay config y tablas con datos, no permitir reinstalar
if (is_file($root . '/config.php')) {
    try {
        $cfg = require $root . '/config.php';
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port'] ?? '3306', $cfg['db_name']),
            $cfg['db_user'],
            $cfg['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $n = $pdo->query("SHOW TABLES LIKE 'settings'")->rowCount();
        if ($n > 0) {
            $already = true;
        }
    } catch (Throwable $e) {
        // config roto o base inaccesible: se permite reinstalar
    }
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$error = null;
$done = false;
$selfDeleted = false;
$counts = [];

if (!$already && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['db_host'] ?? 'localhost')) ?: 'localhost';
    $port = trim((string) ($_POST['db_port'] ?? '3306')) ?: '3306';
    $name = trim((string) ($_POST['db_name'] ?? ''));
    $user = trim((string) ($_POST['db_user'] ?? ''));
    $pass = (string) ($_POST['db_pass'] ?? '');

    if ($name === '' || $user === '') {
        $error = 'Falta el nombre de la base o el usuario.';
    } else {
        try {
            // 1. Probar conexión
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // 2. Importar schema.sql
            $sql = file_get_contents($root . '/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('No se encontró schema.sql junto al instalador.');
            }
            $alreadyInstalled = $pdo->query("SHOW TABLES LIKE 'settings'")->rowCount() > 0
                && (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() > 0;
            if ($alreadyInstalled) {
                throw new RuntimeException('Esa base ya tiene datos de la web. Instalación cancelada para no duplicar contenido.');
            }
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                // Quitar líneas de comentario y ejecutar lo que quede
                $stmt = trim((string) preg_replace('/^\s*--.*$/m', '', $stmt));
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            }

            // 3. Escribir config.php
            $cfgCode = "<?php\n// Generado por install.php el " . date('Y-m-d H:i') . "\nreturn [\n"
                . "    'db_host' => " . var_export($host, true) . ",\n"
                . "    'db_port' => " . var_export($port, true) . ",\n"
                . "    'db_name' => " . var_export($name, true) . ",\n"
                . "    'db_user' => " . var_export($user, true) . ",\n"
                . "    'db_pass' => " . var_export($pass, true) . ",\n"
                . "];\n";
            if (file_put_contents($root . '/config.php', $cfgCode) === false) {
                throw new RuntimeException('No se pudo escribir config.php (revisa permisos de la carpeta).');
            }

            // 4. Resumen
            foreach (['settings', 'services', 'projects', 'reviews'] as $t) {
                $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            }

            // 5. Autodestruirse
            $selfDeleted = @unlink(__FILE__);
            $done = true;
        } catch (PDOException $e) {
            $error = 'No se pudo conectar o importar: ' . $e->getMessage();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Instalador · Bolivians Reformes</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      min-height: 100vh; display: grid; place-items: center;
      background: #16130F; color: #16130F;
      font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
      padding: 1.5rem;
    }
    .card {
      width: min(460px, 100%); background: #FDFCFA; border-radius: 16px;
      padding: 2.2rem 2rem; box-shadow: 0 40px 80px -30px rgba(0,0,0,.6);
    }
    .brand { display: flex; align-items: center; gap: .6rem; font-weight: 800; margin-bottom: 1.4rem; }
    .brand i {
      width: 38px; height: 38px; border-radius: 9px; background: #D97706; color: #fff;
      display: inline-grid; place-items: center; font-style: normal; font-weight: 900; font-size: 1.2rem;
    }
    h1 { font-size: 1.35rem; letter-spacing: -.02em; margin-bottom: .4rem; }
    p.hint { color: #8A8178; font-size: .85rem; margin-bottom: 1.2rem; }
    label { display: block; font-weight: 600; font-size: .82rem; color: #4A443C; margin-top: .9rem; }
    input {
      display: block; width: 100%; margin-top: .3rem; font: inherit;
      background: #F7F3EE; border: 1px solid rgba(22,19,15,.15); border-radius: 8px;
      padding: .6rem .8rem;
    }
    input:focus { outline: none; border-color: #D97706; box-shadow: 0 0 0 3px rgba(217,119,6,.15); }
    button {
      width: 100%; margin-top: 1.5rem; font: inherit; font-weight: 700; cursor: pointer;
      background: #D97706; color: #fff; border: 0; border-radius: 99px; padding: .8rem;
    }
    button:hover { background: #B45309; }
    .msg { border-radius: 8px; padding: .8rem 1rem; margin-bottom: 1rem; font-size: .9rem; font-weight: 500; }
    .msg--err { background: #FDECEA; border: 1px solid #F5C6CB; color: #93261F; }
    .msg--ok { background: #E8F5E9; border: 1px solid #A5D6A7; color: #1B5E20; }
    .msg--warn { background: #FFF7E6; border: 1px solid #F5D48F; color: #7A5200; }
    ul { margin: .6rem 0 1rem 1.2rem; font-size: .9rem; color: #4A443C; }
    a.next {
      display: block; text-align: center; margin-top: 1.2rem; font-weight: 700;
      color: #B45309;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand"><i>B</i> Bolivians Reformes · Instalador</div>

    <?php if ($already): ?>
      <h1>Ya está instalado</h1>
      <p class="hint">La web ya tiene configuración y datos. Por seguridad, borra este archivo <code>install.php</code> del servidor.</p>
      <a class="next" href="dyp-admin/">Ir al panel →</a>

    <?php elseif ($done): ?>
      <h1>¡Instalación completada!</h1>
      <div class="msg msg--ok">Base de datos montada y config.php creado.</div>
      <ul>
        <li>Textos y ajustes: <?= $counts['settings'] ?></li>
        <li>Servicios: <?= $counts['services'] ?></li>
        <li>Proyectos: <?= $counts['projects'] ?></li>
        <li>Opiniones: <?= $counts['reviews'] ?></li>
      </ul>
      <?php if ($selfDeleted): ?>
        <div class="msg msg--ok">Este instalador se eliminó automáticamente del servidor.</div>
      <?php else: ?>
        <div class="msg msg--warn">No pude borrarme solo: elimina <code>install.php</code> del servidor ahora.</div>
      <?php endif; ?>
      <a class="next" href="dyp-admin/">Crear el usuario del panel →</a>

    <?php else: ?>
      <h1>Conectar la base de datos</h1>
      <p class="hint">Datos de cPanel → MySQL Databases. En hosting compartido el servidor suele ser <b>localhost</b>.</p>
      <?php if ($error): ?><div class="msg msg--err"><?= esc($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <label>Servidor <input type="text" name="db_host" value="<?= esc($_POST['db_host'] ?? 'localhost') ?>"></label>
        <label>Puerto <input type="text" name="db_port" value="<?= esc($_POST['db_port'] ?? '3306') ?>"></label>
        <label>Nombre de la base <input type="text" name="db_name" value="<?= esc($_POST['db_name'] ?? '') ?>" required></label>
        <label>Usuario <input type="text" name="db_user" value="<?= esc($_POST['db_user'] ?? '') ?>" required></label>
        <label>Contraseña <input type="password" name="db_pass"></label>
        <button type="submit">Instalar</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
