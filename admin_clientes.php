<?php
// ============================================================
// ADMIN — CLIENTES
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$db = pdo();

// ── Badge helper ──
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

// ── POST: Eliminar cliente (JSON) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($contentType, 'application/json') !== false) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (($input['action'] ?? '') === 'eliminar') {
      $id = (int)($input['id'] ?? 0);
      if ($id > 0) {
        try {
          $pdo = pdo();
          $pdo->prepare("UPDATE pedidos SET cliente_id = NULL WHERE cliente_id = ?")->execute([$id]);
          $pdo->prepare("DELETE FROM wishlist WHERE cliente_id = ?")->execute([$id]);
          $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
          echo json_encode(['ok' => true]);
        } catch (Exception $e) {
          echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
      } else {
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
      }
      exit();
    }
  }
}

// ── POST: Toggle activo ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_activo') {
    $cid = (int) ($_POST['cliente_id'] ?? 0);
    if ($cid > 0) {
        $stmt = $db->prepare("UPDATE clientes SET activo = NOT activo WHERE id = ?");
        $stmt->execute([$cid]);
    }
    $back = $_POST['back'] ?? 'admin_clientes.php';
    redirect($back);
}

// ── POST: Export CSV ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel
    fputcsv($out, ['ID', 'Nombre', 'Email', 'Teléfono', 'Dirección', 'Ciudad', 'Provincia', 'Activo', 'Fecha Registro', 'Último Acceso', 'Pedidos', 'Total Gastado']);

    $rows = $db->query("
        SELECT c.*,
            (SELECT COUNT(*) FROM pedidos WHERE cliente_id = c.id) as pedidos_count,
            (SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE cliente_id = c.id AND estado IN ('pagado','preparando','enviado','entregado')) as total_gastado
        FROM clientes c
        ORDER BY c.fecha_registro DESC
    ")->fetchAll();

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['nombre'], $r['email'], $r['telefono'],
            $r['direccion'], $r['ciudad'], $r['provincia'],
            $r['activo'] ? 'Sí' : 'No',
            $r['fecha_registro'], $r['ultimo_acceso'],
            $r['pedidos_count'], $r['total_gastado']
        ]);
    }
    fclose($out);
    exit;
}

// ── Client detail view ──
$cliente_detalle = null;
$cliente_pedidos = [];
$cliente_total_gastado = 0;

if (isset($_GET['id'])) {
    $cid = (int) $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cid]);
    $cliente_detalle = $stmt->fetch();

    if ($cliente_detalle) {
        $stmt = $db->prepare("SELECT id, fecha, total, estado FROM pedidos WHERE cliente_id = ? ORDER BY fecha DESC");
        $stmt->execute([$cid]);
        $cliente_pedidos = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE cliente_id = ? AND estado IN ('pagado','preparando','enviado','entregado')");
        $stmt->execute([$cid]);
        $cliente_total_gastado = (float) $stmt->fetchColumn();
    }
}

// ── List view: filters & pagination ──
$per_page = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

// Filter: activo
if (isset($_GET['activo']) && $_GET['activo'] !== '') {
    $where[] = "c.activo = ?";
    $params[] = (int) $_GET['activo'];
}

// Filter: search
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[] = "(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$stmt = $db->prepare("SELECT COUNT(*) FROM clientes c $where_sql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));

// Fetch clients with order stats
$stmt = $db->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM pedidos WHERE cliente_id = c.id) as pedidos_count,
        (SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE cliente_id = c.id AND estado IN ('pagado','preparando','enviado','entregado')) as total_gastado
    FROM clientes c
    $where_sql
    ORDER BY c.fecha_registro DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Build query string for pagination links
$qs_parts = [];
if ($q !== '') $qs_parts[] = 'q=' . urlencode($q);
if (isset($_GET['activo']) && $_GET['activo'] !== '') $qs_parts[] = 'activo=' . (int) $_GET['activo'];
$qs_base = $qs_parts ? '&' . implode('&', $qs_parts) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clientes — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>
<?php $admin_page = 'clientes'; ?>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

<?php if ($cliente_detalle): ?>
<!-- ============ CLIENT DETAIL ============ -->

<a href="admin_clientes.php" class="back-link">&larr; Volver a clientes</a>

<div class="page-header">
  <h1><?= sanitize($cliente_detalle['nombre']) ?></h1>
  <p class="page-subtitle">Detalle del cliente #<?= $cliente_detalle['id'] ?></p>
