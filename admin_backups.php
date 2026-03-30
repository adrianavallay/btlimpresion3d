<?php
require_once __DIR__ . '/config.php';

if (!is_admin()) {
    redirect('admin.php');
}

// ── Delete backup via POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    header('Content-Type: application/json; charset=utf-8');
    $file = $_POST['file'] ?? '';
    if (!preg_match('/^backup_(db|files|full)_[\d\-_]+\.(sql\.gz|zip)$/', $file)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Archivo no valido']);
        exit;
    }
    $path = __DIR__ . '/bkp/' . $file;
    if (file_exists($path) && unlink($path)) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'No se pudo eliminar']);
    }
    exit;
}

// ── Gather backup info ──
$bkp_dir = __DIR__ . '/bkp/';
$last_backup_file = $bkp_dir . '.last_backup';
$last_auto = file_exists($last_backup_file) ? (int) file_get_contents($last_backup_file) : 0;
$next_auto = $last_auto > 0 ? $last_auto + 86400 : 0;

// List all backup files
$archivos = [];
$total_size = 0;
if (is_dir($bkp_dir)) {
    $files = glob($bkp_dir . 'backup_*');
    foreach ($files as $f) {
        $name = basename($f);
        $size = filesize($f);
        $total_size += $size;

        if (str_starts_with($name, 'backup_db_')) {
            $tipo = 'db';
            $tipo_label = 'Base de datos';
        } elseif (str_starts_with($name, 'backup_files_')) {
            $tipo = 'archivos';
            $tipo_label = 'Archivos';
        } else {
            $tipo = 'completo';
            $tipo_label = 'Completo';
        }

        $can_restore = ($tipo === 'db' || $tipo === 'completo');

        $archivos[] = [
            'nombre' => $name,
            'tipo' => $tipo,
            'tipo_label' => $tipo_label,
            'fecha' => filemtime($f),
            'tamano' => $size,
            'can_restore' => $can_restore,
        ];
    }
    usort($archivos, fn($a, $b) => $b['fecha'] - $a['fecha']);
}

