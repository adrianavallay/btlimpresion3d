<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

require_cliente();

$db = pdo();
$item_count = cart_count();

// ── Detail view ──
$detalle = null;
$detalle_items = [];

if (isset($_GET['id'])) {
    $pedido_id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM pedidos WHERE id = ? AND cliente_id = ?");
    $stmt->execute([$pedido_id, cliente_id()]);
    $detalle = $stmt->fetch();

    if ($detalle) {
        $stmt = $db->prepare("
            SELECT pi.*, p.imagen_principal
            FROM pedido_items pi
            LEFT JOIN productos p ON pi.producto_id = p.id
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedido_id]);
        $detalle_items = $stmt->fetchAll();
    }
}

// ── Orders list ──
$stmt = $db->prepare("
    SELECT p.id, p.fecha, p.total, p.estado,
           (SELECT SUM(pi.cantidad) FROM pedido_items pi WHERE pi.pedido_id = p.id) as items_count
    FROM pedidos p
    WHERE p.cliente_id = ?
    ORDER BY p.fecha DESC
");
$stmt->execute([cliente_id()]);
$pedidos = $stmt->fetchAll();

$page_title = 'Mis pedidos';
$extra_css = null;
include __DIR__ . '/includes/header.php';
?>

<!-- Styles now in css/tienda.css -->

<!-- ═══════════ ACCOUNT ═══════════ -->
<div class="container account-layout">

    <!-- SIDEBAR -->
    <aside class="account-sidebar">
        <h3>Mi cuenta</h3>
        <a href="<?= url_pagina('mi-cuenta') ?>">Mi cuenta</a>
        <a href="<?= url_pagina('mis-pedidos') ?>" class="active">Mis pedidos</a>
        <a href="<?= url_pagina('wishlist') ?>">Wishlist</a>
        <a href="<?= SITE_URL ?>/logout" class="logout-link">Cerrar sesión</a>
    </aside>

    <!-- CONTENT -->
    <div class="account-content">

        <?php if ($detalle): ?>
        <!-- ═══════════ ORDER DETAIL ═══════════ -->
        <a href="<?= url_pagina('mis-pedidos') ?>" class="back-link">&larr; Volver a mis pedidos</a>

        <div class="account-section">
            <div class="order-detail-header">
                <h2>Pedido #<?= (int)$detalle['id'] ?></h2>
                <span class="status-badge status-<?= sanitize($detalle['estado']) ?>">
                    <?= sanitize(ucfirst($detalle['estado'])) ?>
                </span>
            </div>

            <div class="order-meta">
                <div class="order-meta-item">
                    <div class="label">Fecha</div>
                    <div class="value"><?= date('d/m/Y H:i', strtotime($detalle['fecha'])) ?></div>
                </div>
                <div class="order-meta-item">
                    <div class="label">Estado</div>
                    <div class="value"><?= sanitize(ucfirst($detalle['estado'])) ?></div>
                </div>
                <div class="order-meta-item">
                    <div class="label">Total</div>
                    <div class="value" style="color:var(--accent-light);font-weight:700;"><?= price((float)$detalle['total']) ?></div>
                </div>
                <?php if (!empty($detalle['cupon_codigo'])): ?>
                <div class="order-meta-item">
                    <div class="label">Cupón</div>
                    <div class="value"><?= sanitize($detalle['cupon_codigo']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items table -->
            <div style="overflow-x:auto;">
                <table class="detail-items-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio unit.</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle_items as $item):
                            $img = img_url($item['imagen_principal'] ?? '');
                            $line = (float)$item['precio_unitario'] * (int)$item['cantidad'];
                        ?>
                        <tr>
                            <td>
                                <?php if ($img): ?>
                                    <img src="<?= sanitize($img) ?>" alt="<?= sanitize($item['nombre_producto']) ?>" class="detail-thumb">
                                <?php else: ?>
                                    <div class="detail-thumb" style="display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--text-muted);font-size:.6rem;">Sin img</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= sanitize($item['nombre_producto']) ?></div>
                                <?php if (!empty($item['variante'])): ?>
                                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px;"><?= sanitize($item['variante']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$item['cantidad'] ?></td>
                            <td><?= price((float)$item['precio_unitario']) ?></td>
                            <td style="text-align:right;font-weight:600;"><?= price($line) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="detail-totals">
                <div class="line">
                    <span style="color:var(--text-muted);">Subtotal</span>
                    <span><?= price((float)$detalle['subtotal']) ?></span>
                </div>
                <?php if ((float)$detalle['descuento'] > 0): ?>
                <div class="line" style="color:#4ade80;">
                    <span>Descuento</span>
                    <span>-<?= price((float)$detalle['descuento']) ?></span>
                </div>
                <?php endif; ?>
                <div class="line total">
                    <span>Total</span>
                    <span class="val"><?= price((float)$detalle['total']) ?></span>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ═══════════ ORDERS LIST ═══════════ -->
        <h1 style="font-size:1.5rem;margin-bottom:24px;">Mis pedidos</h1>

        <div class="account-section">
            <?php if (empty($pedidos)): ?>
                <div style="text-align:center;padding:40px 20px;">
                    <div style="font-size:3rem;margin-bottom:16px;opacity:.4;">&#128230;</div>
                    <p style="font-size:1rem;color:var(--text-muted);margin-bottom:20px;">No tenés pedidos todavía.</p>
                    <a href="<?= SITE_URL ?>/" class="btn-add-cart" style="display:inline-block;padding:12px 28px;text-decoration:none;">
                        Explorar catálogo
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Productos</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $p): ?>
                            <tr>
                                <td style="font-weight:600;">#<?= (int)$p['id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                                <td><?= (int)($p['items_count'] ?? 0) ?> <?= (int)($p['items_count'] ?? 0) === 1 ? 'item' : 'items' ?></td>
                                <td style="font-weight:600;"><?= price((float)$p['total']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= sanitize($p['estado']) ?>">
                                        <?= sanitize(ucfirst($p['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= url_pagina('mis-pedidos') ?>?id=<?= (int)$p['id'] ?>" style="color:var(--accent-light);font-size:.82rem;text-decoration:none;font-weight:600;">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
