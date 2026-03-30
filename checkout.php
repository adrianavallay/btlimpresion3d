<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// ── Cart check ────────────────────────────────────────────
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    redirect(url_pagina('carrito'));
}

// ── Cart summary ──────────────────────────────────────────
$subtotal = 0;
$cart_items = [];
foreach ($cart as $key => $item) {
    $line_total = $item['precio'] * $item['qty'];
    $subtotal += $line_total;
    $cart_items[] = array_merge($item, ['key' => $key, 'line_total' => $line_total]);
}

$descuento = 0;
$cupon = $_SESSION['cupon'] ?? null;
if ($cupon) {
    if ($cupon['tipo'] === 'porcentaje') {
        $descuento = round($subtotal * $cupon['valor'] / 100, 2);
    } else {
        $descuento = min($cupon['valor'], $subtotal);
    }
}
$total = max(0, $subtotal - $descuento);

// ── Pre-fill client data ──────────────────────────────────
$cliente = null;
if (is_cliente()) {
    $cliente = cliente_data();
}

$flash_error = flash('error');
$flash_success = flash('success');

$page_title = 'Checkout';
include __DIR__ . '/includes/header.php';
?>

  <!-- ═══════════ BREADCRUMBS ═══════════ -->
  <div class="container" style="padding-top:80px;">
    <nav class="breadcrumbs">
      <a href="<?= SITE_URL ?>/">Inicio</a>
      <span class="sep">/</span>
      <a href="<?= url_pagina('carrito') ?>">Carrito</a>
      <span class="sep">/</span>
      <span class="current">Checkout</span>
    </nav>
  </div>

  <!-- ═══════════ CHECKOUT ═══════════ -->
  <section class="container page-enter" style="padding-bottom:60px;">

    <?php if ($flash_error): ?>
      <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4);color:#f87171;padding:14px 20px;border-radius:8px;margin-bottom:24px;font-size:.9rem;">
        <?= sanitize($flash_error) ?>
      </div>
    <?php endif; ?>

    <?php if ($flash_success): ?>
      <div style="background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.4);color:#4ade80;padding:14px 20px;border-radius:8px;margin-bottom:24px;font-size:.9rem;">
        <?= sanitize($flash_success) ?>
      </div>
    <?php endif; ?>

    <h1 style="font-size:1.6rem;margin-bottom:32px;">Finalizar compra</h1>

    <form action="checkout_process.php" method="POST">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

      <div class="checkout-grid" style="display:grid;grid-template-columns:1fr 400px;gap:40px;align-items:start;">

        <!-- ── LEFT: Form ── -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:32px;">
          <h2 style="font-size:1.1rem;margin-bottom:24px;color:var(--accent-light);">Datos de envío</h2>

          <div class="form-group" style="margin-bottom:18px;">
            <label style="display:block;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px;">Nombre completo *</label>
            <input type="text" name="nombre" class="form-input" required
                   value="<?= sanitize($cliente['nombre'] ?? '') ?>"
                   placeholder="Tu nombre completo"
                   style="width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;">
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group" style="margin-bottom:18px;">
              <label style="display:block;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px;">Email *</label>
              <input type="email" name="email" class="form-input" required
                     value="<?= sanitize($cliente['email'] ?? ($_SESSION['cliente_email'] ?? '')) ?>"
                     placeholder="tu@email.com"
                     style="width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;">
            </div>

            <div class="form-group" style="margin-bottom:18px;">
              <label style="display:block;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px;">Teléfono</label>
              <input type="tel" name="telefono" class="form-input"
                     value="<?= sanitize($cliente['telefono'] ?? '') ?>"
                     placeholder="+54 11 1234-5678"
                     style="width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;">
            </div>
          </div>

          <div class="form-group" style="margin-bottom:18px;">
            <label style="display:block;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px;">Dirección *</label>
            <input type="text" name="direccion" class="form-input" required
                   value="<?= sanitize($cliente['direccion'] ?? '') ?>"
                   placeholder="Calle, número, piso, depto"
                   style="width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;">
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group" style="margin-bottom:18px;">
              <label style="display:block;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px;">Ciudad *</label>
              <input type="text" name="ciudad" class="form-input" required
                     value="<?= sanitize($cliente['ciudad'] ?? '') ?>"
                     placeholder="Tu ciudad"
                     style="width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;">
            </div>

            <div class="form-group" style="margin-bottom:18px;">
              <label style="display:block;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px;">Provincia *</label>
              <input type="text" name="provincia" class="form-input" required
                     value="<?= sanitize($cliente['provincia'] ?? '') ?>"
                     placeholder="Tu provincia"
                     style="width:100%;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;">
            </div>
          </div>
        </div>

        <!-- ── RIGHT: Order summary ── -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:28px;position:sticky;top:100px;">
          <h2 style="font-size:1.1rem;margin-bottom:20px;color:var(--accent-light);">Resumen del pedido</h2>

          <div style="max-height:320px;overflow-y:auto;margin-bottom:20px;">
            <?php foreach ($cart_items as $item): ?>
              <div style="display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
                <img src="<?= sanitize($item['imagen'] ?: 'uploads/productos/placeholder.jpg') ?>"
                     alt="<?= sanitize($item['nombre']) ?>"
                     style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                <div style="flex:1;min-width:0;">
                  <p style="font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0;">
                    <?= sanitize($item['nombre']) ?>
                  </p>
                  <?php if (!empty($item['variante'])): ?>
                    <p style="font-size:.72rem;color:var(--text-muted);margin:2px 0 0;"><?= sanitize($item['variante']) ?></p>
                  <?php endif; ?>
                  <p style="font-size:.78rem;color:var(--text-muted);margin:2px 0 0;">
                    <?= $item['qty'] ?> x <?= price($item['precio']) ?>
                  </p>
                </div>
                <span style="font-size:.88rem;font-weight:600;color:var(--text);white-space:nowrap;">
                  <?= price($item['line_total']) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Coupon -->
          <?php if (!$cupon): ?>
            <div style="display:flex;gap:8px;margin-bottom:16px;">
              <input type="text" name="coupon_code" class="form-input" placeholder="Código de cupón"
                     style="flex:1;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.82rem;">
              <button type="button" id="apply-coupon-btn"
                      style="padding:10px 16px;background:var(--border);color:var(--text);border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .2s;">
                Aplicar
              </button>
            </div>
          <?php else: ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:8px;margin-bottom:16px;">
              <span style="font-size:.82rem;color:#4ade80;font-weight:600;">
                Cupón: <?= sanitize($cupon['codigo']) ?>
              </span>
              <button type="button" id="remove-coupon-btn"
                      style="background:none;border:none;color:#f87171;font-size:.78rem;cursor:pointer;font-weight:600;">
                Quitar
              </button>
            </div>
          <?php endif; ?>

          <!-- Totals -->
          <div style="border-top:1px solid var(--border);padding-top:16px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:.88rem;color:var(--text-muted);">
              <span>Subtotal</span>
              <span><?= price($subtotal) ?></span>
            </div>

            <?php if ($descuento > 0): ?>
              <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:.88rem;color:#4ade80;">
                <span>Descuento</span>
                <span>-<?= price($descuento) ?></span>
              </div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:1.15rem;font-weight:700;">
              <span>Total</span>
              <span style="color:var(--accent-light);"><?= price($total) ?></span>
            </div>
          </div>

          <!-- Submit -->
          <button type="submit"
                  style="width:100%;margin-top:24px;padding:16px;border:none;border-radius:10px;font-size:1rem;font-weight:700;color:#fff;cursor:pointer;
                         background:linear-gradient(135deg,#7c3aed,#2563eb);
                         transition:opacity .2s,transform .15s;letter-spacing:.02em;">
            Pagar con MercadoPago
          </button>

          <p style="text-align:center;font-size:.72rem;color:var(--text-muted);margin-top:12px;">
            Serás redirigido a MercadoPago para completar el pago de forma segura.
          </p>
        </div>

      </div>
    </form>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>
  <script>
    // Coupon apply via AJAX
    document.addEventListener('DOMContentLoaded', function() {
      const applyBtn = document.getElementById('apply-coupon-btn');
      if (applyBtn) {
        applyBtn.addEventListener('click', function() {
          const code = document.querySelector('input[name="coupon_code"]').value.trim();
          if (!code) return;
          const fd = new FormData();
          fd.append('action', 'apply_coupon');
          fd.append('codigo', code);
          fetch('carrito_api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
              if (data.ok) {
                location.reload();
              } else {
                alert(data.mensaje || 'Error al aplicar cupón');
              }
            });
        });
      }

      const removeBtn = document.getElementById('remove-coupon-btn');
      if (removeBtn) {
        removeBtn.addEventListener('click', function() {
          const fd = new FormData();
          fd.append('action', 'remove_coupon');
          fetch('carrito_api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
              if (data.ok) location.reload();
            });
        });
      }
    });
  </script>