function format_size(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

$admin_page = 'backups';

// Status card values
$val_ultimo = $last_auto > 0 ? date('d/m/Y H:i', $last_auto) : 'Nunca';
if ($next_auto > 0 && $next_auto <= time()) {
    $val_proximo = 'Pendiente (proximo acceso)';
} elseif ($next_auto > 0) {
    $val_proximo = 'En ' . round(($next_auto - time()) / 3600, 1) . ' horas';
} else {
    $val_proximo = 'Proximo acceso al panel';
}

// Backup types for cards
$backup_types = [
    'db'       => ['icon' => '&#128451;', 'title' => 'Base de datos',   'desc' => 'Solo tablas MySQL comprimidas', 'hint' => 'Recomendado: diario'],
    'archivos' => ['icon' => '&#128193;', 'title' => 'Archivos',        'desc' => 'Solo archivos del servidor',    'hint' => 'Recomendado: semanal'],
    'completo' => ['icon' => '&#128230;', 'title' => 'Backup completo', 'desc' => 'DB + Archivos juntos',          'hint' => 'Ideal para migraciones'],
];

// Badge colors per type
$badge_colors = [
    'db'       => ['bg' => '#eff6ff', 'color' => '#2563eb'],
    'archivos' => ['bg' => '#fefce8', 'color' => '#a16207'],
    'completo' => ['bg' => '#f0fdf4', 'color' => '#16a34a'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Backups — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

<!-- PAGE HEADER -->
<div style="margin-bottom:24px;">
    <h1 style="font-family:var(--font-serif);font-size:1.6rem;font-weight:600;color:#111;margin:0 0 4px;">Backups</h1>
    <p style="font-size:0.85rem;color:#999;margin:0;">Gestion de copias de seguridad del sistema</p>
</div>

<!-- STATUS CARDS -->
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:24px;width:100%;box-sizing:border-box;">
    <?php
    $stats = [
        ['icon' => '&#128337;', 'label' => 'Ultimo backup auto', 'value' => $val_ultimo],
        ['icon' => '&#9203;',   'label' => 'Proximo backup auto', 'value' => $val_proximo],
        ['icon' => '&#128190;', 'label' => 'Espacio usado',       'value' => format_size($total_size)],
        ['icon' => '&#128196;', 'label' => 'Total de backups',    'value' => count($archivos)],
    ];
    foreach ($stats as $st): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:12px;background:#fff;border:1px solid #e8e8e8;box-sizing:border-box;overflow:hidden;">
        <span style="font-size:1.2rem;flex-shrink:0;"><?= $st['icon'] ?></span>
        <div style="min-width:0;overflow:hidden;">
            <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $st['label'] ?></div>
            <div style="font-size:0.85rem;font-weight:600;color:#333;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $st['value'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- GENERATE BACKUP -->
<div style="background:#fff;border:1px solid #e8e8e8;margin-bottom:24px;width:100%;box-sizing:border-box;overflow:hidden;">
    <div style="padding:16px 20px 0;">
        <h3 style="font-family:var(--font-serif);font-size:1.1rem;font-weight:600;color:#111;margin:0;">Generar backup</h3>
    </div>
    <div style="padding:16px 20px 20px;">
        <div style="display:flex;flex-direction:column;gap:10px;width:100%;">
            <?php foreach ($backup_types as $tipo_key => $info): ?>
            <button type="button" onclick="runBackup('<?= $tipo_key ?>')" style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:#fff;border:1px solid #e8e8e8;width:100%;box-sizing:border-box;cursor:pointer;font-family:var(--font-sans);text-align:left;transition:border-color 0.2s;" onmouseover="this.style.borderColor='#111'" onmouseout="this.style.borderColor='#e8e8e8'">
                <span style="font-size:1.6rem;flex-shrink:0;"><?= $info['icon'] ?></span>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:0.88rem;color:#111;"><?= $info['title'] ?></div>
                    <div style="color:#666;font-size:0.78rem;"><?= $info['desc'] ?></div>
                    <div style="color:#aaa;font-size:0.72rem;font-style:italic;"><?= $info['hint'] ?></div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>

        <div id="bkpProgress" style="display:none;align-items:center;gap:12px;padding:16px;background:#f0f8ff;border:1px solid #d0e4f5;margin-top:16px;font-size:0.88rem;color:#1d4ed8;">
            <div class="bkp-spinner"></div>
            <span id="bkpProgressText">Generando backup...</span>
        </div>
        <div id="bkpResult" style="display:none;padding:16px;margin-top:16px;font-size:0.85rem;line-height:1.6;"></div>
    </div>
</div>

<!-- SAVED BACKUPS TABLE -->
<div style="background:#fff;border:1px solid #e8e8e8;margin-bottom:24px;width:100%;box-sizing:border-box;overflow:hidden;">
    <div style="padding:16px 20px 0;">
        <h3 style="font-family:var(--font-serif);font-size:1.1rem;font-weight:600;color:#111;margin:0;">Backups guardados</h3>
    </div>
    <div style="padding:0;">
        <?php if (empty($archivos)): ?>
            <p style="padding:24px;text-align:center;color:#999;">No hay backups guardados todavia.</p>
        <?php else: ?>
        <div style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;padding:16px 0 16px;">
            <table style="min-width:540px;width:100%;border-collapse:collapse;font-size:0.85rem;">
                <thead>
                    <tr style="background:#f8f8f8;">
                        <th style="padding:10px 14px;text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;white-space:nowrap;">Tipo</th>
                        <th style="padding:10px 14px;text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;white-space:nowrap;">Nombre</th>
                        <th style="padding:10px 14px;text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;white-space:nowrap;">Fecha</th>
                        <th style="padding:10px 14px;text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;white-space:nowrap;">Tamano</th>
                        <th style="padding:10px 14px;text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:#999;white-space:nowrap;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archivos as $a):
                        $bc = $badge_colors[$a['tipo']] ?? $badge_colors['db'];
                    ?>
                    <tr id="row-<?= md5($a['nombre']) ?>" style="border-top:1px solid #f0f0f0;">
                        <td style="padding:12px 14px;white-space:nowrap;">
                            <span style="display:inline-block;padding:3px 8px;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;background:<?= $bc['bg'] ?>;color:<?= $bc['color'] ?>;"><?= $a['tipo_label'] ?></span>
                        </td>
                        <td style="padding:12px 14px;white-space:nowrap;font-family:monospace;font-size:0.78rem;"><?= sanitize($a['nombre']) ?></td>
                        <td style="padding:12px 14px;white-space:nowrap;color:#666;"><?= date('d/m/Y H:i', $a['fecha']) ?></td>
                        <td style="padding:12px 14px;white-space:nowrap;color:#666;"><?= format_size($a['tamano']) ?></td>
                        <td style="padding:12px 14px;white-space:nowrap;">
                            <a href="download_backup.php?file=<?= urlencode($a['nombre']) ?>" style="display:inline-block;padding:5px 10px;border:1px solid #111;font-size:0.72rem;font-weight:600;text-transform:uppercase;color:#111;text-decoration:none;margin-right:4px;">&#8615; Descargar</a>
                            <?php if ($a['can_restore']): ?>
                            <button type="button" data-archivo="<?= sanitize($a['nombre']) ?>" data-tipo="<?= $a['tipo'] ?>" data-restore style="padding:5px 10px;border:1px solid #111;font-size:0.72rem;font-weight:600;text-transform:uppercase;color:#111;background:none;cursor:pointer;margin-right:4px;">&#8634; Restaurar</button>
                            <?php endif; ?>
                            <button type="button" onclick="confirmDelete('<?= sanitize($a['nombre']) ?>')" style="padding:5px 10px;border:1px solid #ef4444;font-size:0.72rem;font-weight:600;color:#fff;background:#ef4444;cursor:pointer;">&#10005;</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- RESTORE WARNING -->
<div style="width:100%;box-sizing:border-box;padding:16px;border-left:3px solid #ef4444;background:#fef2f2;overflow:hidden;">
    <p style="margin:0 0 8px;font-weight:700;color:#dc2626;font-size:0.88rem;">&#9888;&#65039; Sobre la restauracion</p>
    <p style="margin:0;font-size:0.83rem;color:#7f1d1d;line-height:1.6;word-wrap:break-word;overflow-wrap:break-word;white-space:normal;">La restauracion de base de datos reemplaza TODOS los datos actuales con los del backup seleccionado. Asegurate de tener un backup reciente antes de restaurar. Esta accion no se puede deshacer.</p>
</div>

<!-- RESTORE MODAL -->
<div class="modal-overlay" id="modalRestaurar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:24px;max-width:420px;width:90%;box-sizing:border-box;text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:12px;">&#9888;&#65039;</div>
        <h3 style="font-family:var(--font-serif);font-size:1.1rem;margin:0 0 8px;">Confirmar restauracion</h3>
        <p style="margin:8px 0;color:#666;font-size:0.88rem;">Vas a restaurar:</p>
        <code id="modalArchivoNombre" style="display:block;background:#f5f5f5;padding:8px 12px;font-size:0.78rem;margin-bottom:12px;word-break:break-all;"></code>
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-left:3px solid #b45309;padding:10px 14px;font-size:0.8rem;color:#92400e;text-align:left;margin-bottom:12px;line-height:1.5;">
            Esta accion reemplazara TODA la base de datos actual. No se puede deshacer.
        </div>
        <div id="modalTipoInfo" style="font-size:0.78rem;color:#666;margin-bottom:20px;"></div>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <button id="modalCancelar" style="padding:8px 20px;border:1px solid #e0e0e0;background:#fff;color:#555;cursor:pointer;font-size:0.8rem;font-weight:600;font-family:var(--font-sans);">Cancelar</button>
            <button id="modalConfirmar" style="padding:8px 20px;background:#b45309;color:#fff;border:none;cursor:pointer;font-size:0.8rem;font-weight:700;text-transform:uppercase;font-family:var(--font-sans);">Si, restaurar</button>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:24px;max-width:420px;width:90%;box-sizing:border-box;">
        <h3 style="font-family:var(--font-serif);font-size:1.1rem;margin:0 0 12px;">Eliminar backup</h3>
        <p style="margin:0 0 8px;color:#555;font-size:0.88rem;">Estas seguro de que queres eliminar este backup?</p>
        <p style="margin:0 0 20px;"><strong id="deleteFileName" style="font-size:0.85rem;word-break:break-all;"></strong></p>
        <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
            <button onclick="closeModal('deleteModal')" style="padding:8px 20px;border:1px solid #e0e0e0;background:#fff;color:#555;cursor:pointer;font-size:0.8rem;font-weight:600;font-family:var(--font-sans);">Cancelar</button>
            <button id="deleteConfirmBtn" onclick="executeDelete()" style="padding:8px 20px;background:#ef4444;color:#fff;border:none;cursor:pointer;font-size:0.8rem;font-weight:700;text-transform:uppercase;font-family:var(--font-sans);">Eliminar</button>
        </div>
    </div>
</div>

<script>
var archivoARestaurar = '';
var tipoARestaurar = '';
var currentDeleteFile = '';

/* ── BACKUP GENERATION ── */
function runBackup(tipo) {
    var progress = document.getElementById('bkpProgress');
    var result = document.getElementById('bkpResult');
    var text = document.getElementById('bkpProgressText');

    var labels = { db: 'base de datos', archivos: 'archivos', completo: 'backup completo' };
    text.textContent = 'Generando ' + (labels[tipo] || tipo) + '...';
    progress.style.display = 'flex';
    result.style.display = 'none';

    fetch('backup_runner.php?tipo=' + tipo)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            progress.style.display = 'none';
            if (data.ok) {
                result.style.background = '#f0fdf4';
                result.style.border = '1px solid #bbf7d0';
                result.style.color = '#166534';
                result.innerHTML = '<strong>Backup generado correctamente</strong><br>' +
                    'Archivo: ' + data.archivo + '<br>' +
                    'Tamano: ' + formatSize(data.tamano) + ' &mdash; Duracion: ' + data.duracion + 's';
            } else {
                result.style.background = '#fef2f2';
                result.style.border = '1px solid #fecaca';
                result.style.color = '#991b1b';
                result.innerHTML = '<strong>Error al generar el backup</strong><br>Revisa los permisos de la carpeta /bkp/';
            }
            result.style.display = 'block';
            setTimeout(function() { location.reload(); }, 2500);
        })
        .catch(function() {
            progress.style.display = 'none';
            result.style.background = '#fef2f2';
            result.style.border = '1px solid #fecaca';
            result.style.color = '#991b1b';
            result.innerHTML = '<strong>Error de conexion</strong>';
            result.style.display = 'block';
        });
}

function formatSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}

