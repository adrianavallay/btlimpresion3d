<?php
require_once __DIR__ . '/config.php';

$q    = trim($_GET['q'] ?? '');
$ajax = (int) ($_GET['ajax'] ?? 0);

// ---------------------------------------------------------------------------
// AJAX autocomplete — return JSON (max 5)
// ---------------------------------------------------------------------------
if ($ajax === 1) {
    header('Content-Type: application/json; charset=utf-8');

    if (mb_strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $like = '%' . $q . '%';
    $stmt = pdo()->prepare(
        "SELECT id, nombre, slug, imagen_principal, precio
         FROM productos
         WHERE estado = 'activo'
           AND (nombre LIKE ? OR descripcion LIKE ? OR descripcion_corta LIKE ?)
         ORDER BY total_ventas DESC
         LIMIT 5"
    );
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $r) {
        $results[] = [
            'id'     => (int) $r['id'],
            'nombre' => $r['nombre'],
            'slug'   => $r['slug'],
            'imagen' => img_url($r['imagen_principal'] ?? ''),
            'precio' => (float) $r['precio'],
            'url'    => url_producto($r['slug']),
        ];
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------
// Full search results page
// ---------------------------------------------------------------------------
$sort      = $_GET['sort'] ?? 'relevancia';
$page      = max(1, (int) ($_GET['page'] ?? 1));
$q_safe    = sanitize($q);

$productos   = [];
$total_items = 0;
$total_pages = 1;

if (mb_strlen($q) >= 1) {
    $like = '%' . $q . '%';

    // Count
    $count_stmt = pdo()->prepare(
        "SELECT COUNT(*) FROM productos
         WHERE estado = 'activo'
           AND (nombre LIKE ? OR descripcion LIKE ? OR descripcion_corta LIKE ?)"
    );
    $count_stmt->execute([$like, $like, $like]);
    $total_items = (int) $count_stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total_items / ITEMS_PER_PAGE));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * ITEMS_PER_PAGE;

    // Sort
    $order_sql = match ($sort) {
        'precio_asc'  => 'p.precio ASC',
        'precio_desc' => 'p.precio DESC',
        'nombre'      => 'p.nombre ASC',
        'vendidos'    => 'p.total_ventas DESC',
        default       => 'p.total_ventas DESC, p.fecha_creacion DESC',
    };

    $sql = "SELECT p.id, p.nombre, p.slug, p.descripcion_corta, p.precio, p.precio_oferta,
                   p.imagen_principal, p.stock, p.rating_promedio
            FROM productos p
            WHERE p.estado = 'activo'
              AND (p.nombre LIKE ? OR p.descripcion LIKE ? OR p.descripcion_corta LIKE ?)
            ORDER BY $order_sql
            LIMIT " . (int) ITEMS_PER_PAGE . " OFFSET " . (int) $offset;

    $prod_stmt = pdo()->prepare($sql);
    $prod_stmt->execute([$like, $like, $like]);
    $productos = $prod_stmt->fetchAll();
}

$cartCount = cart_count();

$page_title = 'Buscar: ' . $q_safe;
include __DIR__ . '/includes/header.php';
?>

<!-- ============================================================
     HERO / TITLE
     ============================================================ -->
<section class="hero-tienda">
  <div class="container">
    <nav style="margin-bottom:16px;font-size:.85rem;color:var(--text-muted)">
      <a href="<?= SITE_URL ?>/" style="color:var(--accent-light)">Inicio</a>
      <span style="margin:0 6px">/</span>
      <span style="color:var(--text)">B&uacute;squeda</span>
    </nav>

    <?php if ($q_safe !== ''): ?>
      <h1>Resultados para &lsquo;<?= $q_safe ?>&rsquo;</h1>
      <p style="color:var(--text-muted)">
        <?= $total_items ?> resultado<?= $total_items !== 1 ? 's' : '' ?> encontrado<?= $total_items !== 1 ? 's' : '' ?>
      </p>
    <?php else: ?>
      <h1>Buscar productos</h1>
      <p style="color:var(--text-muted)">Escrib&iacute; un t&eacute;rmino para buscar en nuestra tienda.</p>
    <?php endif; ?>
  </div>
</section>

<!-- ============================================================
     TOOLBAR — sort
     ============================================================ -->
