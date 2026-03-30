<?php
// ============================================================
// ADMIN — SLIDER DEL HOME
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$db = pdo();

// ── Ensure table exists ──
$db->exec("CREATE TABLE IF NOT EXISTS slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden INT DEFAULT 0,
    imagen VARCHAR(500) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    subtitulo VARCHAR(200) DEFAULT '',
    titulo VARCHAR(300) DEFAULT '',
    descripcion VARCHAR(500) DEFAULT '',
    btn1_texto VARCHAR(100) DEFAULT '',
    btn1_url VARCHAR(300) DEFAULT '',
    btn1_estilo ENUM('solido','outline') DEFAULT 'solido',
    btn2_texto VARCHAR(100) DEFAULT '',
    btn2_url VARCHAR(300) DEFAULT '',
    btn2_estilo ENUM('solido','outline') DEFAULT 'outline',
    texto_posicion ENUM('centro','izquierda','derecha') DEFAULT 'centro',
    texto_color ENUM('blanco','negro') DEFAULT 'blanco',
    overlay_opacidad TINYINT DEFAULT 40,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg_ok = '';
$msg_err = '';

// ── POST Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // SAVE (create or update)
    if ($action === 'guardar') {
        $id = (int) ($_POST['slide_id'] ?? 0);
        $imagen = trim($_POST['imagen'] ?? '');
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $btn1_texto = trim($_POST['btn1_texto'] ?? '');
        $btn1_url = trim($_POST['btn1_url'] ?? '');
        $btn1_estilo = in_array($_POST['btn1_estilo'] ?? '', ['solido','outline']) ? $_POST['btn1_estilo'] : 'solido';
        $btn2_texto = trim($_POST['btn2_texto'] ?? '');
        $btn2_url = trim($_POST['btn2_url'] ?? '');
        $btn2_estilo = in_array($_POST['btn2_estilo'] ?? '', ['solido','outline']) ? $_POST['btn2_estilo'] : 'outline';
        $texto_posicion = in_array($_POST['texto_posicion'] ?? '', ['centro','izquierda','derecha']) ? $_POST['texto_posicion'] : 'centro';
        $texto_color = in_array($_POST['texto_color'] ?? '', ['blanco','negro']) ? $_POST['texto_color'] : 'blanco';
        $overlay_opacidad = max(0, min(80, (int) ($_POST['overlay_opacidad'] ?? 40)));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id > 0) {
            // Update
            $stmt = $db->prepare("UPDATE slides SET imagen=?, subtitulo=?, titulo=?, descripcion=?,
                btn1_texto=?, btn1_url=?, btn1_estilo=?, btn2_texto=?, btn2_url=?, btn2_estilo=?,
                texto_posicion=?, texto_color=?, overlay_opacidad=?, activo=? WHERE id=?");
            $stmt->execute([$imagen, $subtitulo, $titulo, $descripcion,
                $btn1_texto, $btn1_url, $btn1_estilo, $btn2_texto, $btn2_url, $btn2_estilo,
                $texto_posicion, $texto_color, $overlay_opacidad, $activo, $id]);
            $msg_ok = 'Slide actualizado correctamente.';
        } else {
            // Check max 10
            $count = (int) $db->query("SELECT COUNT(*) FROM slides")->fetchColumn();
            if ($count >= 10) {
                $msg_err = 'Maximo 10 slides permitidos.';
            } elseif (empty($imagen)) {
                $msg_err = 'La imagen es obligatoria.';
            } else {
                $orden = (int) $db->query("SELECT COALESCE(MAX(orden),0)+1 FROM slides")->fetchColumn();
                $stmt = $db->prepare("INSERT INTO slides (imagen, subtitulo, titulo, descripcion,
                    btn1_texto, btn1_url, btn1_estilo, btn2_texto, btn2_url, btn2_estilo,
                    texto_posicion, texto_color, overlay_opacidad, activo, orden)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$imagen, $subtitulo, $titulo, $descripcion,
                    $btn1_texto, $btn1_url, $btn1_estilo, $btn2_texto, $btn2_url, $btn2_estilo,
                    $texto_posicion, $texto_color, $overlay_opacidad, $activo, $orden]);
                $msg_ok = 'Slide creado correctamente.';
            }
        }
    }

    // DELETE
    if ($action === 'eliminar') {
        $id = (int) ($_POST['slide_id'] ?? 0);
        $slide = $db->prepare("SELECT imagen FROM slides WHERE id=?");
        $slide->execute([$id]);
        $s = $slide->fetch();
        if ($s) {
            $img_path = __DIR__ . '/' . $s['imagen'];
            if (file_exists($img_path)) @unlink($img_path);
            $db->prepare("DELETE FROM slides WHERE id=?")->execute([$id]);
            $msg_ok = 'Slide eliminado.';
        }
    }

    // TOGGLE
    if ($action === 'toggle') {
        $id = (int) ($_POST['slide_id'] ?? 0);
        $db->prepare("UPDATE slides SET activo = NOT activo WHERE id=?")->execute([$id]);
        $msg_ok = 'Estado actualizado.';
    }

    // REORDER (AJAX)
    if ($action === 'reordenar') {
        header('Content-Type: application/json');
        $ids = json_decode($_POST['orden'] ?? '[]', true);
        if (is_array($ids)) {
            $stmt = $db->prepare("UPDATE slides SET orden=? WHERE id=?");
            foreach ($ids as $i => $slide_id) {
                $stmt->execute([$i, (int) $slide_id]);
            }
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit;
    }
}

