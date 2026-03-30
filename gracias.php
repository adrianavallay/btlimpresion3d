<?php
require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// 1. Get pedido by ID
// ---------------------------------------------------------------------------
$pedido_id = (int) ($_GET['id'] ?? 0);
if ($pedido_id <= 0) {
    redirect(SITE_URL . '/');
}

$stmt = pdo()->prepare("SELECT * FROM pedidos WHERE id = ? LIMIT 1");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    redirect(SITE_URL . '/');
}

// ---------------------------------------------------------------------------
// 2. Get pedido items with product images
// ---------------------------------------------------------------------------
$stmtItems = pdo()->prepare("
    SELECT pi.*, p.imagen_principal
    FROM pedido_items pi
    LEFT JOIN productos p ON pi.producto_id = p.id
    WHERE pi.pedido_id = ?
");
$stmtItems->execute([$pedido_id]);
$items = $stmtItems->fetchAll();

$cartCount = cart_count();

// ---------------------------------------------------------------------------
// 3. Status badge mapping
// ---------------------------------------------------------------------------
$estado_labels = [
    'pendiente'   => ['label' => 'Pendiente',   'color' => '#f59e0b'],
    'pagado'      => ['label' => 'Pagado',      'color' => '#22c55e'],
    'preparando'  => ['label' => 'Preparando',  'color' => '#3b82f6'],
    'enviado'     => ['label' => 'Enviado',     'color' => '#8b5cf6'],
    'entregado'   => ['label' => 'Entregado',   'color' => '#22c55e'],
    'cancelado'   => ['label' => 'Cancelado',   'color' => '#ef4444'],
    'reembolsado' => ['label' => 'Reembolsado', 'color' => '#71717a'],
];
$estado_info = $estado_labels[$pedido['estado']] ?? ['label' => ucfirst($pedido['estado']), 'color' => '#71717a'];

$page_title = 'Gracias por tu compra';
include __DIR__ . '/includes/header.php';
?>

<style>
    .gracias-card {
      max-width: 680px;
      margin: 0 auto;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 48px 40px;
      text-align: center;
    }
    @media (max-width: 600px) {
      .gracias-card { padding: 32px 20px; }
    }

    /* Checkmark icon */
    .check-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: rgba(34, 197, 94, 0.12);
      border: 3px solid #22c55e;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
      animation: scaleIn .4s ease;
    }
    .check-icon::after {
      content: '';
      display: block;
      width: 24px;
      height: 40px;
      border-right: 4px solid #22c55e;
      border-bottom: 4px solid #22c55e;
      transform: rotate(45deg) translateY(-4px);
    }
    @keyframes scaleIn {
      0% { transform: scale(0); opacity: 0; }
      60% { transform: scale(1.15); }
      100% { transform: scale(1); opacity: 1; }
    }

    .gracias-card h1 {
      font-family: 'Sora', sans-serif;
      font-size: 1.8rem;
      margin-bottom: 8px;
      color: var(--text);
    }
    .gracias-card .subtitle {
      color: var(--text-muted);
      font-size: .95rem;
      margin-bottom: 32px;
    }

    /* Order table */
    .order-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
      text-align: left;
    }
    .order-table th {
      font-size: .75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--text-muted);
      padding: 10px 8px;
      border-bottom: 1px solid var(--border);
    }
    .order-table td {
      padding: 12px 8px;
      font-size: .88rem;
      color: var(--text);
      border-bottom: 1px solid rgba(255,255,255,.05);
      vertical-align: middle;
    }
    .order-table .item-img {
      width: 44px;
      height: 44px;
      object-fit: cover;
      border-radius: 8px;
      background: var(--bg-body);
    }
    .order-table .item-cell {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Totals */
    .order-totals {
      text-align: right;
      margin-bottom: 28px;
      font-size: .9rem;
    }
    .order-totals .row {
      display: flex;
      justify-content: flex-end;
      gap: 24px;
      padding: 6px 8px;
      color: var(--text-muted);
    }
    .order-totals .row span:last-child {
      min-width: 100px;
      text-align: right;
      color: var(--text);
    }
    .order-totals .row--total {
      font-weight: 700;
      font-size: 1.05rem;
      border-top: 1px solid var(--border);
      padding-top: 12px;
      margin-top: 6px;
    }
    .order-totals .row--total span:last-child {
      color: var(--accent-light);
    }
    .order-totals .row--discount span:last-child {
      color: #22c55e;
    }

    /* Customer info */
    .customer-info {
      display: flex;
      justify-content: center;
      gap: 32px;
      flex-wrap: wrap;
      margin-bottom: 28px;
      font-size: .88rem;
      color: var(--text-muted);
    }
    .customer-info strong {
      color: var(--text);
      font-weight: 600;
    }

    /* Status badge */
    .status-badge {
      display: inline-block;
      padding: 6px 16px;
      border-radius: 20px;
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      margin-bottom: 28px;
    }

    /* Buttons */
    .gracias-actions {
      display: flex;
      justify-content: center;
      gap: 14px;
      flex-wrap: wrap;
    }
    .gracias-actions .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 28px;
      background: var(--accent);
      color: #fff;
      font-weight: 600;
      font-size: .9rem;
      border: none;
      border-radius: 10px;
      text-decoration: none;
      cursor: pointer;
      transition: background .2s, transform .15s;
    }
    .gracias-actions .btn-primary:hover {
      background: var(--accent-light);
      transform: translateY(-1px);
    }
    .gracias-actions .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 28px;
      background: transparent;
      color: var(--accent-light);
      font-weight: 600;
      font-size: .9rem;
      border: 1px solid var(--border);
      border-radius: 10px;
      text-decoration: none;
      cursor: pointer;
      transition: border-color .2s, transform .15s;
    }
    .gracias-actions .btn-secondary:hover {
      border-color: var(--accent-light);
      transform: translateY(-1px);
    }
  </style>

