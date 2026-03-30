<?php
// ============================================================
// ADMIN — REDES SOCIALES
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$db = pdo();

// ── Ensure table & seed ──
$db->exec("CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    grupo VARCHAR(50) DEFAULT 'general',
    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("INSERT IGNORE INTO configuracion (clave, valor, grupo) VALUES
    ('rs_instagram',  '', 'redes_sociales'),
    ('rs_facebook',   '', 'redes_sociales'),
    ('rs_twitter',    '', 'redes_sociales'),
    ('rs_tiktok',     '', 'redes_sociales'),
    ('rs_youtube',    '', 'redes_sociales'),
    ('rs_whatsapp',   '', 'redes_sociales'),
    ('rs_linkedin',   '', 'redes_sociales'),
    ('rs_pinterest',  '', 'redes_sociales')");

$msg_ok = '';

// ── Save ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_redes'])) {
    csrf_check();
    $redes = ['rs_instagram','rs_facebook','rs_tiktok','rs_youtube',
              'rs_whatsapp','rs_twitter','rs_linkedin','rs_pinterest'];
    foreach ($redes as $red) {
        set_config($red, trim($_POST[$red] ?? ''), 'redes_sociales');
    }
    $msg_ok = 'Redes sociales guardadas correctamente.';
}

// ── Load current values ──
$redes_config = [
    'rs_instagram' => ['label' => 'Instagram',   'icon' => '&#128247;', 'placeholder' => 'https://instagram.com/tu_usuario'],
    'rs_facebook'  => ['label' => 'Facebook',     'icon' => '&#128216;', 'placeholder' => 'https://facebook.com/tu_pagina'],
    'rs_tiktok'    => ['label' => 'TikTok',       'icon' => '&#127925;', 'placeholder' => 'https://tiktok.com/@tu_usuario'],
    'rs_youtube'   => ['label' => 'YouTube',      'icon' => '&#9654;&#65039;',  'placeholder' => 'https://youtube.com/@tu_canal'],
    'rs_whatsapp'  => ['label' => 'WhatsApp',     'icon' => '&#128172;', 'placeholder' => 'https://wa.me/5491112345678', 'hint' => 'Formato: https://wa.me/549 + numero sin espacios'],
    'rs_twitter'   => ['label' => 'Twitter / X',  'icon' => '&#128038;', 'placeholder' => 'https://x.com/tu_usuario'],
    'rs_linkedin'  => ['label' => 'LinkedIn',     'icon' => '&#128188;', 'placeholder' => 'https://linkedin.com/company/tu_empresa'],
    'rs_pinterest' => ['label' => 'Pinterest',    'icon' => '&#128204;', 'placeholder' => 'https://pinterest.com/tu_usuario'],
];

$admin_page = 'redes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Redes Sociales — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

    <div class="page-header">
        <h1>Redes Sociales</h1>
        <p class="page-subtitle">Configura los links de tus redes sociales. Se mostraran automaticamente en la tienda.</p>
    </div>

    <?php if ($msg_ok): ?>
        <div class="rs-alert rs-alert--ok"><?= sanitize($msg_ok) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

        <div class="rs-cards">
            <?php foreach ($redes_config as $key => $red): ?>
            <div class="rs-card <?= get_config($key) ? 'rs-card--active' : '' ?>">
                <div class="rs-card-header">
                    <span class="rs-card-icon"><?= $red['icon'] ?></span>
                    <span class="rs-card-label"><?= $red['label'] ?></span>
                    <?php if (get_config($key)): ?>
                        <span class="rs-card-status">Activa</span>
                    <?php endif; ?>
                </div>
                <div class="rs-card-body">
                    <input type="url" name="<?= $key ?>"
                           placeholder="<?= $red['placeholder'] ?>"
                           value="<?= sanitize(get_config($key)) ?>">
                    <?php if (!empty($red['hint'])): ?>
                        <small><?= $red['hint'] ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="rs-footer">
            <button type="submit" name="guardar_redes" class="btn btn-primary">Guardar redes sociales</button>
            <span class="rs-footer-hint">Las redes sin URL no se mostraran en la tienda.</span>
        </div>
    </form>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

</body>
</html>
