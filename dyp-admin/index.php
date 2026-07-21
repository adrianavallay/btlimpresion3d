<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
csrf_check();

if (is_logged_in()) {
    redirect('dashboard.php');
}

$setupMode = admin_count() === 0;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if ($setupMode) {
        $pass2 = (string) ($_POST['password2'] ?? '');
        if ($user === '' || strlen($user) < 3) {
            $error = 'El usuario debe tener al menos 3 caracteres.';
        } elseif (strlen($pass) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($pass !== $pass2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $st = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $st->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
            login_attempt($user, $pass);
            redirect('dashboard.php');
        }
    } else {
        if (login_attempt($user, $pass)) {
            redirect('dashboard.php');
        }
        $error = 'Usuario o contraseña incorrectos.';
        if (($_SESSION['login_fails'] ?? 0) >= 6) {
            $error = 'Demasiados intentos. Espera 10 minutos.';
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $setupMode ? 'Configurar admin' : 'Acceso' ?> · Bolivians</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
  <script src="assets/admin.js" defer></script>
</head>
<body class="login-body">
  <form method="post" class="login-card" autocomplete="off">
    <?= csrf_field() ?>
    <div class="login-brand"><span class="sidebar__mark">B</span> Bolivians<em>Reformes</em></div>
    <?php if ($setupMode): ?>
      <h1>Crear administrador</h1>
      <p class="login-hint">Primera vez: elige el usuario y contraseña que usará el panel.</p>
    <?php else: ?>
      <h1>Panel de contenidos</h1>
    <?php endif; ?>
    <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
    <label>Usuario
      <input type="text" name="username" required minlength="3" autofocus>
    </label>
    <label>Contraseña
      <input type="password" name="password" required <?= $setupMode ? 'minlength="8"' : '' ?>>
    </label>
    <?php if ($setupMode): ?>
    <label>Repite la contraseña
      <input type="password" name="password2" required minlength="8">
    </label>
    <?php endif; ?>
    <button type="submit" class="btn-primary"><?= $setupMode ? 'Crear y entrar' : 'Entrar' ?></button>
  </form>
</body>
</html>
