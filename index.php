<?php
require_once __DIR__ . '/config.php';

$db = pdo();

// Slides
try {
    $slides = $db->query("SELECT * FROM slides WHERE activo = 1 ORDER BY orden ASC")->fetchAll();
} catch (Exception $e) {
    $slides = [];
}

$categorias = $db->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY orden ASC LIMIT 6")->fetchAll();

$destacados = $db->query("
    SELECT p.*, c.nombre as cat_nombre, c.slug as cat_slug
    FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.estado = 'activo' AND p.destacado = 1
    ORDER BY p.fecha_creacion DESC LIMIT 8
")->fetchAll();

$vendidos = $db->query("
    SELECT p.*, c.nombre as cat_nombre, c.slug as cat_slug
    FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
    WHERE p.estado = 'activo' AND p.total_ventas > 0
    ORDER BY p.total_ventas DESC LIMIT 8
")->fetchAll();

if (empty($destacados) && empty($vendidos)) {
    $recientes = $db->query("
        SELECT p.*, c.nombre as cat_nombre, c.slug as cat_slug
        FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
        WHERE p.estado = 'activo'
        ORDER BY p.fecha_creacion DESC LIMIT 8
    ")->fetchAll();
}

$nl_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    $nl_email = filter_var(trim($_POST['newsletter_email']), FILTER_SANITIZE_EMAIL);
    if (filter_var($nl_email, FILTER_VALIDATE_EMAIL)) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS suscriptores (
                id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(100) UNIQUE, fecha DATETIME DEFAULT NOW()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $db->prepare("INSERT IGNORE INTO suscriptores (email) VALUES (?)");
            $stmt->execute([$nl_email]);
            $nl_msg = $stmt->rowCount() ? 'ok' : 'exists';
        } catch (PDOException $e) { $nl_msg = 'error'; }
    } else { $nl_msg = 'invalid'; }
}

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

$page_title = 'Tienda Online';
$seo_title = 'Inicio';
$seo_desc = 'Descubrí productos premium seleccionados con el mejor diseño y calidad.';
include __DIR__ . '/includes/header.php';
?>

<!-- HERO SLIDER -->
<section class="hero-slider" id="heroSlider">
  <?php if (!empty($slides)): ?>
    <?php foreach ($slides as $i => $slide): ?>
    <div class="slide <?= $i === 0 ? 'active' : '' ?>"
         style="background-image: url('<?= img_url($slide['imagen'], 'slides') ?>')">
      <div class="slide-overlay"
           style="background: rgba(0,0,0,<?= $slide['overlay_opacidad'] / 100 ?>)"></div>
      <?php if ($slide['titulo'] || $slide['btn1_texto']): ?>
      <div class="slide-content slide-content--<?= $slide['texto_posicion'] ?> slide-content--<?= $slide['texto_color'] ?>">
        <?php if ($slide['subtitulo']): ?>
          <span class="slide-subtitulo"><?= sanitize($slide['subtitulo']) ?></span>
        <?php endif; ?>
        <?php if ($slide['titulo']): ?>
          <h1 class="slide-titulo"><?= nl2br(sanitize($slide['titulo'])) ?></h1>
        <?php endif; ?>
        <?php if ($slide['descripcion']): ?>
          <p class="slide-descripcion"><?= sanitize($slide['descripcion']) ?></p>
        <?php endif; ?>
        <?php if ($slide['btn1_texto'] || $slide['btn2_texto']): ?>
        <div class="slide-btns">
          <?php if ($slide['btn1_texto']): ?>
            <a href="<?= sanitize($slide['btn1_url'] ?: '#') ?>" class="slide-btn slide-btn--<?= $slide['btn1_estilo'] ?>"><?= sanitize($slide['btn1_texto']) ?></a>
          <?php endif; ?>
          <?php if ($slide['btn2_texto']): ?>
            <a href="<?= sanitize($slide['btn2_url'] ?: '#') ?>" class="slide-btn slide-btn--<?= $slide['btn2_estilo'] ?>"><?= sanitize($slide['btn2_texto']) ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (count($slides) > 1): ?>
    <button class="slider-btn slider-btn--prev" id="sliderPrev">&#8249;</button>
    <button class="slider-btn slider-btn--next" id="sliderNext">&#8250;</button>
    <div class="slider-dots" id="sliderDots">
      <?php foreach ($slides as $i => $s): ?>
        <button class="slider-dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="slide active" style="background: linear-gradient(135deg, #111 0%, #333 100%)">
      <div class="slide-overlay" style="background:rgba(0,0,0,0.3)"></div>
      <div class="slide-content slide-content--centro slide-content--blanco">
        <span class="slide-subtitulo">BIENVENIDO</span>
        <h1 class="slide-titulo">Tu tienda online</h1>
        <div class="slide-btns">
          <a href="<?= url_pagina('tienda') ?>" class="slide-btn slide-btn--solido">Ver productos</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>

<!-- FEATURES BAR -->
<div class="features-bar">
  <div class="container">
    <div class="features-grid">
      <div class="feature-item">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        <h4>Envio seguro</h4>
        <p>A todo el pais con seguimiento</p>
      </div>
      <div class="feature-item">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        <h4>Pago protegido</h4>
        <p>Transacciones 100% seguras</p>
      </div>
      <div class="feature-item">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        <h4>Soporte</h4>
        <p>Te ayudamos cuando lo necesites</p>
      </div>
      <div class="feature-item">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <h4>Ofertas exclusivas</h4>
        <p>Suscribite y enterate primero</p>
      </div>
    </div>
  </div>
</div>

<!-- CATEGORIAS -->
<section class="section" id="categorias">
  <div class="container">
    <h2 class="section__title">Categorias</h2>
    <p class="section__subtitle">Explora nuestra seleccion curada de productos por categoria.</p>
    <?php if (!empty($categorias)): ?>
    <div class="cat-grid">
      <?php foreach ($categorias as $i => $cat): ?>
      <a href="<?= url_categoria($cat['slug']) ?>" class="cat-card fade-in">
        <?php if ($cat['imagen']): ?>
          <img src="<?= img_url($cat['imagen']) ?>" alt="<?= sanitize($cat['nombre']) ?>" loading="lazy">
        <?php endif; ?>
        <div class="cat-card__overlay">
          <h3><?= sanitize($cat['nombre']) ?></h3>
          <span class="cat-card__cta">Ver mas</span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="cat-grid">
      <div class="cat-card cat-card--placeholder fade-in"><div class="cat-card__overlay"><h3>Proximamente</h3></div></div>
      <div class="cat-card cat-card--placeholder fade-in"><div class="cat-card__overlay"><h3>Proximamente</h3></div></div>
      <div class="cat-card cat-card--placeholder fade-in"><div class="cat-card__overlay"><h3>Proximamente</h3></div></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- PRODUCTOS DESTACADOS -->
<section class="section-fw section-fw--white" id="productos">
  <h2 class="section-fw__title section-fw__title--line">Productos destacados</h2>
  <?php $list = !empty($destacados) ? $destacados : ($recientes ?? []); ?>
  <?php if (!empty($list)): ?>
  <div class="products-grid-fw">
    <?php foreach ($list as $p): ?>
      <?= render_product_card($p) ?>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="empty-text">Próximamente — Estamos preparando nuestros productos</p>
  <?php endif; ?>
</section>

<!-- BANNER -->
<section class="banner-full">
  <div class="container">
    <p class="banner-full__pre">No te lo pierdas</p>
    <h2 class="banner-full__title">Nuevos ingresos<br>cada semana</h2>
    <a href="#ofertas" class="btn btn--black">Ver novedades</a>
  </div>
</section>

<!-- MAS VENDIDOS / NUEVOS INGRESOS -->
<?php if (!empty($vendidos)): ?>
<section class="section-fw section-fw--alt" id="ofertas">
  <h2 class="section-fw__title section-fw__title--line">Los más vendidos</h2>
  <div class="products-grid-fw">
    <?php foreach ($vendidos as $p): ?>
      <?= render_product_card($p) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- NEWSLETTER -->
<section class="newsletter">
  <div class="container">
    <h2 class="newsletter__title">Suscribite al newsletter</h2>
    <p class="newsletter__sub">Recibi ofertas exclusivas y lanzamientos antes que nadie.</p>
    <form class="newsletter__form" method="POST" action="index.php#nl">
      <div class="newsletter__input-row" id="nl">
        <input type="email" name="newsletter_email" placeholder="Tu email" required>
        <button type="submit" class="btn btn--black">Suscribirse</button>
      </div>
      <?php if ($nl_msg === 'ok'): ?>
        <p class="nl-msg nl-msg--ok">Suscripcion exitosa.</p>
      <?php elseif ($nl_msg === 'exists'): ?>
        <p class="nl-msg nl-msg--ok">Ya estas suscripto/a.</p>
      <?php elseif ($nl_msg): ?>
        <p class="nl-msg nl-msg--err">Error. Intenta de nuevo.</p>
      <?php endif; ?>
    </form>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
