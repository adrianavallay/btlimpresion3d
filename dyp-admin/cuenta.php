<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/admin.php';
require_login();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string) ($_POST['current'] ?? '');
    $newUser = trim((string) ($_POST['username'] ?? ''));
    $newPass = (string) ($_POST['password'] ?? '');
    $newPass2 = (string) ($_POST['password2'] ?? '');

    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($current, (string) $hash)) {
        flash('La contraseña actual no es correcta.');
    } elseif ($newPass !== '' && strlen($newPass) < 8) {
        flash('La contraseña nueva debe tener al menos 8 caracteres.');
    } elseif ($newPass !== $newPass2) {
        flash('Las contraseñas nuevas no coinciden.');
    } else {
        if ($newUser !== '' && strlen($newUser) >= 3) {
            db()->prepare('UPDATE users SET username = ? WHERE id = ?')
                ->execute([$newUser, $_SESSION['uid']]);
            $_SESSION['uname'] = $newUser;
        }
        if ($newPass !== '') {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['uid']]);
        }
        flash('Cuenta actualizada.');
    }
    redirect('cuenta.php');
}

admin_header('Mi cuenta', 'cuenta');
?>
    <form method="post" class="form" autocomplete="off">
      <?= csrf_field() ?>
      <fieldset>
        <legend>Cambiar usuario o contraseña</legend>
        <label>Contraseña actual (obligatoria)
          <input type="password" name="current" required>
        </label>
        <div class="row3">
          <label>Nuevo usuario (opcional)
            <input type="text" name="username" value="<?= e($_SESSION['uname'] ?? '') ?>" minlength="3">
          </label>
          <label>Nueva contraseña (opcional)
            <input type="password" name="password" minlength="8">
          </label>
          <label>Repite la nueva contraseña
            <input type="password" name="password2" minlength="8">
          </label>
        </div>
      </fieldset>
      <button type="submit" class="btn-primary">Guardar</button>
    </form>
<?php admin_footer();
