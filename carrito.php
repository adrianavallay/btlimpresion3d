<?php
require_once __DIR__ . '/config.php';

// ============================================================
// CART DATA
// ============================================================
$cart = $_SESSION['cart'] ?? [];
$item_count = cart_count();

// Calculate subtotal
$subtotal = 0;
foreach ($cart as $key => $item) {
    $subtotal += $item['precio'] * $item['qty'];
}

// Coupon / discount
$cupon = $_SESSION['cupon'] ?? null;
$descuento = 0;
if ($cupon) {
    if ($cupon['tipo'] === 'porcentaje') {
        $descuento = round($subtotal * $cupon['valor'] / 100, 2);
    } else {
        $descuento = min($cupon['valor'], $subtotal);
    }
}

// Total
$total = max(0, $subtotal - $descuento);

$page_title = 'Carrito';
include __DIR__ . '/includes/header.php';
?>

<div class="cart-page">
    <div class="container">

        <h1 class="cart-page-title">Tu carrito <span class="cart-page-count">(<?= $item_count ?> <?= $item_count === 1 ? 'producto' : 'productos' ?>)</span></h1>

        <?php if (empty($cart)): ?>
        <!-- EMPTY STATE -->
        <div class="cart-empty-state">
            <div class="cart-empty-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            </div>
            <p>Tu carrito está vacío</p>
            <a href="<?= url_pagina('tienda') ?>" class="btn-cart-primary" style="display:inline-block;padding:12px 32px;margin-top:16px;">Ver productos</a>
        </div>

        <?php else: ?>
        <!-- CART WITH ITEMS -->
        <div class="cart-grid">

            <!-- LEFT: Items -->
            <div class="cart-items-col">
                <?php foreach ($cart as $key => $item):
                    $line_total = $item['precio'] * $item['qty'];
                    $imagen = img_url($item['imagen'] ?? '');
                ?>
                <div class="cart-item-row" data-key="<?= sanitize($key) ?>">
                    <div class="cart-item-img">
                        <?php if ($imagen): ?>
                            <img src="<?= sanitize($imagen) ?>" alt="<?= sanitize($item['nombre']) ?>">
                        <?php else: ?>
                            <div class="cart-item-noimg">Sin img</div>
                        <?php endif; ?>
                    </div>
                    <div class="cart-item-details">
                        <div class="cart-item-name"><?= sanitize($item['nombre']) ?></div>
                        <?php if (!empty($item['variante'])): ?>
                            <div class="cart-item-variant"><?= sanitize($item['variante']) ?></div>
                        <?php endif; ?>
                        <div class="cart-item-unit-price"><?= price($item['precio']) ?></div>
                    </div>
                    <div class="cart-item-qty-controls">
                        <button type="button" class="cart-qty-btn" data-key="<?= sanitize($key) ?>" data-action="decrease">&minus;</button>
                        <span class="cart-qty-value"><?= (int)$item['qty'] ?></span>
                        <button type="button" class="cart-qty-btn" data-key="<?= sanitize($key) ?>" data-action="increase">+</button>
                    </div>
                    <div class="cart-item-line-total"><?= price($line_total) ?></div>
                    <button type="button" class="cart-item-remove-btn" data-key="<?= sanitize($key) ?>" title="Eliminar">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- RIGHT: Summary -->
            <div class="cart-summary-col">
                <div class="cart-summary-card">
                    <h3>Resumen del pedido</h3>

                    <!-- Coupon -->
                    <div class="cart-coupon-section">
                        <?php if ($cupon): ?>
                            <div class="cart-coupon-applied">
                                <span>
                                    <?= sanitize($cupon['codigo']) ?>
                                    <?php if ($cupon['tipo'] === 'porcentaje'): ?>
                                        (-<?= (int)$cupon['valor'] ?>%)
                                    <?php else: ?>
                                        (-<?= price($cupon['valor']) ?>)
                                    <?php endif; ?>
                                </span>
                                <button type="button" class="cart-coupon-remove" title="Quitar cupón">&times;</button>
                            </div>
                        <?php else: ?>
                            <div class="cart-coupon-form">
                                <input type="text" id="couponInput" placeholder="Código de cupón">
                                <button type="button" id="couponApplyBtn">Aplicar</button>
                            </div>
                            <p id="couponMsg" class="cart-coupon-msg"></p>
                        <?php endif; ?>
                    </div>

                    <!-- Totals -->
                    <div class="cart-totals">
                        <div class="cart-totals-line">
                            <span>Subtotal</span>
                            <span id="cartSubtotal"><?= price($subtotal) ?></span>
                        </div>
                        <?php if ($descuento > 0): ?>
                        <div class="cart-totals-line cart-totals-discount">
                            <span>Descuento</span>
                            <span id="cartDiscount">-<?= price($descuento) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="cart-totals-line">
                            <span>Envío</span>
                            <span class="cart-totals-muted">Se calcula en el checkout</span>
                        </div>
                        <div class="cart-totals-line cart-totals-final">
                            <span>Total</span>
                            <span id="cartTotal"><?= price($total) ?></span>
                        </div>
                    </div>

                    <a href="<?= url_pagina('checkout') ?>" class="btn-cart-primary">Ir al checkout</a>
                    <a href="<?= url_pagina('tienda') ?>" class="btn-cart-checkout">&larr; Seguir comprando</a>
                </div>
            </div>

        </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Cart page quantity & remove handlers
document.querySelectorAll('.cart-qty-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var key = this.dataset.key;
        var row = this.closest('.cart-item-row');
        var qtyEl = row.querySelector('.cart-qty-value');
        var qty = parseInt(qtyEl.textContent);
        var newQty = this.dataset.action === 'increase' ? qty + 1 : qty - 1;
        if (newQty < 1) return;
        fetch('carrito_api.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'update', key: key, qty: newQty })
        }).then(function() { location.reload(); });
    });
});

document.querySelectorAll('.cart-item-remove-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var key = this.dataset.key;
        fetch('carrito_api.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'remove', key: key })
        }).then(function() { location.reload(); });
    });
});

// Coupon
var couponApply = document.getElementById('couponApplyBtn');
if (couponApply) {
    couponApply.addEventListener('click', function() {
        var code = document.getElementById('couponInput').value.trim();
        if (!code) return;
        fetch('carrito_api.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'apply_coupon', codigo: code })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) { location.reload(); }
            else {
                var msg = document.getElementById('couponMsg');
                msg.textContent = data.mensaje;
                msg.style.display = 'block';
                msg.style.color = 'var(--danger)';
            }
        });
    });
}

document.querySelectorAll('.cart-coupon-remove').forEach(function(btn) {
    btn.addEventListener('click', function() {
        fetch('carrito_api.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'remove_coupon' })
        }).then(function() { location.reload(); });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
