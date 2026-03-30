<?php
// ============================================================
// ADMIN — PRODUCTOS
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$db = pdo();
$flash_ok  = '';
$flash_err = '';

// ── Allowed image extensions & max size ──
$allowed_ext = ['jpg','jpeg','png','gif','webp'];
$max_size = 5 * 1024 * 1024; // 5 MB

/**
 * Handle single image upload. Returns filename or null.
 */
function handle_image_upload(array $file, string $dest_dir): ?string {
    global $allowed_ext, $max_size;
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return null;
    if ($file['size'] > $max_size) return null;
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }
    $filename = uniqid('prod_', true) . '.' . $ext;
    $target = $dest_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $filename;
    }
    return null;
}

// ── POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $slug_val = trim($_POST['slug'] ?? '') ?: slug($nombre);
        $categoria_id = (int)($_POST['categoria_id'] ?? 0) ?: null;
        $descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        $precio_oferta = ($_POST['precio_oferta'] ?? '') !== '' ? (float)$_POST['precio_oferta'] : null;
        $stock = (int)($_POST['stock'] ?? 0);
        $stock_minimo = (int)($_POST['stock_minimo'] ?? 5);
        $estado = $_POST['estado'] ?? 'activo';
        $destacado = isset($_POST['destacado']) ? 1 : 0;
        $meta_titulo = trim($_POST['meta_titulo'] ?? '');
        $meta_descripcion = trim($_POST['meta_descripcion'] ?? '');

        if ($nombre === '' || $precio <= 0) {
            $flash_err = 'Nombre y precio son obligatorios.';
        } else {
            // Image: from AJAX upload (hidden field) or traditional file upload
            $imagen = null;
            $ajax_img = trim($_POST['imagen_principal_ajax'] ?? '');
            if ($ajax_img !== '') {
                $imagen = $ajax_img;
            } elseif (!empty($_FILES['imagen_principal']['name'])) {
                $imagen = handle_image_upload($_FILES['imagen_principal'], UPLOAD_DIR);
            }

            $stmt = $db->prepare("INSERT INTO productos
                (categoria_id, nombre, slug, descripcion, descripcion_corta, precio, precio_oferta,
                 stock, stock_minimo, imagen_principal, estado, destacado, meta_titulo, meta_descripcion)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $categoria_id, $nombre, $slug_val, $descripcion, $descripcion_corta,
                $precio, $precio_oferta, $stock, $stock_minimo, $imagen,
                $estado, $destacado, $meta_titulo, $meta_descripcion
            ]);
            $new_id = (int)$db->lastInsertId();

            // Variantes
            if (!empty($_POST['var_nombre']) && is_array($_POST['var_nombre'])) {
                $vstmt = $db->prepare("INSERT INTO producto_variantes (producto_id, nombre, valor, stock_extra, precio_extra) VALUES (?,?,?,?,?)");
                foreach ($_POST['var_nombre'] as $i => $vn) {
                    $vn = trim($vn);
                    $vv = trim($_POST['var_valor'][$i] ?? '');
                    if ($vn === '' && $vv === '') continue;
                    $vs = (int)($_POST['var_stock_extra'][$i] ?? 0);
                    $vp = (float)($_POST['var_precio_extra'][$i] ?? 0);
                    $vstmt->execute([$new_id, $vn, $vv, $vs, $vp]);
                }
            }

            $flash_ok = 'Producto creado correctamente.';
        }
    }

    // ── UPDATE ──
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $slug_val = trim($_POST['slug'] ?? '') ?: slug($nombre);
        $categoria_id = (int)($_POST['categoria_id'] ?? 0) ?: null;
        $descripcion_corta = trim($_POST['descripcion_corta'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = (float)($_POST['precio'] ?? 0);
        $precio_oferta = ($_POST['precio_oferta'] ?? '') !== '' ? (float)$_POST['precio_oferta'] : null;
        $stock = (int)($_POST['stock'] ?? 0);
        $stock_minimo = (int)($_POST['stock_minimo'] ?? 5);
        $estado = $_POST['estado'] ?? 'activo';
        $destacado = isset($_POST['destacado']) ? 1 : 0;
        $meta_titulo = trim($_POST['meta_titulo'] ?? '');
        $meta_descripcion = trim($_POST['meta_descripcion'] ?? '');

        if ($id < 1 || $nombre === '' || $precio <= 0) {
            $flash_err = 'Datos inválidos.';
        } else {
            // Handle image replacement: AJAX upload or traditional
            $imagen_sql = '';
            $imagen_val = [];
            $ajax_img = trim($_POST['imagen_principal_ajax'] ?? '');
            $new_img = null;
            if ($ajax_img !== '') {
                $new_img = $ajax_img;
            } elseif (!empty($_FILES['imagen_principal']['name'])) {
                $new_img = handle_image_upload($_FILES['imagen_principal'], UPLOAD_DIR);
            }
            if ($new_img) {
                // Delete old image
                $old = $db->prepare("SELECT imagen_principal FROM productos WHERE id = ?");
                $old->execute([$id]);
                $old_img = $old->fetchColumn();
                if ($old_img && $old_img !== $new_img && file_exists(UPLOAD_DIR . $old_img)) {
                    unlink(UPLOAD_DIR . $old_img);
                }
                $imagen_sql = ', imagen_principal = ?';
                $imagen_val = [$new_img];
            }

            $sql = "UPDATE productos SET
                categoria_id=?, nombre=?, slug=?, descripcion=?, descripcion_corta=?,
                precio=?, precio_oferta=?, stock=?, stock_minimo=?,
                estado=?, destacado=?, meta_titulo=?, meta_descripcion=?
                $imagen_sql WHERE id=?";
            $params = [
                $categoria_id, $nombre, $slug_val, $descripcion, $descripcion_corta,
                $precio, $precio_oferta, $stock, $stock_minimo,
                $estado, $destacado, $meta_titulo, $meta_descripcion
            ];
            $params = array_merge($params, $imagen_val, [$id]);
            $db->prepare($sql)->execute($params);

            // Replace variantes
            $db->prepare("DELETE FROM producto_variantes WHERE producto_id = ?")->execute([$id]);
            if (!empty($_POST['var_nombre']) && is_array($_POST['var_nombre'])) {
                $vstmt = $db->prepare("INSERT INTO producto_variantes (producto_id, nombre, valor, stock_extra, precio_extra) VALUES (?,?,?,?,?)");
                foreach ($_POST['var_nombre'] as $i => $vn) {
                    $vn = trim($vn);
                    $vv = trim($_POST['var_valor'][$i] ?? '');
                    if ($vn === '' && $vv === '') continue;
                    $vs = (int)($_POST['var_stock_extra'][$i] ?? 0);
                    $vp = (float)($_POST['var_precio_extra'][$i] ?? 0);
                    $vstmt->execute([$id, $vn, $vv, $vs, $vp]);
                }
            }

            $flash_ok = 'Producto actualizado.';
        }
    }

    // ── DELETE ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Remove images from disk
            $old = $db->prepare("SELECT imagen_principal FROM productos WHERE id = ?");
            $old->execute([$id]);
            $old_img = $old->fetchColumn();
            if ($old_img && file_exists(UPLOAD_DIR . $old_img)) {
                unlink(UPLOAD_DIR . $old_img);
            }
            $gallery = $db->prepare("SELECT imagen FROM producto_imagenes WHERE producto_id = ?");
            $gallery->execute([$id]);
            foreach ($gallery->fetchAll(PDO::FETCH_COLUMN) as $gi) {
                if ($gi && file_exists(UPLOAD_DIR . $gi)) unlink(UPLOAD_DIR . $gi);
            }
            // Cascade handles producto_imagenes & producto_variantes
            $db->prepare("DELETE FROM productos WHERE id = ?")->execute([$id]);
            $flash_ok = 'Producto eliminado.';
        }
    }

    // ── TOGGLE ESTADO ──
    if ($action === 'toggle_estado') {
        $id = (int)($_POST['id'] ?? 0);
        $nuevo = $_POST['nuevo_estado'] ?? 'activo';
        if ($id > 0 && in_array($nuevo, ['activo','borrador','agotado'])) {
            $db->prepare("UPDATE productos SET estado = ? WHERE id = ?")->execute([$nuevo, $id]);
            $flash_ok = 'Estado actualizado.';
        }
    }

    // ── TOGGLE DESTACADO ──
    if ($action === 'toggle_destacado') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE productos SET destacado = NOT destacado WHERE id = ?")->execute([$id]);
            $flash_ok = 'Destacado actualizado.';
        }
    }

    // ── UPLOAD GALLERY ──
    if ($action === 'upload_gallery') {
        $id = (int)($_POST['producto_id'] ?? 0);
        if ($id > 0 && !empty($_FILES['galeria'])) {
            $count = 0;
            $files = $_FILES['galeria'];
            $total = is_array($files['name']) ? count($files['name']) : 0;
            for ($i = 0; $i < $total; $i++) {
                $f = [
                    'name'     => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i]
                ];
                $fname = handle_image_upload($f, UPLOAD_DIR);
                if ($fname) {
                    $db->prepare("INSERT INTO producto_imagenes (producto_id, imagen, orden) VALUES (?,?,?)")
                       ->execute([$id, $fname, $i]);
                    $count++;
                }
            }
            $flash_ok = "$count imagen(es) subida(s).";
        }
    }

    // ── IMPORT CSV ──
    if ($action === 'import_csv') {
        if (!empty($_FILES['csv_file']['tmp_name']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                $flash_err = 'Solo archivos CSV.';
            } else {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($handle, 0, ',');
                $imported = 0;
                $stmt = $db->prepare("INSERT INTO productos
                    (nombre, slug, categoria_id, descripcion_corta, descripcion, precio, precio_oferta, stock, stock_minimo, estado, destacado)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    if (count($row) < 6) continue;
                    $n = trim($row[0] ?? '');
                    if ($n === '') continue;
                    $s = slug($n);
                    $cat = (int)($row[1] ?? 0) ?: null;
                    $dc  = trim($row[2] ?? '');
                    $d   = trim($row[3] ?? '');
                    $p   = (float)($row[4] ?? 0);
                    $po  = ($row[5] ?? '') !== '' ? (float)$row[5] : null;
                    $st  = (int)($row[6] ?? 0);
                    $sm  = (int)($row[7] ?? 5);
                    $es  = trim($row[8] ?? 'activo') ?: 'activo';
                    $de  = (int)($row[9] ?? 0);
                    $stmt->execute([$n, $s, $cat, $dc, $d, $p, $po, $st, $sm, $es, $de]);
                    $imported++;
                }
                fclose($handle);
                $flash_ok = "$imported producto(s) importados desde CSV.";
            }
        } else {
            $flash_err = 'Error al subir el archivo CSV.';
        }
    }

    // Redirect to avoid resubmit (PRG pattern)
    if ($flash_ok) flash('ok', $flash_ok);
    if ($flash_err) flash('err', $flash_err);
    redirect('admin_productos.php?' . http_build_query(array_filter([
        'cat'       => $_GET['cat'] ?? '',
        'estado'    => $_GET['estado'] ?? '',
        'stock_bajo'=> $_GET['stock_bajo'] ?? '',
        'q'         => $_GET['q'] ?? '',
        'pag'       => $_GET['pag'] ?? ''
    ])));
}

