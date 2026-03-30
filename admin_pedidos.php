<?php
// ============================================================
// ADMIN — PEDIDOS
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/email_helper.php';
require_admin();

$db = pdo();
$flash_ok  = '';
$flash_err = '';

// ── Helper: estado badge ──
if (!function_exists('estado_badge')) {
    function estado_badge(string $estado): string {
        $colors = [
            'pendiente'   => '#f59e0b',
            'pagado'      => '#06b6d4',
            'preparando'  => '#8b5cf6',
            'enviado'     => '#3b82f6',
            'entregado'   => '#10b981',
            'cancelado'   => '#ef4444',
            'reembolsado' => '#71717a',
        ];
        $c = $colors[$estado] ?? '#71717a';
        $rgb = implode(',', array_map(fn($h) => hexdec($h), str_split(ltrim($c, '#'), 2)));
        return "<span style=\"display:inline-block;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600;background:rgba({$rgb},0.15);color:$c;border:1px solid {$c}33;\">$estado</span>";
    }
}

// ── POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update estado ──
    if ($action === 'update_estado') {
        $pedido_id = (int)($_POST['pedido_id'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        $estados_validos = ['pendiente','pagado','preparando','enviado','entregado','cancelado','reembolsado'];

        if ($pedido_id > 0 && in_array($nuevo_estado, $estados_validos)) {
            $stmt = $db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $pedido_id]);

            // Fetch pedido for email
            $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = ?");
            $stmt->execute([$pedido_id]);
            $pedido = $stmt->fetch();

            if ($pedido) {
                email_pedido_estado($pedido);
                $flash_ok = "Pedido #{$pedido_id} actualizado a <strong>{$nuevo_estado}</strong>. Email enviado al cliente.";
            }
        } else {
            $flash_err = 'Datos inválidos para actualizar estado.';
        }
    }

    // ── Add nota ──
    if ($action === 'add_nota') {
        $pedido_id = (int)($_POST['pedido_id'] ?? 0);
        $nota = trim($_POST['nota'] ?? '');

        if ($pedido_id > 0 && $nota !== '') {
            $timestamp = date('d/m/Y H:i');
            $nueva_nota = "[{$timestamp}] {$nota}";

            $stmt = $db->prepare("SELECT notas FROM pedidos WHERE id = ?");
            $stmt->execute([$pedido_id]);
            $row = $stmt->fetch();

            if ($row) {
                $notas_actuales = $row['notas'] ? $row['notas'] . "\n" . $nueva_nota : $nueva_nota;
                $stmt = $db->prepare("UPDATE pedidos SET notas = ? WHERE id = ?");
                $stmt->execute([$notas_actuales, $pedido_id]);
                $flash_ok = "Nota agregada al pedido #{$pedido_id}.";
            }
        } else {
            $flash_err = 'Debe ingresar una nota válida.';
        }
    }

    // ── Export CSV ──
    if ($action === 'export_csv') {
        $where = ["1=1"];
        $params = [];

        if (!empty($_POST['f_estado'])) {
            $where[] = "p.estado = ?";
            $params[] = $_POST['f_estado'];
        }
        if (!empty($_POST['f_desde'])) {
            $where[] = "DATE(p.fecha) >= ?";
            $params[] = $_POST['f_desde'];
        }
        if (!empty($_POST['f_hasta'])) {
            $where[] = "DATE(p.fecha) <= ?";
            $params[] = $_POST['f_hasta'];
        }
        if (!empty($_POST['f_email'])) {
            $where[] = "p.email LIKE ?";
            $params[] = '%' . $_POST['f_email'] . '%';
        }

        $sql = "SELECT p.id, p.fecha, p.nombre, p.email, p.telefono, p.direccion, p.ciudad, p.provincia,
                       p.subtotal, p.descuento, p.total, p.estado, p.cupon_codigo, p.mp_payment_id,
                       (SELECT COUNT(*) FROM pedido_items pi WHERE pi.pedido_id = p.id) as items_count
                FROM pedidos p
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.fecha DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedidos_' . date('Y-m-d_His') . '.csv"');
        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID','Fecha','Nombre','Email','Teléfono','Dirección','Ciudad','Provincia','Subtotal','Descuento','Total','Estado','Cupón','MP Payment ID','Items']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['fecha'], $r['nombre'], $r['email'], $r['telefono'],
                $r['direccion'], $r['ciudad'], $r['provincia'],
                $r['subtotal'], $r['descuento'], $r['total'], $r['estado'],
                $r['cupon_codigo'], $r['mp_payment_id'], $r['items_count']
            ]);
        }
        fclose($out);
        exit;
    }
}

