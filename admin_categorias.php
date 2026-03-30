<?php
// ============================================================
// ADMIN — CATEGORÍAS
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$db = pdo();
$flash_ok  = '';
$flash_err = '';

// ── Ensure padre_id column exists ──
try {
    $db->query("SELECT padre_id FROM categorias LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE categorias ADD COLUMN padre_id INT DEFAULT NULL AFTER id");
    // FK is optional — shared hosting may not support it reliably
}

// ── Allowed image extensions & max size ──
$allowed_ext = ['jpg','jpeg','png','gif','webp'];
$max_size = 5 * 1024 * 1024; // 5 MB

function handle_cat_image_upload(array $file, string $dest_dir): ?string {
    global $allowed_ext, $max_size;
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return null;
    if ($file['size'] > $max_size) return null;
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }
    $filename = uniqid('cat_', true) . '.' . $ext;
    $target = $dest_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $filename;
    }
    return null;
}

// ── POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $slug_val = slug($nombre);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $padre_id = ($_POST['padre_id'] ?? '') !== '' ? (int)$_POST['padre_id'] : null;

        if ($nombre === '') {
            $flash_err = 'El nombre es obligatorio.';
        } else {
            // Check unique slug
            $chk = $db->prepare("SELECT id FROM categorias WHERE slug = ?");
            $chk->execute([$slug_val]);
            if ($chk->fetch()) {
                $slug_val .= '-' . uniqid();
            }

            $imagen = null;
            if (!empty($_FILES['imagen']['name'])) {
                $imagen = handle_cat_image_upload($_FILES['imagen'], UPLOAD_DIR);
            }

            $stmt = $db->prepare("INSERT INTO categorias (padre_id, nombre, slug, descripcion, imagen, orden, activa) VALUES (?,?,?,?,?,?,1)");
            $stmt->execute([$padre_id, $nombre, $slug_val, $descripcion, $imagen, $orden]);
            $flash_ok = "Categoría <strong>" . sanitize($nombre) . "</strong> creada.";
        }
    }

    // ── UPDATE ──
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $slug_val = slug($nombre);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $padre_id = ($_POST['padre_id'] ?? '') !== '' ? (int)$_POST['padre_id'] : null;

        if ($id < 1 || $nombre === '') {
            $flash_err = 'Datos inválidos.';
        } else {
            // Prevent setting self as parent
            if ($padre_id === $id) $padre_id = null;

            // Check unique slug (excluding self)
            $chk = $db->prepare("SELECT id FROM categorias WHERE slug = ? AND id != ?");
            $chk->execute([$slug_val, $id]);
            if ($chk->fetch()) {
                $slug_val .= '-' . uniqid();
            }

            // Handle image
            $imagen_sql = '';
            $params = [$padre_id, $nombre, $slug_val, $descripcion, $orden];
            if (!empty($_FILES['imagen']['name'])) {
                $new_img = handle_cat_image_upload($_FILES['imagen'], UPLOAD_DIR);
                if ($new_img) {
                    $old = $db->prepare("SELECT imagen FROM categorias WHERE id = ?");
                    $old->execute([$id]);
                    $old_img = $old->fetchColumn();
                    if ($old_img && file_exists(UPLOAD_DIR . $old_img)) {
                        unlink(UPLOAD_DIR . $old_img);
                    }
                    $imagen_sql = ', imagen = ?';
                    $params[] = $new_img;
                }
            }

            $params[] = $id;
            $db->prepare("UPDATE categorias SET padre_id=?, nombre=?, slug=?, descripcion=?, orden=? $imagen_sql WHERE id=?")->execute($params);
            $flash_ok = 'Categoría actualizada.';
        }
    }

    // ── DELETE ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Check if products reference this category
            $cnt = $db->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = ?");
            $cnt->execute([$id]);
            $product_count = (int)$cnt->fetchColumn();

            // Check subcategories
            $sub_cnt = $db->prepare("SELECT COUNT(*) FROM categorias WHERE padre_id = ?");
            $sub_cnt->execute([$id]);
            $sub_count = (int)$sub_cnt->fetchColumn();

            if ($product_count > 0) {
                $flash_err = "No se puede eliminar: hay $product_count producto(s) en esta categoría.";
            } elseif ($sub_count > 0) {
                $flash_err = "No se puede eliminar: tiene $sub_count subcategoría(s). Eliminalas primero.";
            } else {
                $old = $db->prepare("SELECT imagen FROM categorias WHERE id = ?");
                $old->execute([$id]);
                $old_img = $old->fetchColumn();
                if ($old_img && file_exists(UPLOAD_DIR . $old_img)) {
                    unlink(UPLOAD_DIR . $old_img);
                }
                $db->prepare("DELETE FROM categorias WHERE id = ?")->execute([$id]);
                $flash_ok = 'Categoría eliminada.';
            }
        }
    }

    // ── TOGGLE ACTIVA ──
    if ($action === 'toggle_activa') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE categorias SET activa = NOT activa WHERE id = ?")->execute([$id]);
            $flash_ok = 'Estado de categoría actualizado.';
        }
    }

    // ── REORDER ──
    if ($action === 'reorder') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (is_array($items) && count($items) > 0) {
            $stmt = $db->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
            foreach ($items as $item) {
                if (isset($item['id'], $item['orden'])) {
                    $stmt->execute([(int)$item['orden'], (int)$item['id']]);
                }
            }
            $flash_ok = 'Orden actualizado.';
        }
    }

    // PRG
    if ($flash_ok) flash('ok', $flash_ok);
    if ($flash_err) flash('err', $flash_err);
    redirect('admin_categorias.php');
}