</div>

<div class="client-info-card">
  <div class="client-info-grid">
    <div class="client-info-item">
      <label>Nombre</label>
      <span><?= sanitize($cliente_detalle['nombre'] ?? '-') ?></span>
    </div>
    <div class="client-info-item">
      <label>Email</label>
      <span><?= sanitize($cliente_detalle['email'] ?? '-') ?></span>
    </div>
    <div class="client-info-item">
      <label>Teléfono</label>
      <span><?= sanitize($cliente_detalle['telefono'] ?: '-') ?></span>
    </div>
    <div class="client-info-item">
      <label>Dirección</label>
      <span><?= sanitize($cliente_detalle['direccion'] ?: '-') ?></span>
    </div>
    <div class="client-info-item">
      <label>Ciudad</label>
      <span><?= sanitize($cliente_detalle['ciudad'] ?: '-') ?></span>
    </div>
    <div class="client-info-item">
      <label>Provincia</label>
      <span><?= sanitize($cliente_detalle['provincia'] ?: '-') ?></span>
    </div>
    <div class="client-info-item">
      <label>Fecha de registro</label>
      <span><?= $cliente_detalle['fecha_registro'] ? date('d/m/Y H:i', strtotime($cliente_detalle['fecha_registro'])) : '-' ?></span>
    </div>
    <div class="client-info-item">
      <label>Último acceso</label>
      <span><?= $cliente_detalle['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($cliente_detalle['ultimo_acceso'])) : 'Nunca' ?></span>
    </div>
    <div class="client-info-item">
      <label>Estado</label>
      <span>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="toggle_activo">
          <input type="hidden" name="cliente_id" value="<?= $cliente_detalle['id'] ?>">
          <input type="hidden" name="back" value="admin_clientes.php?id=<?= $cliente_detalle['id'] ?>">
          <label class="toggle-switch">
            <input type="checkbox" <?= $cliente_detalle['activo'] ? 'checked' : '' ?> onchange="this.form.submit()">
            <span class="toggle-slider"></span>
          </label>
        </form>
        <span style="margin-left:8px;font-size:.82rem;color:<?= $cliente_detalle['activo'] ? '#10b981' : '#ef4444' ?>;">
          <?= $cliente_detalle['activo'] ? 'Activo' : 'Bloqueado' ?>
        </span>
      </span>
    </div>
  </div>

  <div class="client-summary">
    <div class="summary-box">
      <div class="summary-value"><?= count($cliente_pedidos) ?></div>
      <div class="summary-label">Pedidos totales</div>
    </div>
    <div class="summary-box">
      <div class="summary-value"><?= price($cliente_total_gastado) ?></div>
      <div class="summary-label">Total gastado</div>
    </div>
  </div>
</div>

<!-- Orders history -->
<div class="table-card">
  <h3>Historial de pedidos</h3>
  <div class="table-wrap table-container">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Fecha</th>
          <th>Total</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cliente_pedidos)): ?>
          <tr><td colspan="4" class="empty-cell">Sin pedidos</td></tr>
        <?php else: ?>
          <?php foreach ($cliente_pedidos as $p): ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
            <td><?= price((float) $p['total']) ?></td>
            <td><?= estado_badge($p['estado']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ============ CLIENT LIST ============ -->

<div class="page-header">
  <h1>Clientes</h1>
  <p class="page-subtitle"><?= $total ?> cliente<?= $total !== 1 ? 's' : '' ?> registrados</p>
</div>

<!-- Toolbar -->
<div class="toolbar">
  <form method="GET" style="display:contents;">
    <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="Buscar nombre, email, teléfono...">
    <select name="activo" onchange="this.form.submit()">
      <option value="">Todos</option>
      <option value="1" <?= (isset($_GET['activo']) && $_GET['activo'] === '1') ? 'selected' : '' ?>>Activos</option>
      <option value="0" <?= (isset($_GET['activo']) && $_GET['activo'] === '0') ? 'selected' : '' ?>>Bloqueados</option>
    </select>
    <button type="submit" class="btn-ver">Filtrar</button>
  </form>
  <form method="POST">
    <input type="hidden" name="action" value="export_csv">
    <button type="submit" class="btn-export">&#11015; Exportar CSV</button>
  </form>
</div>

<!-- Table -->
<div class="table-card">
  <div class="table-wrap table-container">
    <table>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Email</th>
          <th>Teléfono</th>
          <th>Pedidos</th>
          <th>Total gastado</th>
          <th>Fecha registro</th>
          <th>Activo</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($clientes)): ?>
          <tr><td colspan="8" class="empty-cell">No se encontraron clientes</td></tr>
        <?php else: ?>
          <?php foreach ($clientes as $c): ?>
          <tr id="fila-cliente-<?= $c['id'] ?>">
            <td><?= sanitize($c['nombre'] ?? '-') ?></td>
            <td><?= sanitize($c['email'] ?? '-') ?></td>
            <td><?= sanitize($c['telefono'] ?: '-') ?></td>
            <td><?= (int) $c['pedidos_count'] ?></td>
            <td><?= price((float) $c['total_gastado']) ?></td>
            <td><?= $c['fecha_registro'] ? date('d/m/Y', strtotime($c['fecha_registro'])) : '-' ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle_activo">
                <input type="hidden" name="cliente_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="back" value="admin_clientes.php?page=<?= $page ?><?= $qs_base ?>">
                <label class="toggle-switch">
                  <input type="checkbox" <?= $c['activo'] ? 'checked' : '' ?> onchange="this.form.submit()">
                  <span class="toggle-slider"></span>
                </label>
              </form>
            </td>
            <td>
              <a href="admin_clientes.php?id=<?= $c['id'] ?>" class="btn-ver">Ver</a>
              <button onclick="eliminarCliente(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>')"
                style="padding:6px 14px;border:1px solid #dc2626;color:#dc2626;background:transparent;border-radius:8px;font-size:0.78rem;font-weight:700;text-transform:uppercase;cursor:pointer;margin-left:6px;">
                Eliminar
              </button>
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
  <a href="admin_clientes.php?page=<?= $page - 1 ?><?= $qs_base ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">&laquo;</a>
  <?php
  $range = 2;
  $start = max(1, $page - $range);
  $end = min($total_pages, $page + $range);
  if ($start > 1): ?>
    <a href="admin_clientes.php?page=1<?= $qs_base ?>">1</a>
    <?php if ($start > 2): ?><span class="disabled">&hellip;</span><?php endif; ?>
  <?php endif; ?>
  <?php for ($i = $start; $i <= $end; $i++): ?>
    <?php if ($i === $page): ?>
      <span class="active"><?= $i ?></span>
    <?php else: ?>
      <a href="admin_clientes.php?page=<?= $i ?><?= $qs_base ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($end < $total_pages): ?>
    <?php if ($end < $total_pages - 1): ?><span class="disabled">&hellip;</span><?php endif; ?>
    <a href="admin_clientes.php?page=<?= $total_pages ?><?= $qs_base ?>"><?= $total_pages ?></a>
  <?php endif; ?>
  <a href="admin_clientes.php?page=<?= $page + 1 ?><?= $qs_base ?>" class="<?= $page >= $total_pages ? 'disabled' : '' ?>">&raquo;</a>