// ── Load slides ──
$slides = $db->query("SELECT * FROM slides ORDER BY orden ASC")->fetchAll();
$total_slides = count($slides);

$admin_page = 'slides';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Slider del Home — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h1>Slider del Home</h1>
            <p class="page-subtitle"><?= $total_slides ?> / 10 slides</p>
        </div>
        <button class="btn btn-primary" onclick="openSlideForm()" <?= $total_slides >= 10 ? 'disabled' : '' ?>>+ Agregar slide</button>
    </div>

    <?php if ($msg_ok): ?>
        <div class="result-box ok" style="margin-bottom:20px;"><?= sanitize($msg_ok) ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
        <div class="result-box err" style="margin-bottom:20px;"><?= sanitize($msg_err) ?></div>
    <?php endif; ?>

    <!-- SLIDES LIST -->
    <div class="slides-list" id="slidesList">
        <?php if (empty($slides)): ?>
            <div class="slides-empty">
                <p>No hay slides todavia. Crea el primero para mostrar un slider en el home.</p>
            </div>
        <?php else: ?>
            <?php foreach ($slides as $s): ?>
            <div class="slide-card" data-id="<?= $s['id'] ?>">
                <div class="slide-card__drag" title="Arrastrar para reordenar">&#10495;</div>
                <div class="slide-card__thumb">
                    <img src="<?= sanitize($s['imagen']) ?>" alt="Slide">
                </div>
                <div class="slide-card__info">
                    <div class="slide-card__title"><?= sanitize($s['titulo'] ?: 'Sin titulo') ?></div>
                    <div class="slide-card__sub"><?= sanitize($s['subtitulo']) ?></div>
                </div>
                <div class="slide-card__meta">
                    <span class="slide-card__pos"><?= ucfirst($s['texto_posicion']) ?></span>
                </div>
                <div class="slide-card__status">
                    <span class="bkp-badge <?= $s['activo'] ? 'bkp-badge--completo' : 'bkp-badge--archivos' ?>">
                        <?= $s['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
                <div class="slide-card__actions">
                    <button class="btn btn--sm btn--outline" onclick='editSlide(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Editar</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="slide_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn--sm btn--outline"><?= $s['activo'] ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar este slide?')">
                        <input type="hidden" name="action" value="eliminar">
                        <input type="hidden" name="slide_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn--sm btn--danger">&#10005;</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<!-- FORM CREATE/EDIT -->
