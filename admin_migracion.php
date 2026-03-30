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
<title>Migraci&oacute;n — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

<!-- PAGE HEADER -->
<div style="margin-bottom:24px;">
    <h1 style="font-family:var(--font-serif);font-size:1.6rem;font-weight:600;color:#111;margin:0 0 4px;">Migraci&oacute;n</h1>
    <p style="font-size:0.85rem;color:#999;margin:0;">Exporta e importa tu sitio completo en un solo archivo</p>
</div>

<!-- STATUS CARDS -->
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:24px;width:100%;box-sizing:border-box;">
    <div style="display:flex;align-items:center;gap:10px;padding:12px;background:#fff;border:1px solid #e8e8e8;box-sizing:border-box;overflow:hidden;">
        <span style="font-size:1.2rem;flex-shrink:0;">&#128230;</span>
        <div style="min-width:0;overflow:hidden;">
            <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;font-weight:600;">Formato</div>
            <div style="font-size:0.85rem;font-weight:600;color:#333;">.storepack</div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;padding:12px;background:#fff;border:1px solid #e8e8e8;box-sizing:border-box;overflow:hidden;">
        <span style="font-size:1.2rem;flex-shrink:0;">&#128274;</span>
        <div style="min-width:0;overflow:hidden;">
            <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;font-weight:600;">Seguridad</div>
            <div style="font-size:0.85rem;font-weight:600;color:#333;">Credenciales limpias</div>
        </div>
    </div>
</div>

<!-- EXPORTAR -->
<div style="background:#fff;border:1px solid #e8e8e8;margin-bottom:24px;width:100%;box-sizing:border-box;overflow:hidden;">
    <div style="padding:16px 20px 0;display:flex;align-items:center;gap:10px;">
        <span style="font-size:1.4rem;">&#11014;&#65039;</span>
        <h3 style="font-family:var(--font-serif);font-size:1.1rem;font-weight:600;color:#111;margin:0;">Exportar sitio</h3>
    </div>
    <div style="padding:16px 20px 20px;">
        <p style="font-size:0.85rem;color:#666;margin:0 0 16px;line-height:1.6;">
            Genera un archivo <strong>.storepack</strong> con todos los archivos y la base de datos lista para instalar en otro servidor.
        </p>

        <!-- Incluye / No incluye -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:0.8rem;color:#166534;">
                <span style="flex-shrink:0;">&#10003;</span> Base de datos completa
            </div>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:0.8rem;color:#166534;">
                <span style="flex-shrink:0;">&#10003;</span> Archivos PHP/CSS/JS
            </div>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:0.8rem;color:#166534;">
                <span style="flex-shrink:0;">&#10003;</span> Im&aacute;genes y uploads
            </div>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:0.8rem;color:#166534;">
                <span style="flex-shrink:0;">&#10003;</span> Instalador incluido
            </div>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;font-size:0.8rem;color:#991b1b;">
                <span style="flex-shrink:0;">&#10007;</span> Credenciales (seguridad)
            </div>
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;font-size:0.8rem;color:#991b1b;">
                <span style="flex-shrink:0;">&#10007;</span> Backups anteriores
            </div>
        </div>

        <button type="button" id="btnExportar" onclick="exportar()" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px 20px;background:#111;color:#fff;border:none;font-size:0.88rem;font-weight:700;cursor:pointer;font-family:var(--font-sans);text-transform:uppercase;letter-spacing:0.04em;transition:background 0.2s;" onmouseover="this.style.background='#333'" onmouseout="this.style.background='#111'">
            &#11014; Generar y descargar .storepack
        </button>

        <div id="exportProgress" style="display:none;margin-top:16px;">
            <div style="width:100%;background:#f0f0f0;height:6px;overflow:hidden;">
                <div id="progressFill" style="width:0%;height:100%;background:#111;transition:width 0.5s ease;"></div>
            </div>
            <p id="progressText" style="font-size:0.82rem;color:#666;margin:8px 0 0;">Preparando exportaci&oacute;n...</p>
        </div>
    </div>
</div>