// Retrieve flash
$flash_ok  = flash('ok');
$flash_err = flash('err');

// ── GET FILTERS & QUERY ──
$filter_cat   = trim($_GET['cat'] ?? '');
$filter_estado = trim($_GET['estado'] ?? '');
$filter_stock  = (int)($_GET['stock_bajo'] ?? 0);
$filter_q      = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['pag'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where = '1=1';
$params = [];

if ($filter_cat !== '') {
    $where .= ' AND p.categoria_id = ?';
    $params[] = (int)$filter_cat;
}
if ($filter_estado !== '') {
    $where .= ' AND p.estado = ?';
    $params[] = $filter_estado;
}
if ($filter_stock) {
    $where .= ' AND p.stock <= p.stock_minimo';
}
if ($filter_q !== '') {
    $where .= ' AND (p.nombre LIKE ? OR p.slug LIKE ?)';
    $params[] = "%$filter_q%";
    $params[] = "%$filter_q%";
}

// Total count
$cnt = $db->prepare("SELECT COUNT(*) FROM productos p WHERE $where");
$cnt->execute($params);
$total_rows = (int)$cnt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// Fetch products
$sql = "SELECT p.*, c.nombre AS cat_nombre
        FROM productos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE $where
        ORDER BY p.id DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Categories for filters / selects
// Fetch categories with hierarchy
$_cat_principales = $db->query("SELECT id, nombre FROM categorias WHERE padre_id IS NULL ORDER BY nombre")->fetchAll();
$categorias = [];
foreach ($_cat_principales as $cp) {
    $categorias[] = $cp;
    $hijas_stmt = $db->prepare("SELECT id, nombre FROM categorias WHERE padre_id = ? ORDER BY nombre");
    $hijas_stmt->execute([$cp['id']]);
    $hijas = $hijas_stmt->fetchAll();
    foreach ($hijas as $h) {
        $h['_es_hija'] = true;
        $h['_padre_nombre'] = $cp['nombre'];
        $categorias[] = $h;
    }
}

// ── If editing, load product + variantes + gallery ──
$edit = null;
$edit_variantes = [];
$edit_galeria = [];
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $est = $db->prepare("SELECT * FROM productos WHERE id = ?");
    $est->execute([$eid]);
    $edit = $est->fetch();
    if ($edit) {
        $evst = $db->prepare("SELECT * FROM producto_variantes WHERE producto_id = ? ORDER BY id");
        $evst->execute([$eid]);
        $edit_variantes = $evst->fetchAll();
        $egst = $db->prepare("SELECT * FROM producto_imagenes WHERE producto_id = ? ORDER BY orden");
        $egst->execute([$eid]);
        $edit_galeria = $egst->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Productos — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>
<?php $admin_page = 'productos'; ?>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

    <!-- Flash -->
    <?php if ($flash_ok): ?>
        <div class="flash ok"><?= sanitize($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="flash err"><?= sanitize($flash_err) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="page-header">
        <h1>Productos <small style="font-size:.85rem;color:#71717a">(<?= $total_rows ?>)</small></h1>
        <div class="header-btns">
            <button class="btn btn-primary" onclick="openProductModal()">+ Nuevo producto</button>
            <button type="button" onclick="abrirModalExportar()" style="
              height:42px;padding:0 20px;border:1.5px solid #e5e7eb;border-radius:10px;
              background:#fff;color:#374151;font-size:0.82rem;font-weight:700;
              text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;
              white-space:nowrap;font-family:inherit;transition:all 0.2s;
            ">&#11015; Exportar CSV</button>
            <button type="button" onclick="document.getElementById('csvModal').classList.add('open')" style="
              height:42px;padding:0 20px;border:1.5px solid #e5e7eb;border-radius:10px;
              background:#fff;color:#374151;font-size:0.82rem;font-weight:700;
              text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;
              white-space:nowrap;font-family:inherit;transition:all 0.2s;
            ">&#11014; Importar CSV</button>
        </div>
    </div>

    <!-- Toolbar -->
    <form class="toolbar" method="GET">
        <input type="text" name="q" placeholder="Buscar producto..." value="<?= sanitize($filter_q) ?>">
        <select name="cat">
            <option value="">Todas las categorias</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_cat == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="activo" <?= $filter_estado==='activo'?'selected':'' ?>>Activo</option>
            <option value="borrador" <?= $filter_estado==='borrador'?'selected':'' ?>>Borrador</option>
            <option value="agotado" <?= $filter_estado==='agotado'?'selected':'' ?>>Agotado</option>
        </select>
        <label class="toggle-filter" style="
          display: flex;
          align-items: center;
          gap: 10px;
          height: 42px;
          padding: 0 16px;
          border: 1.5px solid <?= isset($_GET['stock_bajo']) ? '#7c3aed' : '#e5e7eb' ?>;
          border-radius: 10px;
          background: <?= isset($_GET['stock_bajo']) ? '#f5f3ff' : '#fff' ?>;
          cursor: pointer;
          font-size: 0.82rem;
          font-weight: 600;
          color: <?= isset($_GET['stock_bajo']) ? '#7c3aed' : '#6b7280' ?>;
          white-space: nowrap;
          user-select: none;
          transition: all 0.2s;
          box-sizing: border-box;
        " onclick="toggleStockBajo(this)">

          <span>Stock bajo</span>

          <div id="toggleTrack" style="
            width: 36px;
            height: 20px;
            border-radius: 10px;
            background: <?= isset($_GET['stock_bajo']) ? '#7c3aed' : '#d1d5db' ?>;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
          ">
            <div id="toggleThumb" style="
              position: absolute;
              top: 2px;
              left: <?= isset($_GET['stock_bajo']) ? '18px' : '2px' ?>;
              width: 16px;
              height: 16px;
              border-radius: 50%;
              background: #fff;
              transition: left 0.2s;
              box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            "></div>
          </div>

          <input type="checkbox" name="stock_bajo" value="1"
            id="stockBajoInput"
            <?= isset($_GET['stock_bajo']) ? 'checked' : '' ?>
            style="display:none;">
        </label>

        <script>
        function toggleStockBajo(label) {
          var input = document.getElementById('stockBajoInput');
          var track = document.getElementById('toggleTrack');
          var thumb = document.getElementById('toggleThumb');
          var active = !input.checked;

          input.checked = active;

          if (active) {
            track.style.background = '#7c3aed';
            thumb.style.left = '18px';
            label.style.borderColor = '#7c3aed';
            label.style.background = '#f5f3ff';
            label.style.color = '#7c3aed';
          } else {
            track.style.background = '#d1d5db';
            thumb.style.left = '2px';
            label.style.borderColor = '#e5e7eb';
            label.style.background = '#fff';
            label.style.color = '#6b7280';
          }

          label.closest('form').submit();
        }
        </script>
        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    </form>

    <!-- Table -->
    <?php if (count($productos) === 0): ?>
        <div class="table-wrap"><p class="empty">No hay productos<?= ($filter_q||$filter_cat||$filter_estado||$filter_stock) ? ' con esos filtros' : ' todavia' ?>.</p></div>
    <?php else: ?>
    <div class="table-wrap table-container">
        <table>
            <thead>
            <tr>
                <th><input type="checkbox" id="checkAll"></th>
                <th>Imagen</th>
                <th>Nombre</th>
                <th>Categoria</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Estado</th>
                <th>Dest.</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
            <tr>
                <td><input type="checkbox" class="row-check" value="<?= $p['id'] ?>"></td>
                <td>
                    <?php if ($p['imagen_principal']): ?>
                        <img src="<?= UPLOAD_URL . sanitize($p['imagen_principal']) ?>" class="thumb" alt="">
                    <?php else: ?>
                        <span class="no-img"></span>
                    <?php endif; ?>
                </td>
                <td><strong><?= sanitize($p['nombre']) ?></strong></td>
                <td><?= sanitize($p['cat_nombre'] ?? '—') ?></td>
                <td>
                    <?php if ($p['precio_oferta']): ?>
                        <span style="text-decoration:line-through;color:#71717a"><?= price($p['precio']) ?></span>
                        <span style="color:#4ade80"><?= price($p['precio_oferta']) ?></span>
                    <?php else: ?>
                        <?= price($p['precio']) ?>
                    <?php endif; ?>
                </td>
                <td class="<?= $p['stock'] <= $p['stock_minimo'] ? 'stock-low' : '' ?>">
                    <?= (int)$p['stock'] ?>
                </td>
                <td>
                    <span class="badge badge-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span>
                </td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle_destacado">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="star-btn <?= $p['destacado'] ? 'active' : '' ?>" title="Destacado">&#9733;</button>
                    </form>
                </td>
                <td>
                    <div class="actions">
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Eliminar este producto?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $qs = array_filter(['cat'=>$filter_cat,'estado'=>$filter_estado,'stock_bajo'=>$filter_stock?'1':'','q'=>$filter_q]);
        for ($i = 1; $i <= $total_pages; $i++):
            $qs['pag'] = $i;
            $link = '?' . http_build_query($qs);
        ?>
            <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= $link ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div><!-- /.page -->

<!-- ================================================================ -->
<!-- PRODUCT MODAL (Create / Edit)                                     -->
<!-- ================================================================ -->
<div class="modal-overlay <?= $edit ? 'open' : '' ?>" id="productModal">
<div class="modal">
    <div class="modal-head">
        <h2><?= $edit ? 'Editar producto' : 'Nuevo producto' ?></h2>
        <button class="modal-close" onclick="closeProductModal()">&times;</button>
    </div>
    <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?= $edit['id'] ?>">
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button type="button" class="active" data-tab="tab-general">General</button>
                <button type="button" data-tab="tab-images">Imagenes</button>
                <button type="button" data-tab="tab-variants">Variantes</button>
                <button type="button" data-tab="tab-seo">SEO</button>
            </div>

            <!-- TAB: General -->
            <div class="tab-pane active" id="tab-general">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" id="inp_nombre" required value="<?= sanitize($edit['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Slug</label>
                        <input type="text" name="slug" id="inp_slug" value="<?= sanitize($edit['slug'] ?? '') ?>" placeholder="auto-generado">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria_id">
                            <option value="">Sin categoria</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($edit['categoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= !empty($c['_es_hija']) ? '&nbsp;&nbsp;&#8627; ' : '' ?><?= sanitize($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <?php foreach (['activo','borrador','agotado'] as $e): ?>
                                <option value="<?= $e ?>" <?= ($edit['estado'] ?? 'activo') === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Descripcion corta</label>
                        <textarea name="descripcion_corta" rows="2" maxlength="300"><?= sanitize($edit['descripcion_corta'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Descripcion</label>
                        <textarea name="descripcion" rows="6"><?= sanitize($edit['descripcion'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Precio *</label>
                        <input type="number" name="precio" step="0.01" min="0" required value="<?= $edit['precio'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Precio oferta</label>
                        <input type="number" name="precio_oferta" step="0.01" min="0" value="<?= $edit['precio_oferta'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" value="<?= $edit['stock'] ?? 0 ?>">
                    </div>
                    <div class="form-group">
                        <label>Stock minimo alerta</label>
                        <input type="number" name="stock_minimo" min="0" value="<?= $edit['stock_minimo'] ?? 5 ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <label class="checkbox-label">
                        <input type="checkbox" name="destacado" value="1" <?= ($edit['destacado'] ?? 0) ? 'checked' : '' ?>>
                        Producto destacado
                    </label>
                </div>
            </div>

            <!-- TAB: Images -->
            <div class="tab-pane" id="tab-images">
                <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:8px;">Imagen principal</label>
                <input type="hidden" name="imagen_principal_ajax" id="imagenPrincipalAjax" value="<?= sanitize($edit['imagen_principal'] ?? '') ?>">

                <!-- Preview -->
                <div id="previewPrincipal" style="<?= ($edit && $edit['imagen_principal']) ? '' : 'display:none;' ?>position:relative;margin-bottom:12px;">
                    <img id="imgPreviewPrincipal" src="<?= ($edit && $edit['imagen_principal']) ? UPLOAD_URL . sanitize($edit['imagen_principal']) : '' ?>" style="width:100%;max-height:280px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                    <button type="button" onclick="quitarImagenPrincipal()" style="position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,0.6);color:#fff;border:none;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;">&times;</button>
                </div>

                <!-- Drop zone -->
                <div id="dropZonePrincipal" style="<?= ($edit && $edit['imagen_principal']) ? 'display:none;' : '' ?>border:2px dashed var(--border);border-radius:12px;padding:40px 20px;text-align:center;cursor:pointer;transition:all 0.2s;background:var(--bg);"
                     ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
                     ondragleave="this.style.borderColor='var(--border)'"
                     ondrop="handleDropPrincipal(event)">
                    <div style="font-size:2rem;margin-bottom:8px;">&#128444;</div>
                    <p style="font-weight:600;color:var(--text);margin:0 0 4px;">Hacé click o arrastrá la imagen aquí</p>
                    <small style="color:var(--text-muted);">JPG, PNG, WebP &middot; Máx 5MB</small>
                    <input type="file" id="inputImagenPrincipal" name="imagen_principal" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="handleImagenPrincipal(this)">
                </div>

                <div id="uploadProgress" style="display:none;margin-top:8px;">
                    <div style="width:100%;background:var(--border);height:4px;border-radius:2px;overflow:hidden;">
                        <div id="uploadProgressBar" style="width:0%;height:100%;background:var(--primary);transition:width 0.3s;"></div>
                    </div>
                    <small style="color:var(--text-muted);">Subiendo imagen...</small>
                </div>

                <?php if ($edit): ?>
                <hr style="border-color:#27272a;margin:16px 0">
                <p style="font-size:.88rem;font-weight:600;margin-bottom:10px">Galeria</p>
                <?php if (count($edit_galeria)): ?>
                    <div class="gallery-grid">
                    <?php foreach ($edit_galeria as $gi): ?>
                        <div class="gallery-item">
                            <img src="<?= UPLOAD_URL . sanitize($gi['imagen']) ?>" alt="">
                            <button type="button" class="del-img" title="Eliminar"
                                onclick="eliminarGaleria(<?= (int)$gi['id'] ?>, <?= (int)$edit['id'] ?>, this)">&times;</button>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div style="margin-top:10px">
                    <div class="form-group">
                        <label>Agregar imagenes a la galeria</label>
                        <input type="file" id="galeriaInput" accept="image/*" multiple>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" style="margin-top:8px"
                        onclick="subirGaleria(<?= (int)$edit['id'] ?>)">Subir imagenes</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: Variants -->
            <div class="tab-pane" id="tab-variants">
                <table class="var-table" id="varTable">
                    <thead>
                        <tr><th>Nombre</th><th>Valor</th><th>Stock extra</th><th>Precio extra</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if ($edit && count($edit_variantes)): ?>
                        <?php foreach ($edit_variantes as $v): ?>
                        <tr>
                            <td><input type="text" name="var_nombre[]" value="<?= sanitize($v['nombre']) ?>"></td>
                            <td><input type="text" name="var_valor[]" value="<?= sanitize($v['valor']) ?>"></td>
                            <td><input type="number" name="var_stock_extra[]" value="<?= (int)$v['stock_extra'] ?>"></td>
                            <td><input type="number" name="var_precio_extra[]" step="0.01" value="<?= $v['precio_extra'] ?>"></td>
                            <td><button type="button" class="remove-row" onclick="this.closest('tr').remove()">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="btn btn-outline btn-sm" onclick="addVariantRow()">+ Agregar variante</button>
            </div>

            <!-- TAB: SEO -->
            <div class="tab-pane" id="tab-seo">
                <div class="form-row full">
                    <div class="form-group">
                        <label>Meta titulo</label>
                        <input type="text" name="meta_titulo" maxlength="200" id="seo_titulo" value="<?= sanitize($edit['meta_titulo'] ?? '') ?>">
                        <span class="char-counter" id="seo_titulo_count">0 / 200</span>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label>Meta descripcion</label>
                        <textarea name="meta_descripcion" rows="3" maxlength="300" id="seo_desc"><?= sanitize($edit['meta_descripcion'] ?? '') ?></textarea>
                        <span class="char-counter" id="seo_desc_count">0 / 300</span>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid #27272a">
                <button type="button" class="btn btn-outline" onclick="closeProductModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= $edit ? 'Guardar cambios' : 'Crear producto' ?></button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- ================================================================ -->
<!-- CSV IMPORT MODAL — con mapeo de columnas                          -->
<!-- ================================================================ -->
<div class="modal-overlay" id="csvModal">
<div class="modal" style="max-width:600px">
    <div class="modal-head">
        <h2>Importar productos desde CSV</h2>
        <button class="modal-close" onclick="document.getElementById('csvModal').classList.remove('open')">&times;</button>
    </div>
    <div class="modal-body">
        <!-- PASO 1: Subir archivo -->
        <div id="importPaso1">
          <p style="font-size:0.85rem;color:#6b7280;margin-bottom:16px;">
            Subí tu archivo CSV. En el siguiente paso vas a poder mapear las columnas.
          </p>
          <div class="form-group" style="margin-bottom:16px">
            <label>Archivo CSV</label>
            <input type="file" id="csvFileInput" accept=".csv" required
              style="padding:10px;border:1.5px dashed #e5e7eb;border-radius:10px;width:100%;box-sizing:border-box;">
          </div>
          <button type="button" onclick="leerCSVParaMapeo()" style="
            width:100%;height:42px;background:#7c3aed;color:#fff;border:none;border-radius:10px;
            font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;">
            Siguiente: Mapear columnas
          </button>
        </div>

        <!-- PASO 2: Mapear columnas -->
        <div id="importPaso2" style="display:none">
          <p style="font-size:0.85rem;color:#6b7280;margin-bottom:16px;">
            Mapeá cada columna del CSV al campo correspondiente:
          </p>
          <div id="mapeoContainer"></div>

          <p style="font-size:0.78rem;font-weight:700;text-transform:uppercase;color:#9ca3af;margin:20px 0 10px;">
            Preview (primeras 3 filas)
          </p>
          <div id="previewContainer" style="overflow-x:auto;margin-bottom:20px;"></div>

          <div style="display:flex;gap:10px;">
            <button type="button" onclick="volverPaso1()" style="
              flex:1;height:42px;border:1.5px solid #e5e7eb;border-radius:10px;
              background:#fff;color:#374151;font-size:0.82rem;font-weight:700;
              text-transform:uppercase;cursor:pointer;">
              &#8592; Volver
            </button>
            <button type="button" onclick="ejecutarImport()" style="
              flex:2;height:42px;background:#7c3aed;color:#fff;border:none;border-radius:10px;
              font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;">
              Importar productos
            </button>
          </div>
        </div>

        <!-- PASO 3: Resultado -->
        <div id="importPaso3" style="display:none;text-align:center;padding:20px 0;">
          <div id="importResultado"></div>
          <button type="button" onclick="location.reload()" style="
            margin-top:20px;height:42px;padding:0 24px;background:#7c3aed;color:#fff;border:none;
            border-radius:10px;font-size:0.82rem;font-weight:700;text-transform:uppercase;cursor:pointer;">
            Cerrar
          </button>
        </div>
    </div>
</div>
</div>

<!-- ================================================================ -->
<!-- CSV EXPORT MODAL                                                  -->
<!-- ================================================================ -->
<div id="modalExportar" style="display:none;position:fixed;inset:0;
  background:rgba(0,0,0,0.5);z-index:2000;
  align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:16px;padding:32px;
              max-width:520px;width:100%;max-height:90vh;overflow-y:auto;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <h3 style="margin:0;font-size:1.1rem;font-weight:700;">Exportar productos</h3>
      <button onclick="cerrarModalExportar()" style="background:none;border:none;
        font-size:1.2rem;cursor:pointer;color:#9ca3af;">&#10005;</button>
    </div>

    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:16px;">
      Seleccioná los campos que querés incluir en el CSV:
    </p>

    <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;
                  padding-bottom:16px;border-bottom:1px solid #f0f0f0;
                  cursor:pointer;font-weight:600;font-size:0.85rem;">
      <input type="checkbox" id="selectAllExport" checked
             onchange="toggleTodosExport(this)"
             style="width:16px;height:16px;accent-color:#7c3aed;">
      Seleccionar todos
    </label>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px;">
      <?php
      $campos_export = [
        'id'=>'ID','nombre'=>'Nombre','descripcion'=>'Descripción',
        'descripcion_corta'=>'Desc. corta','precio'=>'Precio',
        'precio_oferta'=>'Precio oferta','stock'=>'Stock',
        'stock_minimo'=>'Stock mínimo','categoria'=>'Categoría',
        'estado'=>'Estado','destacado'=>'Destacado',
        'imagen_principal'=>'Imagen','slug'=>'Slug/URL',
        'meta_titulo'=>'Meta título','meta_descripcion'=>'Meta descripción',
        'total_ventas'=>'Total ventas','fecha_creacion'=>'Fecha creación',
      ];
      foreach ($campos_export as $key => $label):
      ?>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                    font-size:0.85rem;color:#374151;padding:8px 12px;
                    border:1.5px solid #e5e7eb;border-radius:8px;
                    transition:all 0.15s;" class="campo-export-label">
        <input type="checkbox" name="campos_export[]" value="<?= $key ?>"
               checked class="campo-export-cb"
               style="width:15px;height:15px;accent-color:#7c3aed;">
        <?= $label ?>
      </label>
      <?php endforeach; ?>
    </div>

    <div style="padding:16px;background:#f9fafb;border-radius:10px;margin-bottom:24px;">
      <p style="font-size:0.78rem;font-weight:700;text-transform:uppercase;
                letter-spacing:0.06em;color:#9ca3af;margin-bottom:12px;">Opciones</p>
      <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;margin-bottom:8px;">
        <input type="checkbox" id="exportSoloActivos" style="accent-color:#7c3aed;">
        Solo productos activos
      </label>
      <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;cursor:pointer;">
        <input type="checkbox" id="exportConEncabezado" checked style="accent-color:#7c3aed;">
        Incluir fila de encabezado
      </label>
    </div>

    <div style="display:flex;gap:10px;">
      <button onclick="cerrarModalExportar()" style="
        flex:1;height:42px;border:1.5px solid #e5e7eb;border-radius:10px;
        background:#fff;color:#374151;font-size:0.82rem;font-weight:700;
        text-transform:uppercase;cursor:pointer;">
        Cancelar
      </button>
      <button onclick="ejecutarExport()" style="
        flex:2;height:42px;background:#7c3aed;color:#fff;border:none;
        border-radius:10px;font-size:0.82rem;font-weight:700;
        text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;">
        &#11015; Descargar CSV
      </button>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<script src="js/admin.js"></script>
<script>
// ── Modal helpers ──
function openProductModal(){
    // Reset form for new product
    const f = document.getElementById('productForm');
    if(f) f.reset();
    const hiddenAction = f.querySelector('input[name="action"]');
    if(hiddenAction) hiddenAction.value = 'create';
    const hiddenId = f.querySelector('input[name="id"]');
    if(hiddenId) hiddenId.remove();
    // Clear variant rows
    document.querySelector('#varTable tbody').innerHTML = '';
    document.getElementById('productModal').classList.add('open');
    switchTab('tab-general');
}
function closeProductModal(){
    document.getElementById('productModal').classList.remove('open');
    // If was editing, navigate away from ?edit=
    if(window.location.search.includes('edit=')){
        window.location.href = 'admin_productos.php';
    }
}

// ── Close modals on overlay click ──
document.querySelectorAll('.modal-overlay').forEach(function(ov){
    ov.addEventListener('click', function(e){
        if(e.target === ov){
            ov.classList.remove('open');
            if(ov.id === 'productModal' && window.location.search.includes('edit=')){
                window.location.href = 'admin_productos.php';
            }
        }
    });
});

// ── Tabs ──
document.querySelectorAll('.tabs button').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});
function switchTab(tabId){
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    const btn = document.querySelector(`.tabs button[data-tab="${tabId}"]`);
    if(btn) btn.classList.add('active');
    const pane = document.getElementById(tabId);
    if(pane) pane.classList.add('active');
}

// ── Auto-slug ──
const inpNombre = document.getElementById('inp_nombre');
const inpSlug = document.getElementById('inp_slug');
if(inpNombre && inpSlug){
    inpNombre.addEventListener('input', () => {
        if(!inpSlug.dataset.manual){
            inpSlug.value = inpNombre.value.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
                .replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
        }
    });
    inpSlug.addEventListener('input', () => { inpSlug.dataset.manual = '1'; });
}

// ── Add variant row ──
function addVariantRow(){
    const tbody = document.querySelector('#varTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="var_nombre[]"></td>
        <td><input type="text" name="var_valor[]"></td>
        <td><input type="number" name="var_stock_extra[]" value="0"></td>
        <td><input type="number" name="var_precio_extra[]" step="0.01" value="0"></td>
        <td><button type="button" class="remove-row" onclick="this.closest('tr').remove()">&times;</button></td>`;
    tbody.appendChild(tr);
}

// ── SEO char counters ──
function initCounter(inputId, counterId, max){
    const inp = document.getElementById(inputId);
    const cnt = document.getElementById(counterId);
    if(!inp||!cnt) return;
    const update = () => { cnt.textContent = inp.value.length + ' / ' + max; };
    inp.addEventListener('input', update);
    update();
}
initCounter('seo_titulo','seo_titulo_count',200);
initCounter('seo_desc','seo_desc_count',300);

// ── Check all ──
const checkAll = document.getElementById('checkAll');
if(checkAll){
    checkAll.addEventListener('change', () => {
        document.querySelectorAll('.row-check').forEach(cb => { cb.checked = checkAll.checked; });
    });
}

// ══════════ EXPORT CSV ══════════
function abrirModalExportar() {
  document.getElementById('modalExportar').style.display = 'flex';
}
function cerrarModalExportar() {
  document.getElementById('modalExportar').style.display = 'none';
}
function toggleTodosExport(cb) {
  document.querySelectorAll('.campo-export-cb').forEach(c => {
    c.checked = cb.checked;
    var lbl = c.closest('label');
    if(lbl){ lbl.style.borderColor = c.checked ? '#7c3aed' : '#e5e7eb'; lbl.style.background = c.checked ? '#f5f3ff' : '#fff'; }
  });
}
document.addEventListener('change', function(e) {
  if (e.target.classList.contains('campo-export-cb')) {
    var todos = document.querySelectorAll('.campo-export-cb');
    var checkeados = document.querySelectorAll('.campo-export-cb:checked');
    document.getElementById('selectAllExport').checked = todos.length === checkeados.length;
    var label = e.target.closest('label');
    if (label) {
      label.style.borderColor = e.target.checked ? '#7c3aed' : '#e5e7eb';
      label.style.background = e.target.checked ? '#f5f3ff' : '#fff';
    }
  }
});
function ejecutarExport() {
  var campos = [];
  document.querySelectorAll('.campo-export-cb:checked').forEach(function(c){ campos.push(c.value); });
  if (!campos.length) { alert('Seleccioná al menos un campo'); return; }
  var soloActivos = document.getElementById('exportSoloActivos').checked ? '1' : '0';
  var conEncabezado = document.getElementById('exportConEncabezado').checked ? '1' : '0';
  var form = document.createElement('form');
  form.method = 'POST'; form.action = 'export_productos.php';
  campos.forEach(function(c){
    var inp = document.createElement('input'); inp.type='hidden'; inp.name='campos[]'; inp.value=c; form.appendChild(inp);
  });
  var i1 = document.createElement('input'); i1.type='hidden'; i1.name='solo_activos'; i1.value=soloActivos; form.appendChild(i1);
  var i2 = document.createElement('input'); i2.type='hidden'; i2.name='con_encabezado'; i2.value=conEncabezado; form.appendChild(i2);
  document.body.appendChild(form); form.submit(); document.body.removeChild(form);
  cerrarModalExportar();
}

// ══════════ IMPORT CSV con mapeo ══════════
var csvHeaders = [];
var csvRows = [];
var camposDestino = [
  {v:'',l:'— Ignorar —'},{v:'nombre',l:'Nombre'},{v:'descripcion',l:'Descripción'},
  {v:'descripcion_corta',l:'Desc. corta'},{v:'precio',l:'Precio'},{v:'precio_oferta',l:'Precio oferta'},
  {v:'stock',l:'Stock'},{v:'stock_minimo',l:'Stock mínimo'},{v:'categoria',l:'Categoría'},
  {v:'estado',l:'Estado'},{v:'destacado',l:'Destacado'},{v:'slug',l:'Slug'},
  {v:'meta_titulo',l:'Meta título'},{v:'meta_descripcion',l:'Meta descripción'}
];
var autoMap = {'nombre':'nombre','name':'nombre','descripcion':'descripcion','description':'descripcion',
  'descripcion_corta':'descripcion_corta','short_description':'descripcion_corta',
  'precio':'precio','price':'precio','precio_oferta':'precio_oferta','sale_price':'precio_oferta',
  'stock':'stock','stock_minimo':'stock_minimo','categoria':'categoria','category':'categoria',
  'estado':'estado','status':'estado','destacado':'destacado','featured':'destacado',
  'slug':'slug','meta_titulo':'meta_titulo','meta_descripcion':'meta_descripcion'};

function leerCSVParaMapeo() {
  var file = document.getElementById('csvFileInput').files[0];
  if (!file) { alert('Seleccioná un archivo CSV'); return; }
  var reader = new FileReader();
  reader.onload = function(e) {
    var lines = e.target.result.split('\n').filter(function(l){ return l.trim(); });
    if (lines.length < 2) { alert('El CSV debe tener al menos 2 filas'); return; }
    csvHeaders = parseCSVLine(lines[0]);
    csvRows = [];
    for (var i = 1; i < Math.min(lines.length, 50); i++) { csvRows.push(parseCSVLine(lines[i])); }
    renderMapeo();
    document.getElementById('importPaso1').style.display = 'none';
    document.getElementById('importPaso2').style.display = 'block';
  };
  reader.readAsText(file, 'UTF-8');
}
function parseCSVLine(line) {
  var result = []; var current = ''; var inQuotes = false;
  for (var i = 0; i < line.length; i++) {
    var c = line[i];
    if (c === '"') { inQuotes = !inQuotes; }
    else if ((c === ',' || c === ';') && !inQuotes) { result.push(current.trim()); current = ''; }
    else { current += c; }
  }
  result.push(current.trim());
  return result;
}
function renderMapeo() {
  var html = '';
  csvHeaders.forEach(function(h, i) {
    var key = h.toLowerCase().replace(/[^a-z_]/g,'');
    var detected = autoMap[key] || '';
    html += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">';
    html += '<span style="flex:1;font-size:0.85rem;color:#374151;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + h + ' →</span>';
    html += '<select id="mapeo_'+i+'" style="flex:1;height:36px;border:1.5px solid #e5e7eb;border-radius:8px;padding:0 10px;font-size:0.85rem;">';
    camposDestino.forEach(function(cd) {
      html += '<option value="'+cd.v+'"'+(cd.v===detected?' selected':'')+'>'+cd.l+'</option>';
    });
    html += '</select></div>';
  });
  document.getElementById('mapeoContainer').innerHTML = html;
  renderPreview();
}
function renderPreview() {
  var preview = csvRows.slice(0, 3);
  if (!preview.length) return;
  var html = '<table style="width:100%;border-collapse:collapse;font-size:0.78rem;">';
  html += '<tr>';
  csvHeaders.forEach(function(h){ html += '<th style="padding:6px 8px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;white-space:nowrap;">'+h+'</th>'; });
  html += '</tr>';
  preview.forEach(function(row){
    html += '<tr>';
    row.forEach(function(cell){ html += '<td style="padding:6px 8px;border:1px solid #e5e7eb;white-space:nowrap;max-width:150px;overflow:hidden;text-overflow:ellipsis;">'+cell+'</td>'; });
    html += '</tr>';
  });
  html += '</table>';
  document.getElementById('previewContainer').innerHTML = html;
}
function volverPaso1() {
  document.getElementById('importPaso2').style.display = 'none';
  document.getElementById('importPaso1').style.display = 'block';
}
function ejecutarImport() {
  var mapeo = {};
  csvHeaders.forEach(function(h, i) {
    var sel = document.getElementById('mapeo_'+i);
    if (sel && sel.value) { mapeo[i] = sel.value; }
  });
  if (Object.keys(mapeo).length === 0) { alert('Mapeá al menos un campo'); return; }
  var formData = new FormData();
  formData.append('csv_file', document.getElementById('csvFileInput').files[0]);
  formData.append('mapeo', JSON.stringify(mapeo));
  document.getElementById('importPaso2').style.display = 'none';
  document.getElementById('importPaso3').style.display = 'block';
  document.getElementById('importResultado').innerHTML = '<p style="color:#6b7280;">Importando...</p>';
  fetch('import_productos.php', { method: 'POST', body: formData })
    .then(function(r){ return r.json(); })
    .then(function(data){
      var html = '<div style="font-size:3rem;margin-bottom:12px;">'+(data.ok ? '✓' : '✗')+'</div>';
      html += '<p style="font-size:1rem;font-weight:700;margin-bottom:8px;">'+(data.ok ? 'Importación completada' : 'Error')+'</p>';
      if (data.importados !== undefined) html += '<p style="color:#10b981;font-weight:600;">'+data.importados+' importados</p>';
      if (data.actualizados !== undefined) html += '<p style="color:#7c3aed;font-weight:600;">'+data.actualizados+' actualizados</p>';
      if (data.errores !== undefined && data.errores > 0) html += '<p style="color:#ef4444;font-weight:600;">'+data.errores+' errores</p>';
      if (data.mensaje) html += '<p style="color:#6b7280;font-size:0.85rem;margin-top:8px;">'+data.mensaje+'</p>';
      document.getElementById('importResultado').innerHTML = html;
    })
    .catch(function(){
      document.getElementById('importResultado').innerHTML = '<p style="color:#ef4444;">Error de conexión</p>';
    });
}

// ── Imagen principal: drag & drop + preview + AJAX upload ──
(function(){
  var dropZone = document.getElementById('dropZonePrincipal');
  var input = document.getElementById('inputImagenPrincipal');
  if (!dropZone || !input) return;

  dropZone.addEventListener('click', function() { input.click(); });

  window.handleImagenPrincipal = function(inp) {
    var file = inp.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { alert('La imagen no puede superar 5MB'); return; }

    // Preview inmediato
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('imgPreviewPrincipal').src = e.target.result;
      document.getElementById('previewPrincipal').style.display = 'block';
      dropZone.style.display = 'none';
    };
    reader.readAsDataURL(file);

    // Subir via AJAX
    var fd = new FormData();
    fd.append('imagen', file);
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadProgressBar').style.width = '30%';

    fetch('upload_imagen.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        document.getElementById('uploadProgressBar').style.width = '100%';
        setTimeout(function() { document.getElementById('uploadProgress').style.display = 'none'; }, 500);
        if (data.ok) {
          document.getElementById('imagenPrincipalAjax').value = data.filename;
          document.getElementById('imgPreviewPrincipal').style.opacity = '1';
        } else {
          alert('Error al subir: ' + (data.mensaje || 'Error desconocido'));
          quitarImagenPrincipal();
        }
      })
      .catch(function() {
        document.getElementById('uploadProgress').style.display = 'none';
        alert('Error de conexión al subir la imagen');
        quitarImagenPrincipal();
      });
  };

  window.handleDropPrincipal = function(e) {
    e.preventDefault();
    dropZone.style.borderColor = 'var(--border)';
    var file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      handleImagenPrincipal(input);
    }
  };

  window.quitarImagenPrincipal = function() {
    input.value = '';
    document.getElementById('imagenPrincipalAjax').value = '';
    document.getElementById('previewPrincipal').style.display = 'none';
    dropZone.style.display = 'block';
    document.getElementById('uploadProgress').style.display = 'none';
  };
})();

// ── Galería: eliminar y subir via AJAX (sin forms anidados) ──
function eliminarGaleria(imgId, productoId, btn) {
  if (!confirm('Eliminar esta imagen?')) return;
  var fd = new FormData();
  fd.append('action', 'delete_gallery_img');
  fd.append('img_id', imgId);
  fd.append('producto_id', productoId);
  fetch(location.pathname, { method: 'POST', body: fd })
    .then(function() {
      var item = btn.closest('.gallery-item');
      if (item) item.remove();
    });
}

function subirGaleria(productoId) {
  var input = document.getElementById('galeriaInput');
  if (!input || !input.files.length) { alert('Seleccioná al menos una imagen'); return; }
  var fd = new FormData();
  fd.append('action', 'upload_gallery');
  fd.append('producto_id', productoId);
  for (var i = 0; i < input.files.length; i++) {
    fd.append('galeria[]', input.files[i]);
  }
  fetch(location.pathname, { method: 'POST', body: fd })
    .then(function() { location.reload(); });
}
</script>

</body>
</html>