// ── GET FILTERS ──
$f_estado = $_GET['estado'] ?? '';
$f_desde  = $_GET['desde'] ?? '';
$f_hasta  = $_GET['hasta'] ?? '';
$f_email  = $_GET['email'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = ["1=1"];
$params = [];

if ($f_estado !== '') {
    $where[] = "p.estado = ?";
    $params[] = $f_estado;
}
if ($f_desde !== '') {
    $where[] = "DATE(p.fecha) >= ?";
    $params[] = $f_desde;
}
if ($f_hasta !== '') {
    $where[] = "DATE(p.fecha) <= ?";
    $params[] = $f_hasta;
}
if ($f_email !== '') {
    $where[] = "p.email LIKE ?";
    $params[] = '%' . $f_email . '%';
}

$where_sql = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("SELECT COUNT(*) FROM pedidos p WHERE {$where_sql}");
$stmt->execute($params);
$total_pedidos = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_pedidos / $per_page));

// Fetch pedidos
$sql = "SELECT p.*,
               (SELECT COUNT(*) FROM pedido_items pi WHERE pi.pedido_id = p.id) as items_count
        FROM pedidos p
        WHERE {$where_sql}
        ORDER BY p.fecha DESC
        LIMIT {$per_page} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Build query string for pagination
$qs_parts = [];
if ($f_estado !== '') $qs_parts[] = 'estado=' . urlencode($f_estado);
if ($f_desde !== '')  $qs_parts[] = 'desde=' . urlencode($f_desde);
if ($f_hasta !== '')  $qs_parts[] = 'hasta=' . urlencode($f_hasta);
if ($f_email !== '')  $qs_parts[] = 'email=' . urlencode($f_email);
$qs_base = $qs_parts ? implode('&', $qs_parts) . '&' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>
<?php $admin_page = 'pedidos'; ?>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

  <!-- Page header -->
  <div class="header-row">
    <div>
      <h1 style="margin:0;">Pedidos <span class="badge-count"><?= $total_pedidos ?></span></h1>
    </div>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="action" value="export_csv">
      <input type="hidden" name="f_estado" value="<?= sanitize($f_estado) ?>">
      <input type="hidden" name="f_desde" value="<?= sanitize($f_desde) ?>">
      <input type="hidden" name="f_hasta" value="<?= sanitize($f_hasta) ?>">
      <input type="hidden" name="f_email" value="<?= sanitize($f_email) ?>">
      <button type="submit" class="btn-export">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Exportar CSV
      </button>
    </form>
  </div>

  <!-- Flash messages -->
  <?php if ($flash_ok): ?>
    <div class="flash-ok"><?= $flash_ok ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="flash-err"><?= $flash_err ?></div>
  <?php endif; ?>

  <!-- Toolbar -->
  <form method="GET" style="margin-bottom: 24px;">
    <div class="filtros-pedidos">

      <div class="filtro-field">
        <label class="filtro-label">Estado</label>
        <select name="estado" class="filtro-input">
          <option value="">Todos</option>
          <option value="pendiente"   <?= ($_GET['estado']??'')==='pendiente'?'selected':''?>>Pendiente</option>
          <option value="pagado"      <?= ($_GET['estado']??'')==='pagado'?'selected':''?>>Pagado</option>
          <option value="preparando"  <?= ($_GET['estado']??'')==='preparando'?'selected':''?>>Preparando</option>
          <option value="enviado"     <?= ($_GET['estado']??'')==='enviado'?'selected':''?>>Enviado</option>
          <option value="entregado"   <?= ($_GET['estado']??'')==='entregado'?'selected':''?>>Entregado</option>
          <option value="cancelado"   <?= ($_GET['estado']??'')==='cancelado'?'selected':''?>>Cancelado</option>
        </select>
      </div>

      <div class="filtro-field">
        <label class="filtro-label">Desde</label>
        <input type="date" name="desde" class="filtro-input"
               value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>">
      </div>

      <div class="filtro-field">
        <label class="filtro-label">Hasta</label>
        <input type="date" name="hasta" class="filtro-input"
               value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>">
      </div>

      <div class="filtro-field filtro-field--email">
        <label class="filtro-label">Email</label>
        <input type="text" name="email" class="filtro-input"
               placeholder="Buscar por email..."
               value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
      </div>

      <div class="filtro-field filtro-field--btn">
        <label class="filtro-label">&nbsp;</label>
        <button type="submit" class="filtro-btn">Filtrar</button>
      </div>

    </div>
  </form>

  <style>
  .filtros-pedidos {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: nowrap;
  }

  .filtro-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
  }

  .filtro-field--email { flex: 1; }

  .filtro-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #9ca3af;
    white-space: nowrap;
  }

  .filtro-input {
    height: 42px;
    padding: 0 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.88rem;
    color: #111;
    background: #fff;
    box-sizing: border-box;
    width: 100%;
    outline: none;
    transition: border-color 0.2s;
    -webkit-appearance: none;
  }

  .filtro-input:focus {
    border-color: #7c3aed;
  }

  select.filtro-input {
    min-width: 130px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
    cursor: pointer;
  }

  input[type=date].filtro-input {
    min-width: 140px;
    cursor: pointer;
  }

  .filtro-btn {
    height: 42px;
    padding: 0 24px;
    background: #7c3aed;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.2s;
  }
  .filtro-btn:hover { background: #6d28d9; }

  /* Tablet */
  @media (max-width: 1024px) {
    .filtros-pedidos {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 10px;
    }
    .filtro-field--email {
      grid-column: 1 / 3;
    }
    .filtro-field--btn {
      grid-column: 3 / 4;
    }
    .filtro-btn { width: 100%; }
  }

  /* Mobile */
  @media (max-width: 640px) {
    .filtros-pedidos {
      grid-template-columns: 1fr 1fr;
    }
    .filtro-field--email,
    .filtro-field--btn {
      grid-column: 1 / -1;
    }
  }
  </style>

  <!-- Table -->
  <div class="table-card">
    <div class="table-wrap table-container">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Email</th>
            <th>Items</th>
            <th>Total</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pedidos)): ?>
            <tr><td colspan="8" class="empty-cell">No se encontraron pedidos</td></tr>
          <?php else: ?>
            <?php foreach ($pedidos as $p): ?>
            <tr>
              <td><?= $p['id'] ?></td>
              <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
              <td><?= sanitize($p['nombre']) ?></td>
              <td style="color:#71717a;font-size:.85rem;"><?= sanitize($p['email']) ?></td>
              <td style="text-align:center;"><?= (int)$p['items_count'] ?></td>
              <td><?= price((float)$p['total']) ?></td>
              <td><?= estado_badge($p['estado']) ?></td>
              <td>
                <button class="btn-ver" onclick="openPedido(<?= $p['id'] ?>)">Ver</button>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= $qs_base ?>page=1">&laquo;</a>
      <a href="?<?= $qs_base ?>page=<?= $page - 1 ?>">&lsaquo;</a>
    <?php else: ?>
      <span class="disabled">&laquo;</span>
      <span class="disabled">&lsaquo;</span>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 3);
    $end   = min($total_pages, $page + 3);
    for ($i = $start; $i <= $end; $i++):
    ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= $qs_base ?>page=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <a href="?<?= $qs_base ?>page=<?= $page + 1 ?>">&rsaquo;</a>
      <a href="?<?= $qs_base ?>page=<?= $total_pages ?>">&raquo;</a>
    <?php else: ?>
      <span class="disabled">&rsaquo;</span>
      <span class="disabled">&raquo;</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> DyP Consultora &mdash; Panel de gestión</p>
  </footer>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<!-- ============ PEDIDO DETAIL MODAL ============ -->