<div id="slideFormContainer" style="display:none; margin-top: 24px;">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h2 id="slideFormTitle" style="font-family:var(--font-serif);font-size:1.2rem;margin:0;">Agregar slide</h2>
    </div>

    <form method="POST" id="slideForm">
        <input type="hidden" name="action" value="guardar">
        <input type="hidden" name="slide_id" id="slideId" value="0">
        <input type="hidden" name="imagen" id="slideImagen" value="">

        <div class="slide-form-grid">

            <!-- COLUMNA IZQUIERDA -->
            <div class="slide-form-left">

                <!-- Card: Imagen -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h3>&#128247; Imagen del slide</h3>
                        <span class="form-card-hint">Minimo 1920x800px &middot; JPG o WebP</span>
                    </div>
                    <div class="upload-zone" id="uploadZone">
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <div class="upload-icon">&uarr;</div>
                            <p>Hace click o arrastra una imagen aqui</p>
                            <small>JPG, PNG, WebP &middot; Max 10MB</small>
                        </div>
                        <img id="uploadPreviewImg" style="display:none" alt="Preview">
                        <input type="file" name="imagen_file" id="imagenInput" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="upload-progress" id="uploadProgress">
                        <div class="bkp-spinner"></div><span>Subiendo imagen...</span>
                    </div>
                </div>

                <!-- Card: Texto -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h3>&#9999;&#65039; Contenido de texto</h3>
                    </div>
                    <div class="form-fields">
                        <div class="form-field">
                            <label>Subtitulo <span class="label-hint">uppercase pequeno</span></label>
                            <input type="text" name="subtitulo" id="fSubtitulo" placeholder="ej: NUEVA COLECCION" maxlength="200">
                        </div>
                        <div class="form-field">
                            <label>Titulo principal</label>
                            <input type="text" name="titulo" id="fTitulo" placeholder="ej: Encontra tu estilo" maxlength="300" class="input-lg">
                        </div>
                        <div class="form-field">
                            <label>Descripcion <span class="label-hint">opcional</span></label>
                            <textarea name="descripcion" id="fDescripcion" rows="2" maxlength="500" placeholder="Texto descriptivo breve..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Card: Botones -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h3>&#128280; Botones</h3>
                    </div>
                    <div class="btns-grid">
                        <div class="btn-group">
                            <div class="btn-group-title">Boton 1 &mdash; Principal</div>
                            <div class="form-field">
                                <label>Texto</label>
                                <input type="text" name="btn1_texto" id="fBtn1Texto" placeholder="ej: Ver productos" maxlength="100">
                            </div>
                            <div class="form-field">
                                <label>URL</label>
                                <input type="text" name="btn1_url" id="fBtn1Url" placeholder="ej: tienda">
                            </div>
                            <div class="form-field">
                                <label>Estilo</label>
                                <div class="estilo-selector">
                                    <label class="estilo-option">
                                        <input type="radio" name="btn1_estilo" value="solido" checked>
                                        <span>Solido</span>
                                    </label>
                                    <label class="estilo-option">
                                        <input type="radio" name="btn1_estilo" value="outline">
                                        <span>Outline</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="btn-group">
                            <div class="btn-group-title">Boton 2 &mdash; Secundario</div>
                            <div class="form-field">
                                <label>Texto</label>
                                <input type="text" name="btn2_texto" id="fBtn2Texto" placeholder="ej: Contacto" maxlength="100">
                            </div>
                            <div class="form-field">
                                <label>URL</label>
                                <input type="text" name="btn2_url" id="fBtn2Url" placeholder="ej: contacto">
                            </div>
                            <div class="form-field">
                                <label>Estilo</label>
                                <div class="estilo-selector">
                                    <label class="estilo-option">
                                        <input type="radio" name="btn2_estilo" value="solido">
                                        <span>Solido</span>
                                    </label>
                                    <label class="estilo-option">
                                        <input type="radio" name="btn2_estilo" value="outline" checked>
                                        <span>Outline</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Apariencia -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h3>&#127912; Apariencia</h3>
                    </div>
                    <div class="form-fields">

                        <div class="form-field">
                            <label>Posicion del texto</label>
                            <div class="posicion-selector">
                                <label class="posicion-option">
                                    <input type="radio" name="texto_posicion" value="izquierda">
                                    <span>
                                        <svg viewBox="0 0 40 28" fill="currentColor">
                                            <rect x="4" y="6" width="16" height="3" rx="1"/>
                                            <rect x="4" y="13" width="24" height="3" rx="1"/>
                                            <rect x="4" y="20" width="12" height="3" rx="1"/>
                                        </svg>
                                        Izquierda
                                    </span>
                                </label>
                                <label class="posicion-option">
                                    <input type="radio" name="texto_posicion" value="centro" checked>
                                    <span>
                                        <svg viewBox="0 0 40 28" fill="currentColor">
                                            <rect x="12" y="6" width="16" height="3" rx="1"/>
                                            <rect x="8" y="13" width="24" height="3" rx="1"/>
                                            <rect x="14" y="20" width="12" height="3" rx="1"/>
                                        </svg>
                                        Centro
                                    </span>
                                </label>
                                <label class="posicion-option">
                                    <input type="radio" name="texto_posicion" value="derecha">
                                    <span>
                                        <svg viewBox="0 0 40 28" fill="currentColor">
                                            <rect x="20" y="6" width="16" height="3" rx="1"/>
                                            <rect x="8" y="13" width="24" height="3" rx="1"/>
                                            <rect x="24" y="20" width="12" height="3" rx="1"/>
                                        </svg>
                                        Derecha
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Color del texto</label>
                            <div class="color-selector">
                                <label class="color-option color-blanco">
                                    <input type="radio" name="texto_color" value="blanco" checked>
                                    <span>&#9728; Blanco</span>
                                </label>
                                <label class="color-option color-negro">
                                    <input type="radio" name="texto_color" value="negro">
                                    <span>&#9673; Negro</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>
                                Oscuridad del overlay
                                <span class="overlay-value" id="overlayValue">40%</span>
                            </label>
                            <input type="range" name="overlay_opacidad" id="overlayRange" min="0" max="80" value="40" step="5">
                            <div class="overlay-track-labels">
                                <span>Sin overlay</span>
                                <span>Muy oscuro</span>
                            </div>
                        </div>

                        <div class="form-field">
                            <label class="toggle-label">
                                <div class="toggle-switch">
                                    <input type="checkbox" name="activo" id="fActivo" checked>
                                    <span class="toggle-track"></span>
                                </div>
                                Slide activo (visible en el home)
                            </label>
                        </div>

                    </div>
                </div>

                <!-- Acciones -->
                <div class="slide-form-actions">
                    <button type="button" id="cancelarSlide" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar slide</button>
                </div>

            </div>

            <!-- COLUMNA DERECHA: PREVIEW -->
            <div class="slide-form-right">
                <div class="preview-sticky">
                    <div class="preview-header">
                        <span>Preview</span>
                        <span class="preview-hint">Se actualiza en tiempo real</span>
                    </div>

                    <!-- Device selector -->
                    <div class="preview-device-selector">
                        <button type="button" class="device-btn active" data-device="desktop" title="Desktop">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                            <span>Desktop</span>
                        </button>
                        <button type="button" class="device-btn" data-device="tablet" title="Tablet">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="18" r="1"/></svg>
                            <span>Tablet</span>
                        </button>
                        <button type="button" class="device-btn" data-device="mobile" title="Mobile">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="18" r="1"/></svg>
                            <span>Mobile</span>
                        </button>
                    </div>

                    <!-- Device frame -->
                    <div class="device-frame-wrapper" id="deviceFrameWrapper">
                        <div class="device-frame" id="deviceFrame" data-device="desktop">
                            <div class="device-screen">
                                <div class="slide-preview" id="slidePreview">
                                    <div class="preview-bg" id="previewBg">
                                        <div class="preview-overlay" id="previewOverlay"></div>
                                        <div class="preview-content" id="previewContent">
                                            <span class="preview-subtitulo" id="previewSub"></span>
                                            <h4 class="preview-titulo" id="previewTit">Titulo del slide</h4>
                                            <p class="preview-desc" id="previewDesc"></p>
                                            <div class="preview-btns" id="previewBtns"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="device-notch" id="deviceNotch"></div>
                            <div class="device-home" id="deviceHome"></div>
                        </div>
                        <div class="device-dimensions" id="deviceDimensions">1920 &times; 1080</div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
