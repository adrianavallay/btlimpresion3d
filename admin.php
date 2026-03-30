<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// ── Logout ──
if (isset($_GET['logout'])) {
    admin_logout();
    redirect('admin.php');
}

// ── Login POST ──
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !is_admin()) {
    $user = $_POST['usuario'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (!admin_login($user, $pass)) {
        $login_error = 'Usuario o contraseña incorrectos';
    }
}

// ── Auto-backup check (every 24h) ──
if (is_admin()) {
    $bkp_last_file = __DIR__ . '/bkp/.last_backup';
    $bkp_last = file_exists($bkp_last_file) ? (int) file_get_contents($bkp_last_file) : 0;
    if (time() - $bkp_last > 86400) {
        if (!is_dir(__DIR__ . '/bkp')) {
            mkdir(__DIR__ . '/bkp', 0755, true);
        }
        file_put_contents($bkp_last_file, time());
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/backup_runner.php') . ' > /dev/null 2>&1 &';
        @exec($cmd);
    }
}

// ── Dashboard data ──
$stats = [];
$ventas_30 = [];
$ventas_cat = [];
$pedidos_estado = [];
$ultimos_pedidos = [];
$top_productos = [];
$stock_bajo = [];

if (is_admin()) {
    $db = pdo();
    $estados_validos = "'pagado','preparando','enviado','entregado'";

    // Revenue today
    $stmt = $db->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE DATE(fecha) = CURDATE() AND estado IN ($estados_validos)");
    $stats['ventas_hoy'] = (float) $stmt->fetchColumn();

    // Revenue this week
    $stmt = $db->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND estado IN ($estados_validos)");
    $stats['ventas_semana'] = (float) $stmt->fetchColumn();

    // Revenue this month
    $stmt = $db->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado IN ($estados_validos)");
    $stats['ventas_mes'] = (float) $stmt->fetchColumn();

    // Order count by status
    $stmt = $db->query("SELECT estado, COUNT(*) as cnt FROM pedidos GROUP BY estado");
    $order_counts = $stmt->fetchAll();
    $pedidos_por_estado = [];
    foreach ($order_counts as $row) {
        $pedidos_por_estado[$row['estado']] = (int) $row['cnt'];
    }
    $stats['pedidos_pendientes'] = ($pedidos_por_estado['pendiente'] ?? 0) + ($pedidos_por_estado['pagado'] ?? 0);

    // New clients this week
    $stmt = $db->query("SELECT COUNT(*) FROM clientes WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stats['clientes_nuevos'] = (int) $stmt->fetchColumn();

    // Products with stock < stock_minimo
    $stmt = $db->query("SELECT id, nombre, stock, stock_minimo FROM productos WHERE stock < stock_minimo AND estado = 'activo' ORDER BY stock ASC LIMIT 10");
    $stock_bajo = $stmt->fetchAll();
    $stats['stock_critico'] = count($stock_bajo);

    // Sales last 30 days (for line chart)
    $stmt = $db->query("
        SELECT DATE(fecha) as dia, COALESCE(SUM(total),0) as total
        FROM pedidos
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND estado IN ($estados_validos)
        GROUP BY DATE(fecha)
        ORDER BY dia ASC
    ");
    $ventas_30_raw = $stmt->fetchAll();
    // Fill gaps for 30 days
    $ventas_30_map = [];
    foreach ($ventas_30_raw as $r) {
        $ventas_30_map[$r['dia']] = (float) $r['total'];
    }
    for ($i = 29; $i >= 0; $i--) {
        $dia = date('Y-m-d', strtotime("-{$i} days"));
        $ventas_30[] = [
            'dia' => $dia,
            'label' => date('d/m', strtotime($dia)),
            'total' => $ventas_30_map[$dia] ?? 0
        ];
    }

    // Sales by category this month (for bar chart)
    $stmt = $db->query("
        SELECT c.nombre as categoria, COALESCE(SUM(pi.cantidad * pi.precio_unitario),0) as total
        FROM pedido_items pi
        JOIN pedidos p ON p.id = pi.pedido_id
        JOIN productos pr ON pr.id = pi.producto_id
        JOIN categorias c ON c.id = pr.categoria_id
        WHERE MONTH(p.fecha) = MONTH(CURDATE()) AND YEAR(p.fecha) = YEAR(CURDATE())
          AND p.estado IN ($estados_validos)
        GROUP BY c.nombre
        ORDER BY total DESC
    ");
    $ventas_cat = $stmt->fetchAll();

    // Orders by status (for donut chart)
    $pedidos_estado = $pedidos_por_estado;

    // Last 5 orders
    $stmt = $db->query("
        SELECT p.id, p.fecha, p.nombre, p.total, p.estado
        FROM pedidos p
        ORDER BY p.fecha DESC
        LIMIT 5
    ");
    $ultimos_pedidos = $stmt->fetchAll();

    // Top 5 best selling products
    $stmt = $db->query("
        SELECT pr.nombre, SUM(pi.cantidad) as ventas, SUM(pi.cantidad * pi.precio_unitario) as ingresos
        FROM pedido_items pi
        JOIN productos pr ON pr.id = pi.producto_id
        JOIN pedidos p ON p.id = pi.pedido_id
        WHERE p.estado IN ($estados_validos)
        GROUP BY pr.id, pr.nombre
        ORDER BY ventas DESC
        LIMIT 5
    ");
    $top_productos = $stmt->fetchAll();
}

// ── Badge color helper ──
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
    return "<span style=\"display:inline-block;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600;background:rgba(" . implode(',', array_map(fn($h) => hexdec($h), str_split(ltrim($c, '#'), 2))) . ",0.15);color:$c;border:1px solid {$c}33;\">$estado</span>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vivid Store Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>

<?php if (!is_admin()): ?>
<!-- ============ LOGIN ============ -->
<div class="login-wrapper">
  <div class="login-box">
    <div class="login-logo">Vivid Store</div>
    <h1>Panel de Administración</h1>
    <?php if ($login_error): ?>
      <p class="login-error"><?= sanitize($login_error) ?></p>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
      <div class="field">
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" placeholder="admin" required autofocus>
      </div>
      <div class="field">
        <label for="password">Contraseña</label>
        <div style="position:relative;">
          <input type="password" id="password" name="password" placeholder="••••••••" required style="padding-right:44px;">
          <button type="button" id="togglePass" style="position:absolute;right:0;top:0;height:100%;width:44px;background:none;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#999;" onclick="togglePassword()">
            <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eyeOffIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
              <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-primary">Ingresar</button>
    </form>
    <script>
    function togglePassword(){
      var p=document.getElementById('password'),on=document.getElementById('eyeIcon'),off=document.getElementById('eyeOffIcon');
      if(p.type==='password'){p.type='text';on.style.display='none';off.style.display='block';}
      else{p.type='password';on.style.display='block';off.style.display='none';}
    }
    </script>
  </div>
</div>

<?php else: ?>
<?php $admin_page = 'dashboard'; ?>
<!-- ============ DASHBOARD ============ -->

<?php include __DIR__ . '/includes/admin_header.php'; ?>

  <!-- Page header -->
  <div class="page-header">
    <h1>Dashboard</h1>
    <p class="page-subtitle"><?= date('d/m/Y H:i') ?> — Resumen general de la tienda</p>
  </div>

  <!-- Stat cards -->
  <div class="stat-cards">
    <div class="stat-card">
      <div class="stat-icon"><span class="material-symbols-outlined">payments</span></div>
      <div class="stat-info">
        <div class="stat-number"><?= price($stats['ventas_hoy']) ?></div>
        <div class="stat-label">Ventas hoy</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><span class="material-symbols-outlined">shopping_bag</span></div>
      <div class="stat-info">
        <div class="stat-number"><?= $stats['pedidos_pendientes'] ?></div>
        <div class="stat-label">Pedidos pendientes</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><span class="material-symbols-outlined">group_add</span></div>
      <div class="stat-info">
        <div class="stat-number"><?= $stats['clientes_nuevos'] ?></div>
        <div class="stat-label">Clientes nuevos (7d)</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><span class="material-symbols-outlined">warning</span></div>
      <div class="stat-info">
        <div class="stat-number"><?= $stats['stock_critico'] ?></div>
        <div class="stat-label">Stock crítico</div>
      </div>
    </div>
  </div>

  <!-- Revenue summary row -->
  <div class="revenue-row">
    <div class="revenue-card">
      <span class="revenue-label">Semana</span>
      <span class="revenue-value"><?= price($stats['ventas_semana']) ?></span>
    </div>
    <div class="revenue-card">
      <span class="revenue-label">Mes</span>
      <span class="revenue-value"><?= price($stats['ventas_mes']) ?></span>
    </div>
  </div>

  <!-- Charts -->
  <section class="charts-section">
    <div class="chart-card chart-wide">
      <h3>Ventas últimos 30 días</h3>
      <canvas id="chartVentas"></canvas>
    </div>
    <div class="chart-card">
      <h3>Ventas por categoría</h3>
      <canvas id="chartCategorias"></canvas>
    </div>
    <div class="chart-card">
      <h3>Pedidos por estado</h3>
      <canvas id="chartEstados"></canvas>
    </div>
  </section>

  <!-- Tables -->
  <section class="tables-section">

    <!-- Últimos pedidos -->
    <div class="table-card table-wide">
      <h3>Últimos 5 pedidos</h3>
      <div class="table-wrap table-container">
        <table>
          <thead>
            <tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Estado</th></tr>
          </thead>
          <tbody>
            <?php if (empty($ultimos_pedidos)): ?>
              <tr><td colspan="5" class="empty-cell">Sin pedidos aún</td></tr>
            <?php else: ?>
              <?php foreach ($ultimos_pedidos as $p): ?>
              <tr>
                <td><?= $p['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                <td><?= sanitize($p['nombre']) ?></td>
                <td><?= price((float)$p['total']) ?></td>
                <td><?= estado_badge($p['estado']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top productos -->
    <div class="table-card">
      <h3>Top 5 productos más vendidos</h3>
      <div class="table-wrap table-container">
        <table>
          <thead>
            <tr><th>Producto</th><th>Ventas</th><th>Ingresos</th></tr>
          </thead>
          <tbody>
            <?php if (empty($top_productos)): ?>
              <tr><td colspan="3" class="empty-cell">Sin datos</td></tr>
            <?php else: ?>
              <?php foreach ($top_productos as $tp): ?>
              <tr>
                <td><?= sanitize($tp['nombre']) ?></td>
                <td><?= (int)$tp['ventas'] ?></td>
                <td><?= price((float)$tp['ingresos']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Stock bajo -->
    <div class="table-card">
      <h3>Productos stock bajo</h3>
      <div class="table-wrap table-container">
        <table>
          <thead>
            <tr><th>Producto</th><th>Stock</th><th>Mínimo</th></tr>
          </thead>
          <tbody>
            <?php if (empty($stock_bajo)): ?>
              <tr><td colspan="3" class="empty-cell">Todo en orden</td></tr>
            <?php else: ?>
              <?php foreach ($stock_bajo as $sb): ?>
              <tr class="row-danger">
                <td><?= sanitize($sb['nombre']) ?></td>
                <td class="text-danger"><?= (int)$sb['stock'] ?></td>
                <td><?= (int)$sb['stock_minimo'] ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </section>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> DyP Consultora &mdash; Panel de gestión</p>
  </footer>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<!-- Chart data -->
<script>
  const chartDataVentas = <?= json_encode($ventas_30) ?>;
  const chartDataCategorias = <?= json_encode($ventas_cat) ?>;
  const chartDataEstados = <?= json_encode($pedidos_estado) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="js/admin.js"></script>

<?php endif; ?>

</body>
</html>
