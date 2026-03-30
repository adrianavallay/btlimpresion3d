<?php
// db_installer.php — Instalador de base de datos standalone
// Subir al servidor destino y abrir en el navegador
// ELIMINAR después de usar

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// PASO 3 — Procesar importación
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? 'localhost');
    $db   = trim($_POST['db'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    if (empty($db) || empty($user)) {
        $error = 'Completá todos los campos obligatorios.';
        $step = 2;
    } elseif (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== 0) {
        $error = 'No se recibió el archivo SQL.';
        $step = 2;
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$db};charset=utf8mb4",
                $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
            if (!$sql_content) throw new Exception('No se pudo leer el archivo SQL');

            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            $statements = array_filter(
                array_map('trim', explode(";\n", $sql_content)),
                fn($s) => strlen($s) > 5 && !str_starts_with($s, '--')
            );

            $count = 0;
            foreach ($statements as $stmt) {
                if (trim($stmt)) {
                    $pdo->exec($stmt);
                    $count++;
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            $success = "Base de datos importada correctamente. {$count} operaciones ejecutadas.";
            $step = 4;

        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            $step = 2;
        }
    }
}

// Probar conexión via AJAX
if (isset($_POST['test_connection'])) {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO(
            "mysql:host={$_POST['host']};dbname={$_POST['db']};charset=utf8mb4",
            $_POST['user'], $_POST['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo json_encode(['ok' => true, 'msg' => 'Conexión exitosa']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DB Installer</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #f5f5f5;
         color: #111; min-height: 100vh; }
  .wrap { max-width: 560px; margin: 40px auto; padding: 0 20px; }
  .header { text-align: center; margin-bottom: 32px; }
  .header h1 { font-size: 1.5rem; font-weight: 700; }
  .header p  { color: #666; font-size: 0.9rem; margin-top: 4px; }
  .steps { display: flex; gap: 8px; margin-bottom: 32px; }
  .step-dot { flex: 1; height: 4px; border-radius: 2px; background: #e0e0e0; }
  .step-dot.active { background: #059669; }
  .card { background: #fff; border: 1px solid #e0e0e0;
          border-radius: 12px; padding: 32px; }
  .card h2 { font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; }
  .card p  { color: #666; font-size: 0.88rem; margin-bottom: 24px; line-height: 1.6; }
  label { display: block; font-size: 0.78rem; font-weight: 700;
          text-transform: uppercase; letter-spacing: 0.06em;
          color: #666; margin-bottom: 6px; margin-top: 16px; }
  input[type=text], input[type=password], input[type=file] {
    width: 100%; padding: 10px 14px; border: 1.5px solid #e0e0e0;
    border-radius: 8px; font-size: 0.9rem; color: #111;
  }
  input:focus { outline: none; border-color: #059669; }
  .btn {
    display: block; width: 100%; padding: 14px;
    background: #059669; color: #fff; border: none;
    border-radius: 8px; font-size: 0.88rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em;
    cursor: pointer; margin-top: 24px; text-align: center;
    text-decoration: none;
  }
  .btn:hover { background: #047857; }
  .btn-outline { background: transparent; border: 1.5px solid #e0e0e0;
                 color: #666; }
  .alert { padding: 12px 16px; border-radius: 8px;
           font-size: 0.88rem; margin-bottom: 20px; }
  .alert-err { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; }
  .alert-ok  { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
  .test-result { font-size: 0.85rem; margin-top: 8px; padding: 8px 12px;
                 border-radius: 6px; display: none; }
  .warning { background: #fffbeb; border: 1px solid #fcd34d;
             border-left: 3px solid #f59e0b; padding: 12px 16px;
             border-radius: 8px; font-size: 0.85rem; color: #92400e;
             margin-top: 20px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>DB Installer</h1>
    <p>Importar base de datos &mdash; BTL Impresi&oacute;n 3D</p>
  </div>

  <!-- Progress -->
  <div class="steps">
    <div class="step-dot <?= $step >= 1 ? 'active' : '' ?>"></div>
    <div class="step-dot <?= $step >= 2 ? 'active' : '' ?>"></div>
    <div class="step-dot <?= $step >= 3 ? 'active' : '' ?>"></div>
    <div class="step-dot <?= $step >= 4 ? 'active' : '' ?>"></div>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- PASO 1 -->
  <?php if ($step === 1): ?>
  <div class="card">
    <h2>Paso 1 &mdash; Bienvenida</h2>
    <p>Este instalador importar&aacute; la base de datos en el nuevo servidor.
       Necesit&aacute;s tener a mano el archivo .sql y las credenciales de la nueva DB.</p>
    <div style="font-size:0.85rem;color:#555;line-height:2;">
      <div><?= version_compare(PHP_VERSION, '8.0', '>=') ? '&#10004;' : '&#10008;' ?> PHP <?= PHP_VERSION ?></div>
      <div><?= extension_loaded('pdo_mysql') ? '&#10004;' : '&#10008;' ?> PDO MySQL</div>
      <div><?= is_writable(__DIR__) ? '&#10004;' : '&#10008;' ?> Escritura en directorio</div>
    </div>
    <a href="?step=2" class="btn">COMENZAR &rarr;</a>
  </div>

  <!-- PASO 2 -->
  <?php elseif ($step === 2): ?>
  <div class="card">
    <h2>Paso 2 &mdash; Configuraci&oacute;n</h2>
    <p>Ingres&aacute; las credenciales de la base de datos en el nuevo servidor
       y sub&iacute; el archivo .sql que exportaste.</p>

    <form method="POST" action="?step=3" enctype="multipart/form-data">
      <label>Host</label>
      <input type="text" name="host" value="localhost" required>

      <label>Nombre de la base de datos *</label>
      <input type="text" name="db" placeholder="nueva_db" required>

      <label>Usuario *</label>
      <input type="text" name="user" placeholder="usuario_db" required>

      <label>Contrase&ntilde;a</label>
      <input type="password" name="pass" placeholder="contrase&ntilde;a">

      <button type="button" onclick="testConnection(this)"
              class="btn btn-outline" style="margin-top:12px;">
        Probar conexi&oacute;n
      </button>
      <div id="testResult" class="test-result"></div>

      <label style="margin-top:24px;">Archivo .sql exportado *</label>
      <input type="file" name="sql_file" accept=".sql" required>

      <button type="submit" class="btn">IMPORTAR BASE DE DATOS &rarr;</button>
    </form>
  </div>

  <!-- PASO 4 — Éxito -->
  <?php elseif ($step === 4): ?>
  <div class="card">
    <h2>&#10004; Importaci&oacute;n completada</h2>
    <?php if ($success): ?>
    <div class="alert alert-ok"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <p>La base de datos fue importada correctamente.</p>
    <p style="margin-top:12px;">Pr&oacute;ximos pasos:</p>
    <div style="font-size:0.85rem;color:#555;line-height:2;margin-top:8px;">
      <div>1. Configur&aacute; config.php con las credenciales nuevas</div>
      <div>2. Hac&eacute; push a GitHub &rarr; deploy autom&aacute;tico</div>
      <div>3. Elimin&aacute; este archivo del servidor</div>
    </div>
    <div class="warning">
      &#9888; <strong>Importante:</strong> Elimin&aacute; db_installer.php del servidor ahora.
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
function testConnection(btn) {
  var form = btn.closest('form');
  var data = new FormData();
  data.append('test_connection', '1');
  data.append('host', form.host.value);
  data.append('db', form.db.value);
  data.append('user', form.user.value);
  data.append('pass', form.pass.value);

  btn.textContent = 'Probando...';
  btn.disabled = true;

  fetch('db_installer.php', { method: 'POST', body: data })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var el = document.getElementById('testResult');
      el.style.display = 'block';
      el.style.background = d.ok ? '#f0fdf4' : '#fef2f2';
      el.style.border = d.ok ? '1px solid #86efac' : '1px solid #fca5a5';
      el.style.color = d.ok ? '#166534' : '#dc2626';
      el.textContent = d.msg;
    })
    .finally(function() {
      btn.textContent = 'Probar conexion';
      btn.disabled = false;
    });
}
</script>
</body>
</html>