/* ── Show/Hide Form ── */
var formContainer = document.getElementById('slideFormContainer');
var slidesList    = document.getElementById('slidesList');
var pageHeader    = document.querySelector('.page-header');

function openSlideForm() {
    document.getElementById('slideFormTitle').textContent = 'Agregar slide';
    document.getElementById('slideId').value = '0';
    document.getElementById('slideForm').reset();
    document.getElementById('slideImagen').value = '';
    document.getElementById('uploadPreviewImg').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = '';
    document.getElementById('overlayRange').value = 40;
    document.getElementById('overlayValue').textContent = '40%';
    document.getElementById('fActivo').checked = true;
    document.querySelector('[name=texto_posicion][value=centro]').checked = true;
    document.querySelector('[name=texto_color][value=blanco]').checked = true;
    document.querySelector('[name=btn1_estilo][value=solido]').checked = true;
    document.querySelector('[name=btn2_estilo][value=outline]').checked = true;
    syncPreview();
    formContainer.style.display = '';
    slidesList.style.display = 'none';
    pageHeader.style.display = 'none';
    window.scrollTo(0, 0);
}

function closeSlideForm() {
    formContainer.style.display = 'none';
    slidesList.style.display = '';
    pageHeader.style.display = '';
}

function editSlide(s) {
    document.getElementById('slideFormTitle').textContent = 'Editar slide';
    document.getElementById('slideId').value = s.id;
    document.getElementById('slideImagen').value = s.imagen;
    document.getElementById('fSubtitulo').value = s.subtitulo || '';
    document.getElementById('fTitulo').value = s.titulo || '';
    document.getElementById('fDescripcion').value = s.descripcion || '';
    document.getElementById('fBtn1Texto').value = s.btn1_texto || '';
    document.getElementById('fBtn1Url').value = s.btn1_url || '';
    document.getElementById('fBtn2Texto').value = s.btn2_texto || '';
    document.getElementById('fBtn2Url').value = s.btn2_url || '';

    // Radio buttons
    var b1e = s.btn1_estilo || 'solido';
    var b1r = document.querySelector('[name=btn1_estilo][value="' + b1e + '"]');
    if (b1r) b1r.checked = true;
    var b2e = s.btn2_estilo || 'outline';
    var b2r = document.querySelector('[name=btn2_estilo][value="' + b2e + '"]');
    if (b2r) b2r.checked = true;
    var pos = s.texto_posicion || 'centro';
    var pr = document.querySelector('[name=texto_posicion][value="' + pos + '"]');
    if (pr) pr.checked = true;
    var col = s.texto_color || 'blanco';
    var cr = document.querySelector('[name=texto_color][value="' + col + '"]');
    if (cr) cr.checked = true;

    document.getElementById('overlayRange').value = s.overlay_opacidad || 40;
    document.getElementById('overlayValue').textContent = (s.overlay_opacidad || 40) + '%';
    document.getElementById('fActivo').checked = !!parseInt(s.activo);

    // Show image preview
    if (s.imagen) {
        var img = document.getElementById('uploadPreviewImg');
        img.src = s.imagen;
        img.style.display = 'block';
        document.getElementById('uploadPlaceholder').style.display = 'none';
        document.getElementById('previewBg').style.backgroundImage = 'url(' + s.imagen + ')';
    }

    syncPreview();
    formContainer.style.display = '';
    slidesList.style.display = 'none';
    pageHeader.style.display = 'none';
    window.scrollTo(0, 0);
}

