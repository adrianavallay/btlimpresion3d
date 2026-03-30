<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// Already logged in? Go to account
if (is_cliente()) {
    redirect('mi-cuenta.php');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            flash('error', 'Completá todos los campos');
        } else {
            $res = cliente_login($email, $password);
            if ($res['ok']) {
                flash('success', $res['mensaje']);
                redirect('mi-cuenta.php');
            } else {
                flash('error', $res['mensaje']);
            }
        }
    }

    if ($action === 'register') {
        $nombre   = trim($_POST['nombre'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (!$nombre || !$email || !$password) {
            flash('error', 'Completá todos los campos obligatorios');
        } elseif ($password !== $confirm) {
            flash('error', 'Las contraseñas no coinciden');
        } elseif (strlen($password) < 6) {
            flash('error', 'La contraseña debe tener al menos 6 caracteres');
        } else {
            $res = cliente_register($nombre, $email, $password, $telefono);
            if ($res['ok']) {
                flash('success', $res['mensaje']);
                redirect('mi-cuenta.php');
            } else {
                flash('error', $res['mensaje']);
            }
        }
    }
}

$flash_error   = flash('error');
$flash_success = flash('success');
$page_title = 'Iniciar sesión';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">

        <?php if ($flash_error): ?>
            <div class="flash-msg flash-error"><?= sanitize($flash_error) ?></div>
        <?php endif; ?>

        <?php if ($flash_success): ?>
            <div class="flash-msg flash-success"><?= sanitize($flash_success) ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="auth-tabs">
            <button class="auth-tab active" data-tab="login">Ingresar</button>
            <button class="auth-tab" data-tab="register">Registrarse</button>
        </div>

        <!-- LOGIN FORM -->
        <form class="auth-form active" id="form-login" action="login.php" method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="login">

            <div class="auth-field">
                <label for="login-email">Email</label>
                <input type="email" id="login-email" name="email" required placeholder="tu@email.com" autocomplete="email">
            </div>

            <div class="auth-field">
                <label for="login-password">Contraseña</label>
                <input type="password" id="login-password" name="password" required placeholder="Tu contraseña" autocomplete="current-password">
            </div>

            <button type="submit" class="auth-submit">Iniciar sesión</button>

            <p class="auth-link">
                <a href="#">¿Olvidaste tu contraseña?</a>
            </p>
        </form>

        <!-- REGISTER FORM -->
        <form class="auth-form" id="form-register" action="login.php" method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="register">

            <div class="auth-field">
                <label for="reg-nombre">Nombre completo *</label>
                <input type="text" id="reg-nombre" name="nombre" required placeholder="Tu nombre completo" autocomplete="name">
            </div>

            <div class="auth-field">
                <label for="reg-email">Email *</label>
                <input type="email" id="reg-email" name="email" required placeholder="tu@email.com" autocomplete="email">
            </div>

            <div class="auth-field">
                <label for="reg-telefono">Teléfono</label>
                <input type="tel" id="reg-telefono" name="telefono" placeholder="+54 11 1234-5678" autocomplete="tel">
            </div>

            <div class="auth-field">
                <label for="reg-password">Contraseña *</label>
                <input type="password" id="reg-password" name="password" required placeholder="Mínimo 6 caracteres" autocomplete="new-password">
            </div>

            <div class="auth-field">
                <label for="reg-password-confirm">Confirmar contraseña *</label>
                <input type="password" id="reg-password-confirm" name="password_confirm" required placeholder="Repetí tu contraseña" autocomplete="new-password">
            </div>

            <button type="submit" class="auth-submit">Crear cuenta</button>

            <p class="auth-link">
                ¿Ya tenés cuenta? <a href="#" onclick="switchTab('login');return false;">Iniciá sesión</a>
            </p>
        </form>

    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
    document.querySelector('[data-tab="' + tab + '"]').classList.add('active');
    document.getElementById('form-' + tab).classList.add('active');
}

document.querySelectorAll('.auth-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        switchTab(this.dataset.tab);
    });
});

// If register form had an error, show register tab
<?php if (isset($_POST['action']) && $_POST['action'] === 'register'): ?>
    switchTab('register');
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
