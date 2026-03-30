<?php if (!defined('SITE_NAME')) require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if (isset($seo_title) && isset($seo_desc)): ?>
<?= seo_tags($seo_title, $seo_desc, $seo_image ?? '', $seo_type ?? 'website') ?>
<?php else: ?>
<title><?= isset($page_title) ? sanitize($page_title) . ' — ' : '' ?><?= SITE_NAME ?></title>
<?php endif; ?>
<meta name="csrf-token" content="<?= csrf_token() ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/tienda.css?v=<?= filemtime(__DIR__.'/../css/tienda.css') ?>">
<script>
(function(){var t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-theme',t);})();
window.SITE_URL = '<?= SITE_URL ?>';
</script>
<?php if (isset($extra_css)): ?><link rel="stylesheet" href="<?= $extra_css ?>"><?php endif; ?>
</head>
<body>

<div id="toastContainer" class="toast-container"></div>

<nav class="navbar" id="navbar">
  <div class="nav-inner">
    <a href="<?= SITE_URL ?>/" class="nav-logo"><?= SITE_NAME ?></a>

    <ul class="nav-links" id="navLinks">
      <li><a href="<?= SITE_URL ?>/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Inicio</a></li>
      <li><a href="<?= url_pagina('tienda') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'tienda.php' ? 'active' : '' ?>">Tienda</a></li>
      <li class="nav-dropdown">
        <a href="<?= SITE_URL ?>/#categorias">Categorias <span class="nav-dropdown-arrow">&#9662;</span></a>
        <?php
        try {
            $_nav_cats = pdo()->query("SELECT c.*, (SELECT COUNT(*) FROM categorias WHERE padre_id = c.id AND activa = 1) as tiene_hijas FROM categorias c WHERE c.padre_id IS NULL AND c.activa = 1 ORDER BY c.orden ASC")->fetchAll();
        } catch (Exception $e) { $_nav_cats = []; }
        if (!empty($_nav_cats)):
        ?>
        <ul class="nav-dropdown-menu">
          <?php foreach ($_nav_cats as $nc): ?>
          <li class="<?= $nc['tiene_hijas'] ? 'has-children' : '' ?>">
            <a href="<?= url_categoria($nc['slug']) ?>"><?= sanitize($nc['nombre']) ?><?= $nc['tiene_hijas'] ? ' &#8250;' : '' ?></a>
            <?php if ($nc['tiene_hijas']): ?>
            <ul class="nav-submenu">
              <?php
              $hijas = pdo()->prepare("SELECT * FROM categorias WHERE padre_id = ? AND activa = 1 ORDER BY nombre");
              $hijas->execute([$nc['id']]);
              foreach ($hijas->fetchAll() as $h):
              ?>
              <li><a href="<?= url_categoria($h['slug']) ?>"><?= sanitize($h['nombre']) ?></a></li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </li>
      <li><a href="<?= SITE_URL ?>/#contacto">Contacto</a></li>
    </ul>

    <div class="nav-actions">
      <?php if (is_cliente()): ?>
        <a href="<?= url_pagina('wishlist') ?>" class="nav-icon" title="Favoritos">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        </a>
      <?php endif; ?>

      <a href="<?= url_pagina('carrito') ?>" class="nav-icon" id="cartIcon" title="Carrito">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        <span class="cart-badge" id="cartBadge"><?= cart_count() ?></span>
      </a>

      <?php if (is_cliente()): ?>
        <a href="<?= url_pagina('mi-cuenta') ?>" class="nav-icon" title="Mi cuenta">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
      <?php else: ?>
        <a href="<?= url_pagina('login') ?>" class="nav-icon" title="Ingresar">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
      <?php endif; ?>

      <button class="theme-toggle" id="themeToggle" aria-label="Cambiar tema" title="Modo claro/oscuro">
        <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
      </button>

      <button class="nav-hamburger" id="menuToggle" aria-label="Menu">
        <span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<div class="mobile-overlay" id="mobileOverlay"></div>