// Cancel button
document.getElementById('cancelarSlide').addEventListener('click', closeSlideForm);

// Wire up "Agregar slide" button
document.querySelector('.page-header .btn-primary')?.addEventListener('click', function(e) {
    e.preventDefault();
    openSlideForm();
});

/* ── Image upload ── */
document.getElementById('imagenInput').addEventListener('change', function() {
    if (!this.files[0]) return;
    var file = this.files[0];
    var formData = new FormData();
    formData.append('imagen', file);

    document.getElementById('uploadProgress').style.display = 'flex';

    // Instant local preview
    var reader = new FileReader();
    reader.onload = function(ev) {
        document.getElementById('uploadPlaceholder').style.display = 'none';
        var img = document.getElementById('uploadPreviewImg');
        img.src = ev.target.result;
        img.style.display = 'block';
        document.getElementById('previewBg').style.backgroundImage = 'url(' + ev.target.result + ')';
    };
    reader.readAsDataURL(file);

    fetch('upload_slide.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('uploadProgress').style.display = 'none';
            if (data.ok) {
                document.getElementById('slideImagen').value = data.url;
                // Update preview bg with server URL
                document.getElementById('previewBg').style.backgroundImage = 'url(' + data.url + ')';
            } else {
                alert('Error: ' + (data.error || 'No se pudo subir'));
            }
        })
        .catch(function() {
            document.getElementById('uploadProgress').style.display = 'none';
            alert('Error de conexion');
        });
});

/* ── Live preview ── */
var previewBg      = document.getElementById('previewBg');
var previewOverlay = document.getElementById('previewOverlay');
var previewContent = document.getElementById('previewContent');
var previewSub     = document.getElementById('previewSub');
var previewTit     = document.getElementById('previewTit');
var previewDesc    = document.getElementById('previewDesc');
var previewBtns    = document.getElementById('previewBtns');
var overlayRange   = document.getElementById('overlayRange');
var overlayValue   = document.getElementById('overlayValue');