</div>
<?php endif; ?>

<?php endif; ?>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> DyP Consultora &mdash; Panel de gestión</p>
  </footer>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<script src="js/admin.js"></script>

<script>
function eliminarCliente(id, nombre) {
  if (!confirm('¿Eliminar al cliente "' + nombre + '"?\nEsta acción no se puede deshacer.')) return;

  fetch('admin_clientes.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'eliminar', id: id})
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      document.getElementById('fila-cliente-' + id)?.remove();
      mostrarAlerta('Cliente eliminado correctamente', 'ok');
    } else {
      mostrarAlerta('Error: ' + d.error, 'err');
    }
  });
}

function mostrarAlerta(texto, tipo) {
  const div = document.createElement('div');
  div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:3000;' +
    'padding:14px 20px;border-radius:8px;font-size:0.88rem;font-weight:600;' +
    'background:' + (tipo==='ok' ? '#f0fdf4' : '#fef2f2') + ';' +
    'border:1px solid ' + (tipo==='ok' ? '#86efac' : '#fca5a5') + ';' +
    'color:' + (tipo==='ok' ? '#166534' : '#dc2626') + ';' +
    'box-shadow:0 4px 12px rgba(0,0,0,0.1);';
  div.textContent = texto;
  document.body.appendChild(div);
  setTimeout(() => div.remove(), 4000);
}
</script>

</body>
</html>