<!-- IMPORTAR -->
<div style="background:#fff;border:1px solid #e8e8e8;margin-bottom:24px;width:100%;box-sizing:border-box;overflow:hidden;">
    <div style="padding:16px 20px 0;display:flex;align-items:center;gap:10px;">
        <span style="font-size:1.4rem;">&#11015;&#65039;</span>
        <h3 style="font-family:var(--font-serif);font-size:1.1rem;font-weight:600;color:#111;margin:0;">Importar en otro servidor</h3>
    </div>
    <div style="padding:16px 20px 20px;">
        <p style="font-size:0.85rem;color:#666;margin:0 0 16px;line-height:1.6;">
            Para instalar en un nuevo servidor, segu&iacute; estos pasos:
        </p>

        <!-- Steps -->
        <div style="display:flex;flex-direction:column;gap:0;margin-bottom:20px;">
            <?php
            $steps = [
                'Exporta el sitio desde aqu&iacute; &rarr; descargas el <strong>.storepack</strong>',
                'En el servidor destino, sub&iacute; el archivo <code style="background:#f5f5f5;padding:2px 6px;font-size:0.8rem;">migration_installer.php</code> via FTP o File Manager',
                'Abr&iacute; <code style="background:#f5f5f5;padding:2px 6px;font-size:0.8rem;">https://nuevo-servidor.com/migration_installer.php</code> en el navegador',
                'Segu&iacute; los pasos del instalador &mdash; sub&iacute; el .storepack y configur&aacute; la nueva DB',
                '&iexcl;Listo! Elimin&aacute; el installer.php del nuevo servidor',
            ];
            foreach ($steps as $i => $step):
                $num = $i + 1;
                $isLast = $i === count($steps) - 1;
            ?>
            <div style="display:flex;gap:14px;align-items:flex-start;">
                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                    <div style="width:28px;height:28px;background:<?= $isLast ? '#111' : '#f5f5f5' ?>;color:<?= $isLast ? '#fff' : '#333' ?>;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;border-radius:50%;flex-shrink:0;"><?= $num ?></div>
                    <?php if (!$isLast): ?>
                    <div style="width:1px;height:16px;background:#e0e0e0;"></div>
                    <?php endif; ?>
                </div>
                <div style="padding:4px 0 <?= $isLast ? '0' : '12px' ?>;font-size:0.85rem;color:#444;line-height:1.5;"><?= $step ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="download_installer.php" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 20px;border:1px solid #111;color:#111;text-decoration:none;font-size:0.85rem;font-weight:600;font-family:var(--font-sans);text-transform:uppercase;letter-spacing:0.04em;box-sizing:border-box;transition:all 0.2s;" onmouseover="this.style.background='#111';this.style.color='#fff'" onmouseout="this.style.background='transparent';this.style.color='#111'">
            &#11015; Descargar migration_installer.php
        </a>
    </div>
</div>

<!-- INFO -->
<div style="width:100%;box-sizing:border-box;padding:16px;border-left:3px solid #2563eb;background:#eff6ff;overflow:hidden;">
    <p style="margin:0 0 8px;font-weight:700;color:#1d4ed8;font-size:0.88rem;">&#9432; Informaci&oacute;n</p>
    <p style="margin:0;font-size:0.83rem;color:#1e3a5f;line-height:1.6;word-wrap:break-word;overflow-wrap:break-word;white-space:normal;">El archivo .storepack es un paquete comprimido que incluye toda tu web. Las credenciales de base de datos se limpian autom&aacute;ticamente por seguridad &mdash; deber&aacute;s configurarlas en el servidor destino durante la instalaci&oacute;n.</p>
</div>

<script>
function exportar() {
    var btn = document.getElementById('btnExportar');
    var progress = document.getElementById('exportProgress');
    var fill = document.getElementById('progressFill');
    var text = document.getElementById('progressText');

    btn.disabled = true;
    btn.style.opacity = '0.6';
    btn.style.cursor = 'not-allowed';
    progress.style.display = 'block';

    var steps = [
        [10, 'Conectando a la base de datos...'],
        [25, 'Generando dump SQL...'],
        [50, 'Copiando archivos...'],
        [75, 'Comprimiendo paquete...'],
        [90, 'Preparando descarga...']
    ];

    var i = 0;
    var interval = setInterval(function() {
        if (i < steps.length) {
            fill.style.width = steps[i][0] + '%';
            text.textContent = steps[i][1];
            i++;
        }
    }, 800);

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_migration.php';
    document.body.appendChild(form);

    setTimeout(function() {
        fill.style.width = '100%';
        text.textContent = 'Descargando...';
        clearInterval(interval);
        form.submit();
        setTimeout(function() {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            progress.style.display = 'none';
            fill.style.width = '0%';
            form.remove();
        }, 3000);
    }, steps.length * 800 + 500);
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

</body>
</html>