// Retrieve flash
$flash_ok  = flash('ok');
$flash_err = flash('err');

// ── FETCH CATEGORIAS with product count, hierarchical ──
$categorias_raw = $db->query("
    SELECT c.*, COALESCE(cnt.total, 0) AS productos_count, p.nombre AS padre_nombre
    FROM categorias c
    LEFT JOIN (SELECT categoria_id, COUNT(*) AS total FROM productos GROUP BY categoria_id) cnt
        ON cnt.categoria_id = c.id
    LEFT JOIN categorias p ON c.padre_id = p.id
    ORDER BY COALESCE(c.padre_id, c.id), c.padre_id IS NOT NULL, c.orden ASC
")->fetchAll();

// Also fetch parent-only categories for the form dropdown
$cats_principales = $db->query("SELECT id, nombre FROM categorias WHERE padre_id IS NULL ORDER BY nombre")->fetchAll();

$categorias = $categorias_raw;

// ── EDIT: load category for modal ──
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $est = $db->prepare("SELECT * FROM categorias WHERE id = ?");
    $est->execute([$eid]);
    $edit = $est->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Categorías — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>
<?php $admin_page = 'categorias'; ?>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

    <!-- Flash -->
    <?php if ($flash_ok): ?>
        <div class="flash ok"><?= $flash_ok ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="flash err"><?= sanitize($flash_err) ?></div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
        <h1>Categorias</h1>
        <button class="btn btn-primary" onclick="openCatModal()">+ Nueva Categoria</button>
    </div>

    <!-- Table -->
    <?php if (empty($categorias)): ?>
        <div class="slides-empty">
            <p>No hay categorias creadas aun. Crea la primera para organizar tus productos.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap table-container">
        <table id="catTable">
            <thead>
                <tr>
                    <th></th>
                    <th>Imagen</th>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th>Productos</th>
                    <th>Activa</th>
                    <th>Orden</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $cat): ?>
                <tr data-id="<?= $cat['id'] ?>" class="<?= $cat['activa'] ? '' : 'row-muted' ?>">
                    <td><span class="drag-handle" title="Arrastrar para reordenar">&#9776;</span></td>
                    <td>
                        <?php if ($cat['imagen']): ?>
                            <img src="<?= UPLOAD_URL . sanitize($cat['imagen']) ?>" alt="" class="cat-thumb">
                        <?php else: ?>
                            <div class="cat-thumb-placeholder">---</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($cat['padre_id']): ?>
                            <span style="padding-left:24px;color:var(--text-muted);">&#8627;</span>
                        <?php endif; ?>
                        <strong><?= sanitize($cat['nombre']) ?></strong>
                        <?php if ($cat['padre_nombre'] ?? null): ?>
                            <span style="font-size:0.7rem;background:#f0f0f0;padding:1px 6px;color:#888;margin-left:6px;"><?= sanitize($cat['padre_nombre']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#71717a"><?= sanitize($cat['slug']) ?></td>
                    <td>
                        <?php if ($cat['productos_count'] > 0): ?>
                            <span class="badge badge-activa"><?= (int)$cat['productos_count'] ?></span>
                        <?php else: ?>
                            <span style="color:#52525b">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="toggle_activa">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <label class="toggle-switch">
                                <input type="checkbox" <?= $cat['activa'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </td>
                    <td><?= (int)$cat['orden'] ?></td>
                    <td>
                        <div class="actions">
                            <a href="admin_categorias.php?edit=<?= $cat['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Eliminar esta categoria?')">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="admin-footer">
        <p>&copy; <?= date('Y') ?> DyP Consultora &mdash; Panel de gestión</p>
    </footer>

</div>

<!-- ── CREATE / EDIT MODAL ── -->
<div class="modal-overlay <?= ($edit || isset($_GET['crear'])) ? 'open' : '' ?>" id="catModal">
    <div class="modal">
        <div class="modal-head">
            <h2><?= $edit ? 'Editar Categoria' : 'Nueva Categoria' ?></h2>
            <button class="modal-close" onclick="closeCatModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?= $edit['id'] ?>">
                <?php endif; ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" value="<?= $edit ? sanitize($edit['nombre']) : '' ?>" required
                               oninput="document.getElementById('slug_preview').textContent = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Slug (auto)</label>
                        <div id="slug_preview" style="padding:10px 12px;background:#f8f8f8;border:1px solid #e8e8e8;border-radius:4px;color:#999;font-size:.9rem;min-height:38px"><?= $edit ? sanitize($edit['slug']) : '' ?></div>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Categoria padre</label>
                        <select name="padre_id">
                            <option value="">— Ninguna (categoria principal) —</option>
                            <?php foreach ($cats_principales as $cp): ?>
                                <?php if ($edit && $cp['id'] == $edit['id']) continue; ?>
                                <option value="<?= $cp['id'] ?>" <?= ($edit['padre_id'] ?? '') == $cp['id'] ? 'selected' : '' ?>><?= sanitize($cp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#999;font-size:.75rem;margin-top:4px;display:block;">Si seleccionas una categoria padre, esta sera una subcategoria.</small>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="descripcion">Descripcion</label>
                        <textarea id="descripcion" name="descripcion" rows="3"><?= $edit ? sanitize($edit['descripcion']) : '' ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="imagen">Imagen</label>
                        <input type="file" id="imagen" name="imagen" accept="image/*" onchange="previewImg(this)">
                        <img id="imgPreview" class="img-preview"
                             src="<?= $edit && $edit['imagen'] ? UPLOAD_URL . sanitize($edit['imagen']) : '' ?>"
                             style="<?= $edit && $edit['imagen'] ? 'display:block' : 'display:none' ?>">
                    </div>
                    <div class="form-group">
                        <label for="orden">Orden</label>
                        <input type="number" id="orden" name="orden" value="<?= $edit ? (int)$edit['orden'] : 0 ?>" min="0">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeCatModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Guardar cambios' : 'Crear categoria' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<script src="js/admin.js"></script>
<script>
// Modal open/close
function openCatModal() {
    document.getElementById('catModal').classList.add('open');
}
function closeCatModal() {
    document.getElementById('catModal').classList.remove('open');
    // If editing, go back to clean URL
    if (window.location.search.includes('edit=')) {
        window.location.href = 'admin_categorias.php';
    }
}

// Image preview
function previewImg(input) {
    const preview = document.getElementById('imgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Close modal on overlay click
document.getElementById('catModal').addEventListener('click', function(e) {
    if (e.target === this) closeCatModal();
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCatModal();
});
</script>

</body>
</html>
