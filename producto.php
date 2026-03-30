<?php
require_once __DIR__ . '/config.php';

// ── Get product by slug or id ──────────────────────────────
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$id   = isset($_GET['id'])   ? (int) $_GET['id']  : 0;

if ($slug !== '') {
    $stmt = pdo()->prepare("
        SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.slug = ?
        LIMIT 1
    ");
    $stmt->execute([$slug]);
} elseif ($id > 0) {
    $stmt = pdo()->prepare("
        SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
} else {
    redirect('index.php');
}

$product = $stmt->fetch();
if (!$product || $product['estado'] !== 'activo') {
    redirect('index.php');
}

$pid = (int) $product['id'];

// ── Additional images ──────────────────────────────────────
$stmtImg = pdo()->prepare("SELECT imagen, orden FROM producto_imagenes WHERE producto_id = ? ORDER BY orden ASC");
$stmtImg->execute([$pid]);
$extra_images = $stmtImg->fetchAll();

// Build full gallery: principal first, then extras
$gallery = [];
if ($product['imagen_principal']) {
    $gallery[] = img_url($product['imagen_principal']);
}
foreach ($extra_images as $img) {
    $gallery[] = img_url($img['imagen']);
}
if (empty($gallery)) {
    $gallery[] = img_url('');
}

// ── Variants ───────────────────────────────────────────────
$stmtVar = pdo()->prepare("SELECT id, nombre, valor, stock_extra, precio_extra FROM producto_variantes WHERE producto_id = ? ORDER BY nombre, valor");
$stmtVar->execute([$pid]);
$variants = $stmtVar->fetchAll();

// Group variants by name (e.g. "Color", "Talle")
$variant_groups = [];
foreach ($variants as $v) {
    $variant_groups[$v['nombre']][] = $v;
}

// ── Reviews (approved) ─────────────────────────────────────
$stmtRev = pdo()->prepare("SELECT nombre, rating, comentario, fecha FROM resenas WHERE producto_id = ? AND aprobada = 1 ORDER BY fecha DESC");
$stmtRev->execute([$pid]);
$reviews = $stmtRev->fetchAll();

$review_count = count($reviews);
$avg_rating   = 0;
if ($review_count > 0) {
    $avg_rating = round(array_sum(array_column($reviews, 'rating')) / $review_count, 1);
}

// ── Related products (same category, exclude current) ──────
$stmtRel = pdo()->prepare("
    SELECT id, nombre, slug, precio, precio_oferta, imagen_principal, stock, rating_promedio
    FROM productos
    WHERE categoria_id = ? AND id != ? AND estado = 'activo'
    ORDER BY RAND()
    LIMIT 4
");
$stmtRel->execute([$product['categoria_id'], $pid]);
$related = $stmtRel->fetchAll();

// ── Price logic ────────────────────────────────────────────
$has_offer    = $product['precio_oferta'] > 0 && $product['precio_oferta'] < $product['precio'];
$display_price = $has_offer ? $product['precio_oferta'] : $product['precio'];
$stock         = (int) $product['stock'];

// ── Check wishlist ────────────────────────────────────────
$in_wishlist = false;
if (is_cliente()) {
    $wStmt = pdo()->prepare("SELECT id FROM wishlist WHERE cliente_id = ? AND producto_id = ?");
    $wStmt->execute([cliente_id(), $pid]);
    $in_wishlist = (bool) $wStmt->fetch();
}

// ── Page meta ──────────────────────────────────────────────
$seo_title = $product['meta_titulo'] ?: $product['nombre'];
$seo_desc  = $product['meta_descripcion'] ?: $product['descripcion_corta'] ?: '';
$seo_image = img_url($product['imagen_principal'] ?? '');
$seo_type  = 'product';
$page_title = $seo_title;
include __DIR__ . '/includes/header.php';
?>

  <!-- ═══════════ BREADCRUMBS ═══════════ -->
  <div class="container" style="padding-top:80px;">
    <nav class="breadcrumbs">
      <a href="<?= SITE_URL ?>/">Inicio</a>
      <span class="sep">/</span>
      <a href="<?= url_pagina('tienda') ?>">Tienda</a>
      <span class="sep">/</span>
      <?php if ($product['categoria_nombre']): ?>
        <a href="<?= url_categoria($product['categoria_slug']) ?>"><?= sanitize($product['categoria_nombre']) ?></a>
        <span class="sep">/</span>
      <?php endif; ?>
      <span class="current"><?= sanitize($product['nombre']) ?></span>
    </nav>
  </div>

  <!-- ═══════════ PRODUCT DETAIL ═══════════ -->
  <section class="container page-enter">
    <div class="product-detail">

      <!-- LEFT: Gallery -->
      <div class="product-gallery">
        <div class="product-gallery__main product-img-zoom">
          <img src="<?= sanitize($gallery[0]) ?>" alt="<?= sanitize($product['nombre']) ?>" class="product-main-img">
        </div>
        <?php if (count($gallery) > 1): ?>
          <div class="product-gallery__thumbs">
            <?php foreach ($gallery as $i => $img): ?>
              <img
                src="<?= sanitize($img) ?>"
                alt="<?= sanitize($product['nombre']) ?> - imagen <?= $i + 1 ?>"
                class="product-thumb <?= $i === 0 ? 'active' : '' ?>"
                data-src="<?= sanitize($img) ?>"
              >
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- RIGHT: Product Info -->
      <div class="product-info">
        <?php if ($product['categoria_nombre']): ?>
          <span style="display:inline-block;padding:4px 12px;border-radius:6px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:rgba(124,58,237,.15);color:var(--accent-light);">
            <?= sanitize($product['categoria_nombre']) ?>
          </span>
        <?php endif; ?>

        <h1 class="product-info__title"><?= sanitize($product['nombre']) ?></h1>

        <!-- Star rating -->
        <div style="display:flex;align-items:center;gap:10px;">
          <div class="star-rating">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <?php if ($s <= round($avg_rating)): ?>
                <span>&#9733;</span>
              <?php else: ?>
                <span class="star--empty">&#9733;</span>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
          <span style="font-size:.82rem;color:var(--text-muted);">
            <?= $avg_rating ?> (<?= $review_count ?> <?= $review_count === 1 ? 'reseña' : 'reseñas' ?>)
          </span>
        </div>

        <!-- Price -->
        <div class="product-info__price">
          <span class="price--current product-price" data-base="<?= $display_price ?>">
            <?= price($display_price) ?>
          </span>
          <?php if ($has_offer): ?>
            <span class="price--old"><?= price($product['precio']) ?></span>
            <?php
              $discount_pct = round((1 - $product['precio_oferta'] / $product['precio']) * 100);
            ?>
            <span class="price--discount">-<?= $discount_pct ?>%</span>
          <?php endif; ?>
        </div>

        <!-- Stock indicator -->
        <?php if ($stock <= 0): ?>
          <div class="stock-indicator stock-indicator--out">
            <span class="stock-indicator__dot"></span>
            <span>Agotado</span>
          </div>
        <?php elseif ($stock <= STOCK_MINIMO_ALERTA): ?>
          <div class="stock-indicator stock-indicator--low">
            <span class="stock-indicator__dot"></span>
            <span>Últimas <?= $stock ?> unidades</span>
          </div>
        <?php else: ?>
          <div class="stock-indicator stock-indicator--in">
            <span class="stock-indicator__dot"></span>
            <span>En stock</span>
          </div>
        <?php endif; ?>

        <?php if ($product['descripcion_corta']): ?>
          <p style="font-size:.9rem;color:var(--text-muted);line-height:1.7;"><?= sanitize($product['descripcion_corta']) ?></p>
        <?php endif; ?>

        <!-- Variant selectors -->
        <?php foreach ($variant_groups as $group_name => $options): ?>
          <div class="variant-group">
            <label style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:8px;display:block;">
              <?= sanitize($group_name) ?>
            </label>
            <div class="variant-selector">
              <?php foreach ($options as $opt): ?>
                <button
                  type="button"
                  class="variant-btn variant-option"
                  data-value="<?= sanitize($opt['valor']) ?>"
                  data-precio-extra="<?= (float) $opt['precio_extra'] ?>"
                >
                  <?= sanitize($opt['valor']) ?>
                  <?php if ($opt['precio_extra'] > 0): ?>
                    <span style="font-size:.7rem;color:var(--text-muted);">(+<?= price($opt['precio_extra']) ?>)</span>
                  <?php endif; ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Quantity + Add to cart -->
        <div class="product-actions" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
          <div class="qty-input product-qty">
            <button type="button" class="qty-minus">&minus;</button>
            <input type="number" value="1" min="1" max="<?= max($stock, 1) ?>" class="qty-input-field" name="qty">
            <button type="button" class="qty-plus">+</button>
          </div>

          <button
            class="btn-add-cart"
            data-producto-id="<?= $pid ?>"
            style="flex:1;min-width:200px;"
            <?= $stock <= 0 ? 'disabled' : '' ?>
          >
            <?= $stock <= 0 ? 'Agotado' : 'Agregar al carrito' ?>
          </button>
        </div>

        <!-- Wishlist -->
        <button class="wishlist-btn-single<?= $in_wishlist ? ' active' : '' ?>" data-producto-id="<?= $pid ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
          <span class="wishlist-btn-single__text"><?= $in_wishlist ? 'En favoritos' : 'Agregar a favoritos' ?></span>
        </button>
      </div>
    </div>
  </section>

  <!-- ═══════════ PRODUCT TABS ═══════════ -->
  <section class="container">
    <div class="product-tabs">
      <div class="product-tabs__nav">
        <button class="tab-btn active" data-tab="descripcion">Descripción</button>
        <button class="tab-btn" data-tab="resenas">Reseñas (<?= $review_count ?>)</button>
      </div>

      <!-- Tab: Descripción -->
      <div class="tab-panel active" data-tab="descripcion">
        <div style="font-size:.9rem;line-height:1.8;color:var(--text);">
          <?= $product['descripcion'] ?? '<p>Sin descripción disponible.</p>' ?>
        </div>
      </div>

      <!-- Tab: Reseñas -->
      <div class="tab-panel" data-tab="resenas">
        <?php if ($review_count > 0): ?>
          <div style="margin-bottom:32px;">
            <?php foreach ($reviews as $rev): ?>
              <div class="review-card">
                <div class="review-card__header">
                  <div class="review-card__avatar">
                    <?= mb_strtoupper(mb_substr($rev['nombre'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                  </div>
                  <div>
                    <div class="review-card__author"><?= sanitize($rev['nombre']) ?></div>
                    <div class="review-card__date"><?= date('d/m/Y', strtotime($rev['fecha'])) ?></div>
                  </div>
                  <div class="star-rating" style="margin-left:auto;">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <span class="<?= $s > $rev['rating'] ? 'star--empty' : '' ?>">&#9733;</span>
                    <?php endfor; ?>
                  </div>
                </div>
                <p class="review-card__text"><?= sanitize($rev['comentario']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p style="color:var(--text-muted);margin-bottom:24px;">Todavía no hay reseñas. ¡Sé el primero!</p>
        <?php endif; ?>

        <!-- Review form -->
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;">
          <h3 style="font-size:1rem;margin-bottom:18px;">Dejá tu reseña</h3>
          <form class="review-form" method="POST">
            <input type="hidden" name="producto_id" value="<?= $pid ?>">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="rating" value="0">

            <?php if (!is_cliente()): ?>
              <div class="form-row" style="margin-bottom:14px;">
                <div class="form-group" style="margin-bottom:0;">
                  <label>Nombre</label>
                  <input type="text" name="nombre" class="form-input" required placeholder="Tu nombre">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label>Email</label>
                  <input type="email" name="email" class="form-input" required placeholder="tu@email.com">
                </div>
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label>Calificación</label>
              <div class="star-input review-stars">
                <span class="star" data-value="5">&#9733;</span>
                <span class="star" data-value="4">&#9733;</span>
                <span class="star" data-value="3">&#9733;</span>
                <span class="star" data-value="2">&#9733;</span>
                <span class="star" data-value="1">&#9733;</span>
              </div>
            </div>

            <div class="form-group">
              <label>Comentario</label>
              <textarea name="comentario" class="form-input" rows="4" required placeholder="Contanos tu experiencia..."></textarea>
            </div>

            <button type="submit" class="btn-add-cart" style="width:auto;padding:10px 28px;">
              Enviar reseña
            </button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════ RELATED PRODUCTS ═══════════ -->
  <?php if (!empty($related)): ?>
  <section class="container" style="padding-bottom:40px;">
    <h2 style="font-size:1.35rem;margin-bottom:24px;">Productos relacionados</h2>
    <div class="products-grid" style="grid-template-columns:repeat(4,1fr);">
      <?php foreach ($related as $rel): ?>
        <?php
          $rel_has_offer = $rel['precio_oferta'] > 0 && $rel['precio_oferta'] < $rel['precio'];
          $rel_price     = $rel_has_offer ? $rel['precio_oferta'] : $rel['precio'];
        ?>
        <a href="<?= url_producto($rel['slug']) ?>" class="product-card" style="text-decoration:none;color:inherit;">
          <div class="product-card__image">
            <?php if ($rel_has_offer): ?>
              <span class="product-badge product-badge--oferta">Oferta</span>
            <?php endif; ?>
            <?php if ((int) $rel['stock'] <= 0): ?>
              <span class="product-badge product-badge--agotado">Agotado</span>
            <?php endif; ?>
            <img src="<?= img_url($rel['imagen_principal'] ?? '') ?>" alt="<?= sanitize($rel['nombre']) ?>">
          </div>
          <div class="product-card__body">
            <h3 class="product-card__name"><?= sanitize($rel['nombre']) ?></h3>
            <div class="product-card__price">
              <span class="price--current"><?= price($rel_price) ?></span>
              <?php if ($rel_has_offer): ?>
                <span class="price--old"><?= price($rel['precio']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ((float) $rel['rating_promedio'] > 0): ?>
              <div class="star-rating" style="font-size:.75rem;">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <span class="<?= $s > round($rel['rating_promedio']) ? 'star--empty' : '' ?>">&#9733;</span>
                <?php endfor; ?>
              </div>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
