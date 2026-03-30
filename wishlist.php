<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

// ============================================================
// AJAX: TOGGLE WISHLIST (from product cards)
// ============================================================
$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Toggle (from product cards) ──────────────────────────
    if ($action === 'toggle') {
        if (!is_cliente()) {
            json_response(['ok' => false, 'mensaje' => 'Debés iniciar sesión'], 401);
        }
        csrf_check();
        $producto_id = (int) ($_POST['producto_id'] ?? 0);
        if ($producto_id <= 0) {
            json_response(['ok' => false, 'mensaje' => 'Producto inválido'], 400);
        }

        $db = pdo();
        $stmt = $db->prepare("SELECT id FROM wishlist WHERE cliente_id = ? AND producto_id = ?");
        $stmt->execute([cliente_id(), $producto_id]);
        $exists = $stmt->fetch();

        if ($exists) {
            $db->prepare("DELETE FROM wishlist WHERE id = ?")->execute([$exists['id']]);
            json_response(['ok' => true, 'in_wishlist' => false, 'mensaje' => 'Eliminado de favoritos']);
        } else {
            $db->prepare("INSERT INTO wishlist (cliente_id, producto_id) VALUES (?, ?)")
               ->execute([cliente_id(), $producto_id]);
            json_response(['ok' => true, 'in_wishlist' => true, 'mensaje' => 'Agregado a favoritos']);
        }
    }

    // ── Require login for remaining actions ──────────────────
    require_cliente();
    csrf_check();

    // ── Remove from wishlist ─────────────────────────────────
    if ($action === 'remove') {
        $producto_id = (int) ($_POST['producto_id'] ?? 0);
        if ($producto_id > 0) {
            pdo()->prepare("DELETE FROM wishlist WHERE cliente_id = ? AND producto_id = ?")
                 ->execute([cliente_id(), $producto_id]);
        }
        if ($is_ajax) {
            json_response(['ok' => true, 'mensaje' => 'Producto eliminado de favoritos']);
        }
        flash('success', 'Producto eliminado de tu lista de deseos');
        redirect(url_pagina('wishlist'));
    }

    // ── Add to cart (move from wishlist) ─────────────────────
    if ($action === 'add_to_cart') {
        $producto_id = (int) ($_POST['producto_id'] ?? 0);
        if ($producto_id > 0) {
            // Fetch product data
            $stmt = pdo()->prepare("SELECT * FROM productos WHERE id = ? AND estado = 'activo'");
            $stmt->execute([$producto_id]);
            $prod = $stmt->fetch();

            if ($prod && (int) $prod['stock'] > 0) {
                $cart = $_SESSION['cart'] ?? [];
                $key  = 'p' . $producto_id;
                $precio_final = ($prod['precio_oferta'] !== null && (float) $prod['precio_oferta'] > 0)
                    ? (float) $prod['precio_oferta']
                    : (float) $prod['precio'];

                if (isset($cart[$key])) {
                    $cart[$key]['qty'] += 1;
                } else {
                    $cart[$key] = [
                        'producto_id' => (int) $prod['id'],
                        'nombre'      => $prod['nombre'],
                        'precio'      => $precio_final,
                        'imagen'      => $prod['imagen_principal'],
                        'variante'    => '',
                        'qty'         => 1,
                    ];
                }
                $_SESSION['cart'] = $cart;

                // Optionally remove from wishlist after adding to cart
                pdo()->prepare("DELETE FROM wishlist WHERE cliente_id = ? AND producto_id = ?")
                     ->execute([cliente_id(), $producto_id]);

                if ($is_ajax) {
                    json_response([
                        'ok'         => true,
                        'mensaje'    => 'Producto agregado al carrito',
                        'cart_count' => cart_count(),
                    ]);
                }
                flash('success', 'Producto agregado al carrito');
            } else {
                if ($is_ajax) {
                    json_response(['ok' => false, 'mensaje' => 'Producto no disponible'], 400);
                }
                flash('error', 'Producto no disponible');
            }
        }
        redirect(url_pagina('wishlist'));
    }
}

// ============================================================
// REQUIRE LOGIN FOR PAGE VIEW
// ============================================================
require_cliente();

