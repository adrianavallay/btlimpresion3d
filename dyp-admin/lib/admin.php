<?php
/**
 * Bootstrap del panel: sesión, autenticación, CSRF y layout.
 * Incluir al inicio de cada página del admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/content.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_name('DYPADMIN');
session_start();

/* ── CSRF ─────────────────────────────── */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sesión caducada. Vuelve atrás e inténtalo de nuevo.');
    }
}

/* ── Autenticación ────────────────────── */

function admin_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['uid']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function login_attempt(string $user, string $pass): bool
{
    // Bloqueo básico: 6 fallos → 10 minutos de espera
    $fails = $_SESSION['login_fails'] ?? 0;
    $until = $_SESSION['login_lock_until'] ?? 0;
    if ($fails >= 6 && time() < $until) {
        return false;
    }

    $st = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $st->execute([$user]);
    $row = $st->fetch();

    if ($row && password_verify($pass, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $row['id'];
        $_SESSION['uname'] = $user;
        unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
        return true;
    }

    $_SESSION['login_fails'] = $fails + 1;
    if ($_SESSION['login_fails'] >= 6) {
        $_SESSION['login_lock_until'] = time() + 600;
    }
    usleep(400000); // frena fuerza bruta
    return false;
}

/** Ruta de imagen para mostrar en el admin (absoluta o relativa a la raíz). */
function img_src(string $p): string
{
    return str_starts_with($p, 'http') ? $p : '../' . $p;
}

/* ── Mensajes flash ───────────────────── */

function flash(string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ── Layout ───────────────────────────── */

function admin_header(string $title, string $active = ''): void
{
    $items = [
        'dashboard' => ['dashboard.php', 'Inicio'],
        'textos'    => ['textos.php', 'Textos y fotos'],
        'servicios' => ['servicios.php', 'Servicios'],
        'proyectos' => ['proyectos.php', 'Proyectos'],
        'opiniones' => ['opiniones.php', 'Opiniones'],
        'contacto'  => ['contacto.php', 'Contacto'],
        'cuenta'    => ['cuenta.php', 'Mi cuenta'],
    ];
    ?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · Admin Bolivians</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='18' fill='%2316130F'/><text x='50' y='68' font-size='52' font-family='Arial' font-weight='900' fill='%23D97706' text-anchor='middle'>B</text></svg>">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar__brand">
      <span class="sidebar__mark">B</span>
      <span>Bolivians<br><small>Panel de contenidos</small></span>
    </div>
    <nav class="sidebar__nav">
      <?php foreach ($items as $key => [$href, $label]): ?>
        <a href="<?= e($href) ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar__foot">
      <a href="../" target="_blank">Ver la web ↗</a>
      <a href="logout.php" class="logout">Cerrar sesión</a>
    </div>
  </aside>
  <main class="content">
    <h1><?= e($title) ?></h1>
    <?php if ($m = flash()): ?><div class="flash"><?= e($m) ?></div><?php endif; ?>
<?php
}

function admin_footer(): void
{
    echo "\n  </main>\n</div>\n</body>\n</html>\n";
}
