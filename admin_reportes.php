<?php
// ============================================================
// ADMIN — REPORTES
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();

$db = pdo();
$estados_validos = "'pagado','preparando','enviado','entregado'";

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

// ── Period calculation ──
$periodo = $_GET['periodo'] ?? 'mes';
$desde_custom = $_GET['desde'] ?? '';
$hasta_custom = $_GET['hasta'] ?? '';

$hoy = date('Y-m-d');
switch ($periodo) {
    case 'hoy':
        $fecha_desde = $hoy;
        $fecha_hasta = $hoy;
        break;
    case 'semana':
        $fecha_desde = date('Y-m-d', strtotime('monday this week'));
        $fecha_hasta = $hoy;
        break;
    case 'anio':
        $fecha_desde = date('Y-01-01');
        $fecha_hasta = $hoy;
        break;
    case 'custom':
        $fecha_desde = $desde_custom ?: date('Y-m-01');
        $fecha_hasta = $hasta_custom ?: $hoy;
        break;
    case 'mes':
    default:
        $periodo = 'mes';
        $fecha_desde = date('Y-m-01');
        $fecha_hasta = $hoy;
        break;
}

// ── POST: Export CSV ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_csv') {
    $csv_desde = $_POST['fecha_desde'] ?? $fecha_desde;
    $csv_hasta = $_POST['fecha_hasta'] ?? $fecha_hasta;

    $sql = "SELECT p.id, p.fecha, p.nombre, p.email, p.telefono, p.direccion, p.ciudad, p.provincia,
                   p.subtotal, p.descuento, p.total, p.estado, p.cupon_codigo, p.mp_payment_id,
                   (SELECT GROUP_CONCAT(CONCAT(pi.nombre_producto, ' x', pi.cantidad) SEPARATOR ' | ')
                    FROM pedido_items pi WHERE pi.pedido_id = p.id) as detalle_items
            FROM pedidos p
            WHERE DATE(p.fecha) >= ? AND DATE(p.fecha) <= ?
            ORDER BY p.fecha DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$csv_desde, $csv_hasta]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_' . $csv_desde . '_a_' . $csv_hasta . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','Fecha','Nombre','Email','Teléfono','Dirección','Ciudad','Provincia','Subtotal','Descuento','Total','Estado','Cupón','MP Payment ID','Detalle Items']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['fecha'], $r['nombre'], $r['email'], $r['telefono'],
            $r['direccion'], $r['ciudad'], $r['provincia'],
            $r['subtotal'], $r['descuento'], $r['total'], $r['estado'],
            $r['cupon_codigo'], $r['mp_payment_id'], $r['detalle_items']
        ]);
    }
    fclose($out);
    exit;
}

// ── Metrics: Total revenue ──
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE DATE(fecha) >= ? AND DATE(fecha) <= ? AND estado IN ($estados_validos)");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$ingresos_totales = (float) $stmt->fetchColumn();

// ── Metrics: Order count (valid) ──
$stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE DATE(fecha) >= ? AND DATE(fecha) <= ? AND estado IN ($estados_validos)");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$pedidos_completados = (int) $stmt->fetchColumn();

// ── Metrics: Average ticket ──
$ticket_promedio = $pedidos_completados > 0 ? $ingresos_totales / $pedidos_completados : 0;

// ── Metrics: Cancellation rate ──
$stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE DATE(fecha) >= ? AND DATE(fecha) <= ?");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$total_pedidos_periodo = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE DATE(fecha) >= ? AND DATE(fecha) <= ? AND estado = 'cancelado'");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$pedidos_cancelados = (int) $stmt->fetchColumn();

$tasa_cancelacion = $total_pedidos_periodo > 0 ? round(($pedidos_cancelados / $total_pedidos_periodo) * 100, 1) : 0;