/* ── RESTORE ── */
document.querySelectorAll('[data-restore]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        archivoARestaurar = this.dataset.archivo;
        tipoARestaurar = this.dataset.tipo;
        document.getElementById('modalArchivoNombre').textContent = archivoARestaurar;
        var infoTexto = tipoARestaurar === 'completo'
            ? '&#128230; Backup completo: se restaurara la base de datos contenida en el ZIP.'
            : '&#128451; Backup de base de datos.';
        document.getElementById('modalTipoInfo').innerHTML = infoTexto;
        document.getElementById('modalRestaurar').style.display = 'flex';
    });
});

document.getElementById('modalCancelar').addEventListener('click', function() {
    document.getElementById('modalRestaurar').style.display = 'none';
});

document.getElementById('modalConfirmar').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Restaurando...';

    fetch('restore_database.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ archivo: archivoARestaurar, tipo: tipoARestaurar })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('modalRestaurar').style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Si, restaurar';
        if (data.ok) {
            mostrarAlerta(data.mensaje, 'success');
        } else {
            mostrarAlerta('Error: ' + (data.error || data.mensaje || 'Error desconocido'), 'error');
        }
    })
    .catch(function(err) {
        document.getElementById('modalRestaurar').style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Si, restaurar';
        mostrarAlerta('Error de conexion: ' + err.message, 'error');
    });
});