overlayRange.addEventListener('input', function() {
    overlayValue.textContent = this.value + '%';
    previewOverlay.style.background = 'rgba(0,0,0,' + (this.value / 100) + ')';
});

function syncPreview() {
    previewSub.textContent  = document.getElementById('fSubtitulo').value || '';
    previewTit.textContent  = document.getElementById('fTitulo').value || 'Titulo del slide';
    previewDesc.textContent = document.getElementById('fDescripcion').value || '';

    // Buttons
    var btn1txt = document.getElementById('fBtn1Texto').value;
    var btn2txt = document.getElementById('fBtn2Texto').value;
    var btn1est = document.querySelector('[name=btn1_estilo]:checked')?.value || 'solido';
    var btn2est = document.querySelector('[name=btn2_estilo]:checked')?.value || 'outline';

    var html = '';
    if (btn1txt) html += '<div class="preview-btn preview-btn--' + btn1est + '">' + btn1txt + '</div>';
    if (btn2txt) html += '<div class="preview-btn preview-btn--' + btn2est + '">' + btn2txt + '</div>';
    previewBtns.innerHTML = html;

    // Position
    var pos = document.querySelector('[name=texto_posicion]:checked')?.value || 'centro';
    previewContent.style.textAlign = pos === 'centro' ? 'center' : pos === 'izquierda' ? 'left' : 'right';
    previewContent.style.alignItems = pos === 'centro' ? 'center' : pos === 'izquierda' ? 'flex-start' : 'flex-end';

    // Color
    var color = document.querySelector('[name=texto_color]:checked')?.value || 'blanco';
    previewContent.style.color = color === 'blanco' ? '#fff' : '#111';

    // Overlay
    var ov = overlayRange.value;
    previewOverlay.style.background = 'rgba(0,0,0,' + (ov / 100) + ')';
}

// Bind all inputs for real-time preview
document.querySelectorAll('#slideForm input, #slideForm textarea').forEach(function(el) {
    el.addEventListener('input', syncPreview);
    el.addEventListener('change', syncPreview);
});

syncPreview();

/* ── Form validation ── */
document.getElementById('slideForm').addEventListener('submit', function(e) {
    if (!document.getElementById('slideImagen').value) {
        e.preventDefault();
        alert('Debes subir una imagen para el slide.');
    }
});

/* ── Drag & Drop reorder ── */
(function() {
    var list = document.getElementById('slidesList');
    var dragging = null;

    list.addEventListener('dragstart', function(e) {
        var card = e.target.closest('.slide-card');
        if (!card) return;
        dragging = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragend', function() {
        if (dragging) dragging.classList.remove('dragging');
        dragging = null;
        saveOrder();
    });

    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        var card = e.target.closest('.slide-card');
        if (!card || card === dragging) return;
        var rect = card.getBoundingClientRect();
        var after = e.clientY > rect.top + rect.height / 2;
        if (after) {
            card.parentNode.insertBefore(dragging, card.nextSibling);
        } else {
            card.parentNode.insertBefore(dragging, card);
        }
    });

    // Make cards draggable via handle
    document.querySelectorAll('.slide-card__drag').forEach(function(handle) {
        handle.closest('.slide-card').setAttribute('draggable', 'true');
    });

    function saveOrder() {
        var ids = [];
        list.querySelectorAll('.slide-card').forEach(function(c) {
            ids.push(parseInt(c.dataset.id));
        });
        fetch('admin_slides.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=reordenar&orden=' + encodeURIComponent(JSON.stringify(ids))
        });
    }
})();

/* ── DEVICE SELECTOR ── */
(function() {
    var deviceBtns  = document.querySelectorAll('.device-btn');
    var deviceFrame = document.getElementById('deviceFrame');
    var deviceDims  = document.getElementById('deviceDimensions');

    var dimensions = {
        desktop: '1920 \u00d7 1080',
        tablet:  '1024 \u00d7 768',
        mobile:  '390 \u00d7 844'
    };

    deviceBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            deviceBtns.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var device = this.dataset.device;
            if (deviceFrame) deviceFrame.setAttribute('data-device', device);
            if (deviceDims) deviceDims.textContent = dimensions[device];
        });
    });
})();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

</body>
</html>