// ── Sales by day (for chart) ──
$stmt = $db->prepare("
    SELECT DATE(fecha) as dia, COALESCE(SUM(total), 0) as total
    FROM pedidos
    WHERE DATE(fecha) >= ? AND DATE(fecha) <= ? AND estado IN ($estados_validos)
    GROUP BY DATE(fecha)
    ORDER BY dia ASC
");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$ventas_dia_raw = $stmt->fetchAll();

$ventas_dia_map = [];
foreach ($ventas_dia_raw as $r) {
    $ventas_dia_map[$r['dia']] = (float) $r['total'];
}

$ventas_por_dia = [];
$current = strtotime($fecha_desde);
$end = strtotime($fecha_hasta);
while ($current <= $end) {
    $dia = date('Y-m-d', $current);
    $ventas_por_dia[] = [
        'dia'   => $dia,
        'label' => date('d/m', $current),
        'total' => $ventas_dia_map[$dia] ?? 0
    ];
    $current = strtotime('+1 day', $current);
}

// ── Top 10 products ──
$stmt = $db->prepare("
    SELECT pr.nombre, SUM(pi.cantidad) as cantidad, SUM(pi.cantidad * pi.precio_unitario) as ingresos
    FROM pedido_items pi
    JOIN productos pr ON pr.id = pi.producto_id
    JOIN pedidos p ON p.id = pi.pedido_id
    WHERE DATE(p.fecha) >= ? AND DATE(p.fecha) <= ? AND p.estado IN ($estados_validos)
    GROUP BY pr.id, pr.nombre
    ORDER BY cantidad DESC
    LIMIT 10
");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$top_productos = $stmt->fetchAll();

// ── Sales by category ──
$stmt = $db->prepare("
    SELECT c.nombre as categoria, SUM(pi.cantidad) as cantidad, SUM(pi.cantidad * pi.precio_unitario) as ingresos
    FROM pedido_items pi
    JOIN productos pr ON pr.id = pi.producto_id
    JOIN categorias c ON c.id = pr.categoria_id
    JOIN pedidos p ON p.id = pi.pedido_id
    WHERE DATE(p.fecha) >= ? AND DATE(p.fecha) <= ? AND p.estado IN ($estados_validos)
    GROUP BY c.id, c.nombre
    ORDER BY ingresos DESC
");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$ventas_categoria = $stmt->fetchAll();

// ── Top clients ──
$stmt = $db->prepare("
    SELECT p.nombre, p.email, COUNT(*) as pedidos, SUM(p.total) as total_gastado
    FROM pedidos p
    WHERE DATE(p.fecha) >= ? AND DATE(p.fecha) <= ? AND p.estado IN ($estados_validos)
    GROUP BY p.email, p.nombre
    ORDER BY total_gastado DESC
    LIMIT 10
");
$stmt->execute([$fecha_desde, $fecha_hasta]);
$top_clientes = $stmt->fetchAll();

// ── Period labels ──
$periodo_labels = [
    'hoy'    => 'Hoy',
    'semana' => 'Esta semana',
    'mes'    => 'Este mes',
    'anio'   => 'Este año',
    'custom' => 'Personalizado',
];
$periodo_label = $periodo_labels[$periodo] ?? 'Este mes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reportes — Admin</title>
<link rel="stylesheet" href="css/admin.css?v=20">
</head>
<body>
<?php $admin_page = 'reportes'; ?>

<?php include __DIR__ . '/includes/admin_header.php'; ?>

  <!-- Page header -->
  <div class="page-header">
    <h1>Reportes</h1>
    <p class="page-subtitle"><?= date('d/m/Y H:i') ?> — Analisis de ventas y rendimiento</p>
  </div>

  <!-- Period selector -->
  <form method="GET">
  <input type="hidden" name="periodo" value="custom" id="periodoInput">
  <div class="filtros-reportes" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;margin-bottom:24px;">

    <!-- Botones de período -->
    <div style="display:flex;gap:6px;align-items:center;">
      <?php foreach(['hoy'=>'HOY','semana'=>'SEMANA','mes'=>'MES','anio'=>'AÑO'] as $k=>$v): ?>
      <button type="button"
        onclick="document.getElementById('periodoInput').value='<?= $k ?>';this.closest('form').submit();"
        style="height:40px;padding:0 14px;border:1px solid #e0e0e0;
               background:<?= ($periodo===$k)?'#7c3aed':'transparent' ?>;
               color:<?= ($periodo===$k)?'#fff':'#666' ?>;
               font-size:0.75rem;font-weight:700;
               text-transform:uppercase;letter-spacing:0.06em;
               cursor:pointer;border-radius:20px;white-space:nowrap;">
        <?= $v ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Separador -->
    <div style="width:1px;height:28px;background:#e0e0e0;flex-shrink:0;"></div>

    <!-- DESDE -->
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.65rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:0.08em;color:#999;">Desde</label>
      <input type="date" name="desde" value="<?= sanitize($fecha_desde) ?>"
        style="height:40px;padding:0 12px;border:1px solid #e0e0e0;
               font-size:0.85rem;color:#111;background:#fff;
               box-sizing:border-box;width:160px;">
    </div>

    <!-- HASTA -->
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.65rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:0.08em;color:#999;">Hasta</label>
      <input type="date" name="hasta" value="<?= sanitize($fecha_hasta) ?>"
        style="height:40px;padding:0 12px;border:1px solid #e0e0e0;
               font-size:0.85rem;color:#111;background:#fff;
               box-sizing:border-box;width:160px;">
    </div>

    <!-- APLICAR -->
    <button type="submit"
      style="height:42px;padding:0 24px;background:#7c3aed;color:#fff;
             border:none;border-radius:10px;font-size:0.82rem;font-weight:700;
             text-transform:uppercase;letter-spacing:0.06em;cursor:pointer;
             white-space:nowrap;transition:background 0.2s;font-family:inherit;"
      onmouseover="this.style.background='#6d28d9'"
      onmouseout="this.style.background='#7c3aed'">
      APLICAR
    </button>

  </div>
  </form>

  <style>
  @media (max-width: 768px) {
    .filtros-reportes {
      flex-direction: column !important;
      align-items: stretch !important;
    }
    .filtros-reportes input[type=date] {
      width: 100% !important;
    }
    .filtros-reportes button {
      width: 100% !important;
    }
    .filtros-reportes > div:first-child {
      flex-wrap: wrap;
    }
  }
  </style>

  <p class="periodo-info">
    Periodo: <strong><?= sanitize($periodo_label) ?></strong> &mdash;
    <?= date('d/m/Y', strtotime($fecha_desde)) ?> al <?= date('d/m/Y', strtotime($fecha_hasta)) ?>
  </p>

  <!-- Metric cards -->
  <div class="metric-cards">
    <div class="metric-card">
      <span class="metric-label">Ingresos totales</span>
      <span class="metric-value" style="color:#10b981;"><?= price($ingresos_totales) ?></span>
      <span class="metric-sub"><?= $total_pedidos_periodo ?> pedidos en el periodo</span>
    </div>
    <div class="metric-card">
      <span class="metric-label">Ticket promedio</span>
      <span class="metric-value"><?= price($ticket_promedio) ?></span>
      <span class="metric-sub">Por pedido completado</span>
    </div>
    <div class="metric-card">
      <span class="metric-label">Pedidos completados</span>
      <span class="metric-value" style="color:#7c3aed;"><?= $pedidos_completados ?></span>
      <span class="metric-sub">Con estado valido</span>
    </div>
    <div class="metric-card">
      <span class="metric-label">Tasa de cancelacion</span>
      <span class="metric-value" style="color:<?= $tasa_cancelacion > 10 ? '#ef4444' : '#f59e0b' ?>;"><?= $tasa_cancelacion ?>%</span>
      <span class="metric-sub"><?= $pedidos_cancelados ?> cancelados de <?= $total_pedidos_periodo ?></span>
    </div>
  </div>

  <!-- Chart: Sales by day -->
  <div class="chart-section">
    <h3>Ventas por dia</h3>
    <canvas id="chartReporte" height="80"></canvas>
  </div>

  <!-- Export CSV -->
  <div class="export-section">
    <form method="POST">
      <input type="hidden" name="action" value="export_csv">
      <input type="hidden" name="fecha_desde" value="<?= sanitize($fecha_desde) ?>">
      <input type="hidden" name="fecha_hasta" value="<?= sanitize($fecha_hasta) ?>">
      <button type="submit" class="btn-export">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Exportar CSV
      </button>
    </form>
  </div>

  <!-- Tables -->
  <div class="report-tables two-col">

    <!-- Top 10 productos -->
    <div class="table-card">
      <h3>Top 10 productos</h3>
      <div class="table-wrap table-container">
        <table>
          <thead>
            <tr><th>Producto</th><th style="text-align:center;">Cantidad</th><th style="text-align:right;">Ingresos</th></tr>
          </thead>
          <tbody>
            <?php if (empty($top_productos)): ?>
              <tr><td colspan="3" class="empty-cell">Sin datos en este periodo</td></tr>
            <?php else: ?>
              <?php foreach ($top_productos as $tp): ?>
              <tr>
                <td><?= sanitize($tp['nombre']) ?></td>
                <td style="text-align:center;"><?= (int)$tp['cantidad'] ?></td>
                <td style="text-align:right;"><?= price((float)$tp['ingresos']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Ventas por categoria -->
    <div class="table-card">
      <h3>Ventas por categoria</h3>
      <div class="table-wrap table-container">
        <table>
          <thead>
            <tr><th>Categoria</th><th style="text-align:center;">Cantidad</th><th style="text-align:right;">Ingresos</th></tr>
          </thead>
          <tbody>
            <?php if (empty($ventas_categoria)): ?>
              <tr><td colspan="3" class="empty-cell">Sin datos en este periodo</td></tr>
            <?php else: ?>
              <?php foreach ($ventas_categoria as $vc): ?>
              <tr>
                <td><?= sanitize($vc['categoria']) ?></td>
                <td style="text-align:center;"><?= (int)$vc['cantidad'] ?></td>
                <td style="text-align:right;"><?= price((float)$vc['ingresos']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Top clientes -->
  <div class="report-tables">
    <div class="table-card">
      <h3>Top clientes</h3>
      <div class="table-wrap table-container">
        <table>
          <thead>
            <tr><th>Nombre</th><th>Email</th><th style="text-align:center;">Pedidos</th><th style="text-align:right;">Total gastado</th></tr>
          </thead>
          <tbody>
            <?php if (empty($top_clientes)): ?>
              <tr><td colspan="4" class="empty-cell">Sin datos en este periodo</td></tr>
            <?php else: ?>
              <?php foreach ($top_clientes as $tc): ?>
              <tr>
                <td><?= sanitize($tc['nombre']) ?></td>
                <td style="color:#71717a; font-size:.85rem;"><?= sanitize($tc['email']) ?></td>
                <td style="text-align:center;"><?= (int)$tc['pedidos'] ?></td>
                <td style="text-align:right;"><?= price((float)$tc['total_gastado']) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> DyP Consultora &mdash; Panel de gestión</p>
  </footer>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<!-- Chart data -->
<script>
  const chartDataReporte = <?= json_encode($ventas_por_dia) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// ── Reportes chart ──
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartReporte');
    if (ctx && typeof Chart !== 'undefined' && chartDataReporte.length > 0) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartDataReporte.map(d => d.label),
                datasets: [{
                    label: 'Ventas ($)',
                    data: chartDataReporte.map(d => d.total),
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#7c3aed',
                    pointBorderColor: '#7c3aed',
                    pointRadius: 3,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1a2e',
                        titleColor: '#e4e4e7',
                        bodyColor: '#a78bfa',
                        borderColor: '#27272a',
                        borderWidth: 1,
                        callbacks: {
                            label: function(ctx) {
                                return '$' + ctx.parsed.y.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#71717a', font: { size: 11 } },
                        grid: { color: 'rgba(39,39,42,0.5)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#71717a',
                            font: { size: 11 },
                            callback: function(v) { return '$' + v.toLocaleString('es-AR'); }
                        },
                        grid: { color: 'rgba(39,39,42,0.5)' }
                    }
                }
            }
        });
    }
});
</script>
<script src="js/admin.js"></script>

</body>
</html>
