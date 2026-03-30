<?php
/**
 * Admin Sidebar Header — included by all admin pages.
 *
 * Before including, set $admin_page to the page key:
 *   'dashboard','productos','categorias','pedidos','clientes','cupones','reportes','config'
 */
if (!isset($admin_page)) $admin_page = '';

function activePage($page) {
    global $admin_page;
    return ($admin_page === $page) ? 'active' : '';
}
?>

<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
      <a href="admin.php" class="sidebar-logo"><img src="logo-admin.png" alt="DyP Consultora" style="width:100%;max-width:200px;height:auto;"></a>
      <button class="sidebar-close" id="sidebarClose">&times;</button>
    </div>

    <nav class="sidebar-nav">
      <a href="admin.php" class="sidebar-link <?= activePage('dashboard') ?>" data-tooltip="Dashboard">
        <span class="sidebar-icon material-symbols-outlined">dashboard</span> Dashboard
      </a>

      <div class="sidebar-group">
        <div class="sidebar-group-title">Productos</div>
        <a href="admin_productos.php" class="sidebar-link <?= activePage('productos') ?>" data-tooltip="Productos">
          <span class="sidebar-icon material-symbols-outlined">inventory_2</span> Productos
        </a>
        <a href="admin_categorias.php" class="sidebar-link <?= activePage('categorias') ?>" data-tooltip="Categorías">
          <span class="sidebar-icon material-symbols-outlined">category</span> Categor&iacute;as
        </a>
        <a href="admin_slides.php" class="sidebar-link <?= activePage('slides') ?>" data-tooltip="Slider Home">
          <span class="sidebar-icon material-symbols-outlined">slideshow</span> Slider Home
        </a>
      </div>

      <div class="sidebar-group">
        <div class="sidebar-group-title">Ventas</div>
        <a href="admin_pedidos.php" class="sidebar-link <?= activePage('pedidos') ?>" data-tooltip="Pedidos">
          <span class="sidebar-icon material-symbols-outlined">shopping_bag</span> Pedidos
        </a>
        <a href="admin_clientes.php" class="sidebar-link <?= activePage('clientes') ?>" data-tooltip="Clientes">
          <span class="sidebar-icon material-symbols-outlined">group</span> Clientes
        </a>
        <a href="admin_cupones.php" class="sidebar-link <?= activePage('cupones') ?>" data-tooltip="Cupones">
          <span class="sidebar-icon material-symbols-outlined">sell</span> Cupones
        </a>
      </div>

      <div class="sidebar-group">
        <div class="sidebar-group-title">An&aacute;lisis</div>
        <a href="admin_reportes.php" class="sidebar-link <?= activePage('reportes') ?>" data-tooltip="Reportes">
          <span class="sidebar-icon material-symbols-outlined">analytics</span> Reportes
        </a>
      </div>

      <div class="sidebar-group">
        <div class="sidebar-group-title">Sistema</div>
        <div class="sidebar-dropdown <?= in_array($admin_page, ['config', 'backups', 'redes', 'migracion']) ? 'open' : '' ?>">
          <button class="sidebar-link sidebar-dropdown-toggle <?= in_array($admin_page, ['config', 'backups', 'redes', 'migracion']) ? 'active' : '' ?>">
            <span class="sidebar-icon material-symbols-outlined">settings</span> Configuraci&oacute;n
            <span class="sidebar-arrow">&#9656;</span>
          </button>
          <div class="sidebar-dropdown-menu">
            <a href="admin_config.php" class="sidebar-link sidebar-sublink <?= activePage('config') ?>">
              <span class="sidebar-icon material-symbols-outlined">tune</span> General
            </a>
            <a href="admin_redes.php" class="sidebar-link sidebar-sublink <?= activePage('redes') ?>">
              <span class="sidebar-icon material-symbols-outlined">share</span> Redes Sociales
            </a>
            <a href="admin_backups.php" class="sidebar-link sidebar-sublink <?= activePage('backups') ?>">
              <span class="sidebar-icon material-symbols-outlined">backup</span> Backups
            </a>
            <a href="admin_migracion.php" class="sidebar-link sidebar-sublink <?= activePage('migracion') ?>">
              <span class="sidebar-icon material-symbols-outlined">swap_horiz</span> Migraci&oacute;n
            </a>
          </div>
        </div>
        <a href="admin.php?logout=1" class="sidebar-link sidebar-logout">
          <span class="sidebar-icon material-symbols-outlined">logout</span> Cerrar sesi&oacute;n
        </a>
      </div>
    </nav>

    <!-- Collapse toggle -->
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Colapsar menú">
      <span class="material-symbols-outlined" id="collapseIcon">chevron_left</span>
    </button>
  </aside>

  <!-- OVERLAY mobile -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- CONTENIDO PRINCIPAL -->
  <div class="admin-main">

    <!-- TOPBAR mobile -->
    <header class="admin-topbar">
      <button class="sidebar-hamburger" id="sidebarToggle">
        <span></span><span></span><span></span>
      </button>
      <span class="topbar-title"><img src="logo-admin.png" alt="DyP Consultora" style="height:42px;width:auto;"></span>
    </header>

    <div class="admin-content">