<!-- ═══════════ MAIN CONTENT ═══════════ -->
<section class="container" style="padding-top:120px;padding-bottom:80px;min-height:80vh;display:flex;align-items:center;justify-content:center;">
  <div class="gracias-card fade-in">

    <!-- Checkmark -->
    <div class="check-icon"></div>

    <h1>&iexcl;Gracias por tu compra!</h1>
    <p class="subtitle">Tu pedido <strong>#<?= (int) $pedido['id'] ?></strong> fue registrado con &eacute;xito</p>

    <!-- Status badge -->
    <span class="status-badge"
          style="background:<?= $estado_info['color'] ?>20;color:<?= $estado_info['color'] ?>;border:1px solid <?= $estado_info['color'] ?>40;">
      <?= sanitize($estado_info['label']) ?>
    </span>

    <!-- Order items table -->
    <?php if (!empty($items)): ?>
    <table class="order-table">
      <thead>
        <tr>
          <th>Producto</th>
          <th style="text-align:center">Cant.</th>
          <th style="text-align:right">Precio</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <?php
            $img = sanitize($item['imagen_principal'] ?: 'uploads/productos/placeholder.png');
            $nombre = sanitize($item['nombre_producto']);
            $variante = $item['variante'] ? ' (' . sanitize($item['variante']) . ')' : '';
          ?>
          <tr>
            <td>
              <div class="item-cell">
                <img src="<?= $img ?>" alt="<?= $nombre ?>" class="item-img" loading="lazy">
                <span><?= $nombre ?><?= $variante ?></span>
              </div>
            </td>
            <td style="text-align:center"><?= (int) $item['cantidad'] ?></td>
            <td style="text-align:right"><?= price((float) $item['precio_unitario'] * (int) $item['cantidad']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <!-- Totals -->
    <div class="order-totals">
      <div class="row">
        <span>Subtotal</span>
        <span><?= price((float) $pedido['subtotal']) ?></span>
      </div>
      <?php if ((float) $pedido['descuento'] > 0): ?>
        <div class="row row--discount">
          <span>Descuento<?= $pedido['cupon_codigo'] ? ' (' . sanitize($pedido['cupon_codigo']) . ')' : '' ?></span>
          <span>-<?= price((float) $pedido['descuento']) ?></span>
        </div>
      <?php endif; ?>
      <div class="row row--total">
        <span>Total</span>
        <span><?= price((float) $pedido['total']) ?></span>
      </div>
    </div>

    <!-- Customer info -->
    <div class="customer-info">
      <?php if ($pedido['nombre']): ?>
        <div><strong>Nombre:</strong> <?= sanitize($pedido['nombre']) ?></div>
      <?php endif; ?>
      <?php if ($pedido['email']): ?>
        <div><strong>Email:</strong> <?= sanitize($pedido['email']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Action buttons -->
    <div class="gracias-actions">
      <a href="<?= SITE_URL ?>/" class="btn-primary">&#128722; Volver a la tienda</a>
      <?php if (is_cliente()): ?>
        <a href="<?= url_pagina('mis-pedidos') ?>" class="btn-secondary">Ver mis pedidos</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
