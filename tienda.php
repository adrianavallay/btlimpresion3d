<?php
require_once __DIR__ . '/config.php';

$db = pdo();

// Sorting
$sort = $_GET['sort'] ?? 'recientes';
$order_sql = match ($sort) {
    'precio_asc'  => 'p.precio ASC',
    'precio_desc' => 'p.precio DESC',
    'nombre'      => 'p.nombre ASC',
    'vendidos'    => 'p.total_ventas DESC',
    default       => 'p.fecha_creacion DESC',
};

// Category filter
$cat_slug = trim($_GET['cat'] ?? '');
$cat_filter = '';
$cat_params = [];
$cat_activa = null;
if ($cat_slug !== '') {
    $stmt = $db->prepare("SELECT * FROM categorias WHERE slug = ? AND activa = 1 LIMIT 1");
    $stmt->execute([$cat_slug]);
    $cat_activa = $stmt->fetch();
    if ($cat_activa) {
        $cat_filter = 'AND p.categoria_id = ?';
        $cat_params[] = (int) $cat_activa['id'];
    }
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$count_sql = "SELECT COUNT(*) FROM productos p WHERE p.estado = 'activo' $cat_filter";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($cat_params);
$total_items = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_items / ITEMS_PER_PAGE));
$page = min($page, $total_pages);
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Products
$sql = "SELECT p.*, c.nombre as cat_nombre, c.slug as cat_slug
        FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE p.estado = 'activo' $cat_filter
        ORDER BY $order_sql
        LIMIT " . (int) ITEMS_PER_PAGE . " OFFSET " . (int) $offset;
$prod_stmt = $db->prepare($sql);
$prod_stmt->execute($cat_params);
$productos = $prod_stmt->fetchAll();

// Categories for sidebar filter
$categorias = $db->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY orden ASC")->fetchAll();

// Pre-load wishlist IDs for logged-in user
$_wishlist_ids = [];
if (is_cliente()) {
    try {
        $stmt = $db->prepare("SELECT producto_id FROM wishlist WHERE cliente_id = ?");
        $stmt->execute([cliente_id()]);
        $_wishlist_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $_wishlist_ids = []; }
}

// Pre-load extra images for all products
$_product_images = [];
try {
    $imgRows = $db->query("SELECT producto_id, imagen FROM producto_imagenes ORDER BY orden ASC")->fetchAll();
    foreach ($imgRows as $row) {
        $_product_images[(int)$row['producto_id']][] = img_url($row['imagen']);
    }
} catch (Exception $e) { $_product_images = []; }

// Product card renderer
function render_product_card(array $p): string {
    global $_wishlist_ids, $_product_images;
    $nombre = sanitize($p['nombre']);
    $precio = (float) $p['precio'];
    $oferta = $p['precio_oferta'] ? (float) $p['precio_oferta'] : 0;
    $stock = (int) $p['stock'];
    $es_nuevo = (strtotime($p['fecha_creacion']) > strtotime('-7 days'));
    $id = (int) $p['id'];
    $cat = isset($p['cat_nombre']) ? sanitize($p['cat_nombre']) : '';
    $in_wishlist = in_array($id, $_wishlist_ids);

    // Build gallery
    $gallery = [];
    if ($p['imagen_principal']) $gallery[] = img_url($p['imagen_principal']);
    if (isset($_product_images[$id])) $gallery = array_merge($gallery, $_product_images[$id]);
    $has_gallery = count($gallery) > 1;

    $badge = '';
    if ($stock === 0) $badge = '<span class="badge badge--agotado">Agotado</span>';
    elseif ($oferta > 0 && $oferta < $precio) $badge = '<span class="badge badge--oferta">Oferta</span>';
    elseif ($es_nuevo) $badge = '<span class="badge badge--nuevo">Nuevo</span>';

    $price_html = '';
    if ($oferta > 0 && $oferta < $precio) {
        $price_html = '<span class="price-old">' . price($precio) . '</span> <span class="price-sale">' . price($oferta) . '</span>';
    } else {
        $price_html = '<span class="price-now">' . price($precio) . '</span>';
    }

    $add_btn = $stock > 0
        ? '<button class="add-btn" onclick="event.preventDefault();dyp.addToCart(' . $id . ',1)">Agregar al carrito</button>'
        : '<button class="add-btn add-btn--disabled" disabled>Sin stock</button>';

    $wish_active = $in_wishlist ? ' active' : '';
    $wish_btn = '<button class="wishlist-btn' . $wish_active . '" data-producto-id="' . $id . '" onclick="event.preventDefault();event.stopPropagation();" title="Favoritos"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></button>';

    // Gallery images HTML
    $imgs_html = '';
    if ($has_gallery) {
        foreach ($gallery as $i => $src) {
            $active = $i === 0 ? ' active' : '';
            $imgs_html .= '<img src="' . $src . '" alt="' . $nombre . '" loading="lazy" class="card-slide' . $active . '" data-index="' . $i . '">';
        }
        $arrows = '<button class="card-arrow card-arrow--prev" onclick="event.preventDefault();event.stopPropagation();cardSlide(this,-1)">&#8249;</button>'
                . '<button class="card-arrow card-arrow--next" onclick="event.preventDefault();event.stopPropagation();cardSlide(this,1)">&#8250;</button>'
                . '<div class="card-dots">';
        for ($i = 0; $i < count($gallery); $i++) {
            $arrows .= '<span class="card-dot' . ($i === 0 ? ' active' : '') . '"></span>';
        }
        $arrows .= '</div>';
    } else {
        $img = !empty($gallery) ? $gallery[0] : img_url('');
        $imgs_html = $img ? '<img src="' . $img . '" alt="' . $nombre . '" loading="lazy" class="card-slide active">' : '<div class="product-card__placeholder"></div>';
        $arrows = '';
    }

    $slug_url = url_producto($p['slug'] ?? slug($nombre));
    return '
    <article class="product-card fade-in">
      <a href="' . $slug_url . '" class="product-card__link">
        <div class="product-card__img' . ($has_gallery ? ' has-gallery' : '') . '">
          ' . $imgs_html . '
          ' . ($badge ? '<div class="product-card__badge">' . $badge . '</div>' : '') . '
          <div class="product-card__wish">' . $wish_btn . '</div>
          ' . $arrows . '
          <div class="product-card__overlay">' . $add_btn . '</div>
        </div>
        <div class="product-card__info">
          ' . ($cat ? '<span class="product-card__cat">' . $cat . '</span>' : '') . '
          <h3 class="product-card__name">' . $nombre . '</h3>
          <div class="product-card__price">' . $price_html . '</div>
        </div>
      </a>
    </article>';
}