// ============================================================
// FETCH WISHLIST ITEMS
// ============================================================
$stmt = pdo()->prepare("
    SELECT w.id AS wishlist_id, w.producto_id, w.fecha AS fecha_agregado,
           p.nombre, p.slug, p.precio, p.precio_oferta, p.imagen_principal,
           p.stock, p.estado, p.descripcion_corta,
           c.nombre AS categoria_nombre, c.slug AS categoria_slug
    FROM wishlist w
    JOIN productos p ON p.id = w.producto_id
    LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE w.cliente_id = ?
    ORDER BY w.fecha DESC
");
$stmt->execute([cliente_id()]);
$wishlist_items = $stmt->fetchAll();
$wishlist_count = count($wishlist_items);

$item_count = cart_count();
$flash_success = flash('success');
$flash_error   = flash('error');

$page_title = 'Mi Lista de Deseos';
include __DIR__ . '/includes/header.php';
?>

<!-- ============================================================
     BREADCRUMBS
     ============================================================ -->
<div class="container" style="padding-top:80px;">
    <nav class="breadcrumbs">
        <a href="<?= SITE_URL ?>/">Inicio</a>
        <span class="sep">/</span>
        <span class="current">Mi Lista de Deseos</span>
    </nav>
</div>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<main class="container page-enter">
    <div class="account-layout">

        <!-- ── SIDEBAR ────────────────────────────────────────── -->
        <aside class="account-sidebar">
            <nav class="account-nav">
                <a href="<?= url_pagina('mi-cuenta') ?>" class="account-nav__item">
                    <span style="font-size:1.1rem;">&#128100;</span>
                    Mi Cuenta
                </a>
                <a href="<?= url_pagina('mis-pedidos') ?>" class="account-nav__item">
                    <span style="font-size:1.1rem;">&#128230;</span>
                    Mis Pedidos
                </a>
                <a href="<?= url_pagina('wishlist') ?>" class="account-nav__item active">
                    <span style="font-size:1.1rem;">&#9829;</span>
                    Wishlist
                </a>
                <a href="<?= SITE_URL ?>/logout" class="account-nav__item" style="color:var(--danger);">
                    <span style="font-size:1.1rem;">&#10140;</span>
                    Cerrar sesión
                </a>
            </nav>
        </aside>

        <!-- ── CONTENT ────────────────────────────────────────── -->
        <div class="account-content">

            <!-- Flash messages -->
            <?php if ($flash_success): ?>
                <div style="padding:12px 16px;margin-bottom:20px;border-radius:8px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;font-size:.88rem;">
                    <?= sanitize($flash_success) ?>
                </div>
            <?php endif; ?>
            <?php if ($flash_error): ?>
                <div style="padding:12px 16px;margin-bottom:20px;border-radius:8px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;font-size:.88rem;">
                    <?= sanitize($flash_error) ?>
                </div>
            <?php endif; ?>

            <h1 style="font-size:1.6rem;margin-bottom:8px;">Mi Lista de Deseos</h1>
            <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:32px;">
                <?= $wishlist_count ?> <?= $wishlist_count === 1 ? 'producto' : 'productos' ?>
            </p>

            <?php if ($wishlist_count === 0): ?>
            <!-- ── EMPTY STATE ────────────────────────────────── -->
            <div style="text-align:center;padding:80px 20px;">
                <div style="font-size:4rem;margin-bottom:20px;opacity:.4;">&#9825;</div>
                <p style="font-size:1.15rem;color:var(--text-muted);margin-bottom:8px;">Tu lista está vacía</p>
                <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:24px;">Explorá el catálogo y agregá productos que te gusten.</p>
                <a href="<?= SITE_URL ?>/" class="btn-add-cart" style="display:inline-block;padding:12px 32px;">
                    Explorar catálogo
                </a>
            </div>

            <?php else: ?>
            <!-- ── WISHLIST GRID ──────────────────────────────── -->
            <div class="products-grid wishlist-grid" id="wishlistGrid">
                <?php foreach ($wishlist_items as $item):
                    $tiene_oferta = $item['precio_oferta'] !== null && (float) $item['precio_oferta'] > 0;
                    $precio_final = $tiene_oferta ? (float) $item['precio_oferta'] : (float) $item['precio'];
                    $agotado      = (int) $item['stock'] === 0 || $item['estado'] !== 'activo';
                    $imagen       = img_url($item['imagen_principal'] ?? '');
                ?>
                <div class="product-card" data-wishlist-item="<?= (int) $item['producto_id'] ?>">
                    <div class="product-card__image">
                        <?php if ($imagen): ?>
                            <img src="<?= sanitize($imagen) ?>" alt="<?= sanitize($item['nombre']) ?>" loading="lazy">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--text-muted);font-size:.8rem;">Sin imagen</div>
                        <?php endif; ?>

                        <!-- Badges -->
                        <?php if ($agotado): ?>
                            <span class="product-badge product-badge--agotado">Agotado</span>
                        <?php elseif ($tiene_oferta): ?>
                            <span class="product-badge product-badge--oferta">Oferta</span>
                        <?php endif; ?>
                    </div>

                    <div class="product-card__body">
                        <!-- Category tag -->
                        <?php if (!empty($item['categoria_nombre'])): ?>
                            <a href="<?= url_categoria($item['categoria_slug']) ?>"
                               style="display:inline-block;font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--accent-light);margin-bottom:6px;">
                                <?= sanitize($item['categoria_nombre']) ?>
                            </a>
                        <?php endif; ?>

                        <!-- Product name -->
                        <h3 class="product-card__name">
                            <a href="<?= url_producto($item['slug']) ?>" style="color:inherit;">
                                <?= sanitize($item['nombre']) ?>
                            </a>
                        </h3>

                        <!-- Price -->
                        <div class="product-card__price">
                            <span class="price--current"><?= price($precio_final) ?></span>
                            <?php if ($tiene_oferta): ?>
                                <span class="price--old"><?= price((float) $item['precio']) ?></span>
                                <?php $desc_pct = round((1 - $precio_final / (float) $item['precio']) * 100); ?>
                                <span class="price--discount">-<?= $desc_pct ?>%</span>
                            <?php endif; ?>
                        </div>

                        <!-- Action buttons -->
                        <div style="display:flex;gap:8px;margin-top:auto;">
                            <!-- Add to cart (AJAX + form fallback) -->
                            <form method="POST" action="<?= url_pagina('wishlist') ?>" class="wishlist-cart-form" style="flex:1;">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="producto_id" value="<?= (int) $item['producto_id'] ?>">
                                <button type="submit"
                                        class="btn-add-cart wishlist-add-cart-btn"
                                        data-id="<?= (int) $item['producto_id'] ?>"
                                        style="width:100%;padding:10px 12px;font-size:.82rem;"
                                        <?= $agotado ? 'disabled' : '' ?>>
                                    <?= $agotado ? 'Sin stock' : 'Agregar al carrito' ?>
                                </button>
                            </form>

                            <!-- Remove from wishlist (AJAX + form fallback) -->
                            <form method="POST" action="<?= url_pagina('wishlist') ?>" class="wishlist-remove-form">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="producto_id" value="<?= (int) $item['producto_id'] ?>">
                                <button type="submit"
                                        class="wishlist-remove-btn"
                                        data-id="<?= (int) $item['producto_id'] ?>"
                                        title="Eliminar de favoritos"
                                        style="padding:10px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:8px;color:#f87171;cursor:pointer;font-size:.95rem;line-height:1;transition:background .2s;">
                                    &#128465;
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div><!-- end .account-content -->
    </div><!-- end .account-layout -->
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
(function(){
    const csrf = '<?= csrf_token() ?>';

    // ── AJAX: Remove from wishlist ───────────────────────────
    document.querySelectorAll('.wishlist-remove-form').forEach(form => {
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const btn = form.querySelector('.wishlist-remove-btn');
            const productoId = btn.dataset.id;
            const card = document.querySelector('[data-wishlist-item="'+productoId+'"]');

            const fd = new FormData();
            fd.append('action', 'remove');
            fd.append('producto_id', productoId);
            fd.append('csrf', csrf);

            fetch(window.SITE_URL + '/wishlist', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok && card) {
                    card.style.transition = 'opacity .3s, transform .3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(.95)';
                    setTimeout(() => {
                        card.remove();
                        updateCount();
                    }, 300);
                }
            })
            .catch(() => form.submit());
        });
    });

    // ── AJAX: Add to cart from wishlist ──────────────────────
    document.querySelectorAll('.wishlist-cart-form').forEach(form => {
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const btn = form.querySelector('.wishlist-add-cart-btn');
            if (btn.disabled) return;

            const productoId = btn.dataset.id;
            const card = document.querySelector('[data-wishlist-item="'+productoId+'"]');

            const fd = new FormData();
            fd.append('action', 'add_to_cart');
            fd.append('producto_id', productoId);
            fd.append('csrf', csrf);

            btn.textContent = 'Agregando…';
            btn.disabled = true;

            fetch(window.SITE_URL + '/wishlist', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    // Update cart badge
                    const badge = document.getElementById('cartBadge');
                    if (badge && data.cart_count !== undefined) {
                        badge.textContent = data.cart_count;
                        badge.style.display = data.cart_count > 0 ? '' : 'none';
                    }
                    // Remove card with animation
                    if (card) {
                        card.style.transition = 'opacity .3s, transform .3s';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(.95)';
                        setTimeout(() => {
                            card.remove();
                            updateCount();
                        }, 300);
                    }
                } else {
                    btn.textContent = 'Error';
                    setTimeout(() => {
                        btn.textContent = 'Agregar al carrito';
                        btn.disabled = false;
                    }, 2000);
                }
            })
            .catch(() => form.submit());
        });
    });

    // ── Update item count after removals ─────────────────────
    function updateCount() {
        const grid = document.getElementById('wishlistGrid');
        const remaining = grid ? grid.querySelectorAll('.product-card').length : 0;
        const countEl = document.querySelector('.account-content > p');
        if (countEl) {
            countEl.textContent = remaining + (remaining === 1 ? ' producto' : ' productos');
        }
        // Show empty state if no items left
        if (remaining === 0 && grid) {
            grid.outerHTML = '<div style="text-align:center;padding:80px 20px;">' +
                '<div style="font-size:4rem;margin-bottom:20px;opacity:.4;">&#9825;</div>' +
                '<p style="font-size:1.15rem;color:var(--text-muted);margin-bottom:8px;">Tu lista está vacía</p>' +
                '<p style="font-size:.88rem;color:var(--text-muted);margin-bottom:24px;">Explorá el catálogo y agregá productos que te gusten.</p>' +
                '<a href="' + window.SITE_URL + '/" class="btn-add-cart" style="display:inline-block;padding:12px 32px;">Explorar catálogo</a></div>';
        }
    }
})();
</script>