/* ── DELETE ── */
function confirmDelete(file) {
    currentDeleteFile = file;
    document.getElementById('deleteFileName').textContent = file;
    document.getElementById('deleteModal').style.display = 'flex';
}

function executeDelete() {
    var btn = document.getElementById('deleteConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'Eliminando...';

    fetch('admin_backups.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&file=' + encodeURIComponent(currentDeleteFile)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        closeModal('deleteModal');
        btn.disabled = false;
        btn.textContent = 'Eliminar';
        if (data.ok) {
            location.reload();
        } else {
            mostrarAlerta('Error: ' + (data.mensaje || 'No se pudo eliminar'), 'error');
        }
    })
    .catch(function() {
        closeModal('deleteModal');
        btn.disabled = false;
        btn.textContent = 'Eliminar';
        mostrarAlerta('Error de conexion', 'error');
    });
}

/* ── HELPERS ── */
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === m) m.style.display = 'none';
    });
});

function mostrarAlerta(texto, tipo) {
    var div = document.createElement('div');
    div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:3000;' +
        'padding:14px 20px;font-size:0.85rem;font-weight:600;max-width:90%;' +
        'box-sizing:border-box;box-shadow:0 4px 12px rgba(0,0,0,0.1);' +
        'background:' + (tipo === 'success' ? '#f0fdf4' : '#fef2f2') + ';' +
        'border:1px solid ' + (tipo === 'success' ? '#86efac' : '#fca5a5') + ';' +
        'border-left:3px solid ' + (tipo === 'success' ? '#22c55e' : '#ef4444') + ';' +
        'color:' + (tipo === 'success' ? '#166534' : '#991b1b') + ';';
    div.textContent = texto;
    document.body.appendChild(div);
    setTimeout(function() { div.remove(); }, 5000);
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

</body>
</html>
