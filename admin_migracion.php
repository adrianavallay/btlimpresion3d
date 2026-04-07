<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$admin_page = 'migracion';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Migraci&oacute;n &mdash; Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

<!-- PAGE HEADER -->
<div style="margin-bottom:24px;">
    <h1 style="font-family:var(--font-serif);font-size:1.6rem;font-weight:600;color:#111;margin:0 0 4px;">Migraci&oacute;n</h1>
    <p style="font-size:0.85rem;color:#999;margin:0;">Descarg&aacute; la base de datos y los archivos del proyecto por separado</p>
</div>

<!-- 2 CARDS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">

  <!-- CARD DB -->
  <div style="background:#fff;border:1px solid #e8e8e8;padding:32px;border-radius:12px;">
    <div style="font-size:2.5rem;margin-bottom:16px;">&#128451;</div>
    <h3 style="font-size:1.1rem;font-weight:700;margin:0 0 8px;">Base de datos</h3>
    <p style="color:#666;font-size:0.88rem;margin-bottom:24px;line-height:1.6;">
      Descarg&aacute; un archivo .sql con todas las tablas y datos.
      Importalo en el nuevo servidor via phpMyAdmin.
    </p>
    <ul style="font-size:0.82rem;color:#555;margin-bottom:24px;padding-left:20px;line-height:2;">
      <li>Todas las tablas del sistema</li>
      <li>Productos, pedidos, clientes</li>
      <li>Configuraciones y slides</li>
      <li>Credenciales NO incluidas</li>
    </ul>
    <form method="POST" action="export_db.php">
      <button type="submit" style="
        width:100%;padding:14px;background:#111;color:#fff;
        border:none;border-radius:8px;font-size:0.85rem;
        font-weight:700;text-transform:uppercase;
        letter-spacing:0.06em;cursor:pointer;">
        &#11015; Descargar .sql
      </button>
    </form>
  </div>

  <!-- CARD ARCHIVOS -->
  <div style="background:#fff;border:1px solid #e8e8e8;padding:32px;border-radius:12px;">
    <div style="font-size:2.5rem;margin-bottom:16px;">&#128193;</div>
    <h3 style="font-size:1.1rem;font-weight:700;margin:0 0 8px;">Archivos del proyecto</h3>
    <p style="color:#666;font-size:0.88rem;margin-bottom:24px;line-height:1.6;">
      Descarg&aacute; un .zip con todos los archivos.
      Descomprimilo en local y pushe&aacute; a GitHub.
    </p>
    <ul style="font-size:0.82rem;color:#555;margin-bottom:24px;padding-left:20px;line-height:2;">
      <li>Todo el c&oacute;digo PHP/CSS/JS</li>
      <li>Im&aacute;genes y uploads</li>
      <li>config.php con credenciales en blanco</li>
      <li>Sin carpeta /bkp/ ni /.git/</li>
    </ul>
    <form method="POST" action="export_files.php">
      <button type="submit" style="
        width:100%;padding:14px;background:#7c3aed;color:#fff;
        border:none;border-radius:8px;font-size:0.85rem;
        font-weight:700;text-transform:uppercase;
        letter-spacing:0.06em;cursor:pointer;">
        &#11015; Descargar .zip
      </button>
    </form>
  </div>

</div>

<!-- CARD INSTALLER -->
<div style="background:#fff;border:1px solid #e8e8e8;padding:32px;
            border-radius:12px;margin-top:24px;">
  <div style="font-size:2.5rem;margin-bottom:16px;">&#9881;&#65039;</div>
  <h3 style="font-size:1.1rem;font-weight:700;margin:0 0 8px;">
    Installer de base de datos
  </h3>
  <p style="color:#666;font-size:0.88rem;margin-bottom:16px;line-height:1.6;">
    Descarg&aacute; este instalador, subilo al nuevo servidor y
    segu&iacute; los pasos para importar la base de datos autom&aacute;ticamente.
    No necesit&aacute;s acceso a phpMyAdmin.
  </p>
  <ul style="font-size:0.82rem;color:#555;margin-bottom:24px;
             padding-left:20px;line-height:2;">
    <li>Sub&iacute; el installer al nuevo servidor via File Manager</li>
    <li>Abrilo en el navegador</li>
    <li>Configur&aacute; las credenciales de la nueva DB</li>
    <li>Sub&iacute; el .sql y lo importa autom&aacute;ticamente</li>
    <li>Elimin&aacute; el installer despu&eacute;s de usarlo</li>
  </ul>
  <a href="db_installer.php" download style="
    display:block;text-align:center;padding:14px;
    background:#059669;color:#fff;border-radius:8px;
    font-size:0.85rem;font-weight:700;text-transform:uppercase;
    letter-spacing:0.06em;text-decoration:none;">
    &#11015; Descargar db_installer.php
  </a>
</div>

<!-- Instrucciones -->
<div style="background:#f9fafb;border:1px solid #e8e8e8;
            border-radius:12px;padding:24px;margin-top:24px;">
  <h4 style="font-size:0.9rem;font-weight:700;margin:0 0 16px;">
    &#128203; C&oacute;mo migrar a un nuevo servidor
  </h4>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div>
      <p style="font-size:0.82rem;font-weight:700;color:#7c3aed;margin:0 0 8px;">
        BASE DE DATOS
      </p>
      <ol style="font-size:0.82rem;color:#555;line-height:2;margin:0;padding-left:20px;">
        <li>Descarg&aacute; el .sql</li>
        <li>En el nuevo cPanel &rarr; phpMyAdmin</li>
        <li>Seleccion&aacute; la nueva DB</li>
        <li>Importar &rarr; subir el .sql</li>
      </ol>
    </div>
    <div>
      <p style="font-size:0.82rem;font-weight:700;color:#111;margin:0 0 8px;">
        ARCHIVOS
      </p>
      <ol style="font-size:0.82rem;color:#555;line-height:2;margin:0;padding-left:20px;">
        <li>Descarg&aacute; el .zip</li>
        <li>Descomprim&iacute; en local en la carpeta del repo</li>
        <li>Edit&aacute; config.php con las credenciales nuevas</li>
        <li>git add . &rarr; commit &rarr; push &rarr; deploy autom&aacute;tico</li>
      </ol>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

</body>
</html>