<?php if ($total_items > 0): ?>
<section class="container" style="padding-top:20px;padding-bottom:10px">
  <div style="display:flex;justify-content:flex-end;align-items:center;gap:8px">
    <label for="sortSelect" style="font-size:.85rem;color:var(--text-muted)">Ordenar:</label>
    <select id="sortSelect" class="filter-sort"
            style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:6px 10px;color:var(--text);font-size:.85rem">
      <option value="relevancia"  <?= $sort === 'relevancia'  ? 'selected' : '' ?>>Relevancia</option>
      <option value="precio_asc"  <?= $sort === 'precio_asc'  ? 'selected' : '' ?>>Precio: menor a mayor</option>
      <option value="precio_desc" <?= $sort === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
      <option value="nombre"      <?= $sort === 'nombre'      ? 'selected' : '' ?>>Nombre A-Z</option>
      <option value="vendidos"    <?= $sort === 'vendidos'    ? 'selected' : '' ?>>M&aacute;s vendidos</option>
    </select>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     PRODUCTS GRID
     ============================================================ -->
<section class="container" style="padding-bottom:60px">
  <?php if ($q_safe !== '' && empty($productos)): ?>
    <div style="text-align:center;padding:80px 0;color:var(--text-muted)">
      <p style="font-size:1.2rem;margin-bottom:8px">No encontramos productos para &lsquo;<?= $q_safe ?>&rsquo;.</p>
      <p>Prob&aacute; con otros t&eacute;rminos o <a href="<?= SITE_URL ?>/" style="color:var(--accent-light)">explor&aacute; la tienda</a>.</p>
    </div>
  <?php elseif (!empty($productos)): ?>
    <div class="products-grid view-grid">
      <?php foreach ($productos as $prod): ?>
        <?php
          $p_name  = sanitize($prod['nombre']);
          $p_slug  = sanitize($prod['slug']);
          $p_img   = img_url($prod['imagen_principal'] ?? '');
          $p_price = (float) $prod['precio'];
          $p_offer = $prod['precio_oferta'] ? (float) $prod['precio_oferta'] : null;
          $p_desc  = sanitize($prod['descripcion_corta'] ?? '');
          $p_stock = (int) $prod['stock'];
        ?>
        <article class="product-card fade-in">
          <a href="<?= url_producto($p_slug) ?>" class="product-card__img-link">
            <img src="<?= $p_img ?>" alt="<?= $p_name ?>" loading="lazy">
            <?php if ($p_offer): ?>
              <span class="product-card__badge badge--sale">Oferta</span>
            <?php endif; ?>
            <?php if ($p_stock <= 0): ?>
              <span class="product-card__badge badge--soldout">Agotado</span>
            <?php endif; ?>
          </a>
          <div class="product-card__body">
            <h3><a href="<?= url_producto($p_slug) ?>"><?= $p_name ?></a></h3>
            <?php if ($p_desc): ?>
              <p class="product-card__desc"><?= $p_desc ?></p>
            <?php endif; ?>
            <div class="product-card__footer">
              <div class="product-card__price">
                <?php if ($p_offer): ?>
                  <span class="price--old"><?= price($p_price) ?></span>
                  <span class="price--current"><?= price($p_offer) ?></span>
                <?php else: ?>
                  <span class="price--current"><?= price($p_price) ?></span>
                <?php endif; ?>
              </div>
              <?php if ($p_stock > 0): ?>
                <button class="btn-add-cart" data-producto-id="<?= (int) $prod['id'] ?>" title="Agregar al carrito">
                  &#128722; Agregar
                </button>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ============================================================
       PAGINATION
       ============================================================ -->
  <?php if ($total_pages > 1): ?>
    <nav class="pagination" style="display:flex;justify-content:center;gap:6px;margin-top:40px">
      <?php
        $base_url = url_buscar($q) . '?sort=' . urlencode($sort);
      ?>
      <?php if ($page > 1): ?>
        <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="pagination__link">&laquo; Anterior</a>
      <?php endif; ?>

      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="pagination__link pagination__link--active"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= $base_url ?>&page=<?= $i ?>" class="pagination__link"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="pagination__link">Siguiente &raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
// Sort select — redirect on change preserving query
document.getElementById('sortSelect')?.addEventListener('change', function () {
  const params = new URLSearchParams(window.location.search);
  params.set('sort', this.value);
  params.delete('page');
  window.location.search = params.toString();
});
</script>