<div class="modal-overlay" id="pedidoModal">
  <div class="modal">
    <div class="modal-header">
      <h2 id="modalTitle">Pedido #</h2>
      <button class="modal-close" onclick="closePedidoModal()">&times;</button>
    </div>
    <div class="modal-body" id="modalBody">
      <p style="color:#71717a;">Cargando...</p>
    </div>
  </div>
</div>

<!-- Pedido data embedded for JS modal -->
<?php
// Pre-load all displayed pedidos with their items for modal use
$pedidos_json = [];
foreach ($pedidos as $p) {
    $stmt = $db->prepare("
        SELECT pi.*, pr.imagen_principal
        FROM pedido_items pi
        LEFT JOIN productos pr ON pr.id = pi.producto_id
        WHERE pi.pedido_id = ?
    ");
    $stmt->execute([$p['id']]);
    $items = $stmt->fetchAll();
    $p['items'] = $items;
    $pedidos_json[$p['id']] = $p;
}
?>
<script>
const pedidosData = <?= json_encode($pedidos_json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function sanitizeHTML(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function formatPrice(n) {
    n = parseFloat(n) || 0;
    return '$' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function openPedido(id) {
    const p = pedidosData[id];
    if (!p) return;

    document.getElementById('modalTitle').textContent = 'Pedido #' + p.id;

    let itemsRows = '';
    (p.items || []).forEach(function(it) {
        const img = it.imagen_principal
            ? '<img class="thumb" src="uploads/productos/' + sanitizeHTML(it.imagen_principal) + '" alt="">'
            : '<div class="thumb" style="display:flex;align-items:center;justify-content:center;color:#52525b;font-size:.6rem;">Sin img</div>';
        const lineTotal = parseFloat(it.precio_unitario) * parseInt(it.cantidad);
        itemsRows += '<tr>' +
            '<td>' + img + '</td>' +
            '<td>' + sanitizeHTML(it.nombre_producto) + (it.variante ? '<br><small style="color:#71717a;">' + sanitizeHTML(it.variante) + '</small>' : '') + '</td>' +
            '<td style="text-align:center;">' + it.cantidad + '</td>' +
            '<td style="text-align:right;">' + formatPrice(it.precio_unitario) + '</td>' +
            '<td style="text-align:right;">' + formatPrice(lineTotal) + '</td>' +
            '</tr>';
    });

    const descuento = parseFloat(p.descuento) || 0;
    const discountHTML = descuento > 0
        ? '<p class="discount">Descuento' + (p.cupon_codigo ? ' (' + sanitizeHTML(p.cupon_codigo) + ')' : '') + ': -' + formatPrice(descuento) + '</p>'
        : '';

    const notas = p.notas || '';
    const notasDisplay = notas
        ? '<div class="notas-display">' + sanitizeHTML(notas) + '</div>'
        : '<p style="color:#52525b;font-size:.83rem;margin-bottom:10px;">Sin notas.</p>';

    const mpInfo = p.mp_payment_id
        ? '<div class="info-item"><label>MP Payment ID</label><span>' + sanitizeHTML(p.mp_payment_id) + '</span></div>'
        : '';

    const estados = ['pendiente','pagado','preparando','enviado','entregado','cancelado','reembolsado'];
    let estadoOptions = '';
    estados.forEach(function(e) {
        estadoOptions += '<option value="' + e + '"' + (e === p.estado ? ' selected' : '') + '>' + e.charAt(0).toUpperCase() + e.slice(1) + '</option>';
    });

    const fechaCreacion = p.fecha ? new Date(p.fecha).toLocaleString('es-AR') : '-';
    const fechaActualizacion = p.updated_at ? new Date(p.updated_at).toLocaleString('es-AR') : '-';

    const html = '' +
        '<div class="modal-section">' +
            '<h3>Informacion del cliente</h3>' +
            '<div class="info-grid">' +
                '<div class="info-item"><label>Nombre</label><span>' + sanitizeHTML(p.nombre) + '</span></div>' +
                '<div class="info-item"><label>Email</label><span>' + sanitizeHTML(p.email) + '</span></div>' +
                '<div class="info-item"><label>Telefono</label><span>' + sanitizeHTML(p.telefono || '-') + '</span></div>' +
                '<div class="info-item"><label>Direccion</label><span>' + sanitizeHTML(p.direccion || '-') + '</span></div>' +
                '<div class="info-item"><label>Ciudad</label><span>' + sanitizeHTML(p.ciudad || '-') + '</span></div>' +
                '<div class="info-item"><label>Provincia</label><span>' + sanitizeHTML(p.provincia || '-') + '</span></div>' +
                mpInfo +
            '</div>' +
        '</div>' +
        '<div class="modal-section">' +
            '<h3>Items del pedido</h3>' +
            '<table class="items-table">' +
                '<thead><tr><th style="width:50px;"></th><th>Producto</th><th style="text-align:center;">Cant.</th><th style="text-align:right;">Precio</th><th style="text-align:right;">Subtotal</th></tr></thead>' +
                '<tbody>' + itemsRows + '</tbody>' +
            '</table>' +
            '<div class="totals">' +
                '<p>Subtotal: ' + formatPrice(p.subtotal) + '</p>' +
                discountHTML +
                '<p class="total-final">Total: ' + formatPrice(p.total) + '</p>' +
            '</div>' +
        '</div>' +
        '<div class="modal-section">' +
            '<h3>Estado del pedido</h3>' +
            '<form method="POST" class="estado-form">' +
                '<input type="hidden" name="action" value="update_estado">' +
                '<input type="hidden" name="pedido_id" value="' + p.id + '">' +
                '<select name="nuevo_estado">' + estadoOptions + '</select>' +
                '<button type="submit" class="btn-save-estado">Guardar estado</button>' +
            '</form>' +
        '</div>' +
        '<div class="modal-section">' +
            '<h3>Notas internas</h3>' +
            notasDisplay +
            '<form method="POST">' +
                '<input type="hidden" name="action" value="add_nota">' +
                '<input type="hidden" name="pedido_id" value="' + p.id + '">' +
                '<textarea name="nota" class="notas-area" placeholder="Agregar nota..."></textarea>' +
                '<button type="submit" class="btn-add-nota">Agregar nota</button>' +
            '</form>' +
        '</div>' +
        '<div class="modal-section meta-info">' +
            '<p>Creado: <span>' + fechaCreacion + '</span></p>' +
            '<p>Actualizado: <span>' + fechaActualizacion + '</span></p>' +
        '</div>';

    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('pedidoModal').classList.add('active');
}

function closePedidoModal() {
    document.getElementById('pedidoModal').classList.remove('active');
}

// Close modal on overlay click
document.getElementById('pedidoModal').addEventListener('click', function(e) {
    if (e.target === this) closePedidoModal();
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePedidoModal();
});
</script>
<script src="js/admin.js"></script>

</body>
</html>