$page_title = $cat_activa ? sanitize($cat_activa['nombre']) : 'Tienda';
$seo_title = $page_title;
$seo_desc = $cat_activa
    ? 'Explorá productos de ' . sanitize($cat_activa['nombre']) . ' en ' . SITE_NAME
    : 'Explorá todos nuestros productos premium con envío a todo el país.';
include __DIR__ . '/includes/header.php';
?>

<!-- BREADCRUMB -->
<div style="margin-top: var(--navbar-height); background: var(--color-white); border-bottom: 1px solid var(--color-border);">
  <div class="container" style="padding-top: 16px; padding-bottom: 16px;">
    <nav style="font-size: 0.82rem; color: var(--color-text-light);">
      <a href="<?= SITE_URL ?>/" style="color: var(--color-primary);">Inicio</a>
      <span style="margin: 0 6px;">/</span>
      <?php if ($cat_activa): ?>
        <a href="<?= url_pagina('tienda') ?>" style="color: var(--color-primary);">Tienda</a>
        <span style="margin: 0 6px;">/</span>
        <span><?= sanitize($cat_activa['nombre']) ?></span>
      <?php else: ?>
        <span>Tienda</span>
      <?php endif; ?>
    </nav>
  </div>
</div>

<!-- PAGE HEADER -->
<section class="section" style="padding-bottom: 0; margin-top: 0;">
  <div class="container">
    <h1 class="section__title" style="margin-bottom: 8px;">
      <?= $cat_activa ? sanitize($cat_activa['nombre']) : 'Tienda' ?>
    </h1>
    <?php if ($cat_activa && $cat_activa['descripcion']): ?>
      <p class="section__subtitle"><?= sanitize($cat_activa['descripcion']) ?></p>
    <?php else: ?>
      <p class="section__subtitle">Explora todos nuestros productos.</p>
    <?php endif; ?>
  </div>
</section>

<!-- TOOLBAR -->
<section style="padding: 20px 0 10px;">
  <div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
      <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <a href="<?= url_pagina('tienda') ?>" class="btn <?= !$cat_activa ? 'btn--black' : 'btn--outline' ?> btn--sm">Todos</a>
        <?php foreach ($categorias as $cat): ?>
          <a href="<?= url_pagina('tienda') ?>?cat=<?= sanitize($cat['slug']) ?>"
             class="btn <?= ($cat_activa && $cat_activa['id'] == $cat['id']) ? 'btn--black' : 'btn--outline' ?> btn--sm">
            <?= sanitize($cat['nombre']) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 0.82rem; color: var(--color-text-light);">
          <?= $total_items ?> producto<?= $total_items !== 1 ? 's' : '' ?>
        </span>
        <select id="sortSelect"
                style="background: var(--color-white); border: 1px solid var(--color-border); border-radius: var(--radius); padding: 8px 12px; color: var(--color-text); font-size: 0.82rem; font-family: var(--font);">
          <option value="recientes" <?= $sort === 'recientes' ? 'selected' : '' ?>>Mas recientes</option>
          <option value="precio_asc" <?= $sort === 'precio_asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
          <option value="precio_desc" <?= $sort === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
          <option value="nombre" <?= $sort === 'nombre' ? 'selected' : '' ?>>Nombre A-Z</option>
          <option value="vendidos" <?= $sort === 'vendidos' ? 'selected' : '' ?>>Mas vendidos</option>
        </select>
      </div>
    </div>
  </div>
</section>

<!-- PRODUCTS -->
<section class="section" style="padding-top: 20px;">
  <div class="container">
    <?php if (empty($productos)): ?>
      <p class="empty-text">No hay productos<?= $cat_activa ? ' en esta categoria' : '' ?> por el momento.</p>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($productos as $p): ?>
          <?= render_product_card($p) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
      <nav style="display: flex; justify-content: center; gap: 6px; margin-top: 48px;">
        <?php
          $base_url = 'tienda.php?' . ($cat_slug ? 'cat=' . urlencode($cat_slug) . '&' : '') . 'sort=' . urlencode($sort);
        ?>
        <?php if ($page > 1): ?>
          <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="btn btn--outline btn--sm">&laquo; Anterior</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="<?= $base_url ?>&page=<?= $i ?>"
             class="btn <?= $i === $page ? 'btn--black' : 'btn--outline' ?> btn--sm"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
          <a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="btn btn--outline btn--sm">Siguiente &raquo;</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.getElementById('sortSelect')?.addEventListener('change', function () {
  const params = new URLSearchParams(window.location.search);
  params.set('sort', this.value);
  params.delete('page');
  window.location.search = params.toString();
});
</script>
