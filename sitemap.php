<?php
require_once __DIR__ . '/config.php';
$db = pdo();

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

<?php
// Static pages
$paginas = ['', 'tienda', 'contacto', 'login'];
foreach ($paginas as $p): ?>
<url>
  <loc><?= SITE_URL ?>/<?= $p ?></loc>
  <changefreq>weekly</changefreq>
  <priority><?= $p === '' ? '1.0' : '0.8' ?></priority>
</url>
<?php endforeach; ?>

<?php
// Products
$prods = $db->query("SELECT slug, fecha_modificacion FROM productos WHERE estado = 'activo' ORDER BY fecha_modificacion DESC")->fetchAll();
foreach ($prods as $p): ?>
<url>
  <loc><?= url_producto($p['slug']) ?></loc>
  <?php if ($p['fecha_modificacion']): ?>
  <lastmod><?= date('Y-m-d', strtotime($p['fecha_modificacion'])) ?></lastmod>
  <?php endif; ?>
  <changefreq>weekly</changefreq>
  <priority>0.9</priority>
</url>
<?php endforeach; ?>

<?php
// Categories
$cats = $db->query("SELECT slug FROM categorias WHERE activa = 1 ORDER BY orden ASC")->fetchAll();
foreach ($cats as $c): ?>
<url>
  <loc><?= url_categoria($c['slug']) ?></loc>
  <changefreq>weekly</changefreq>
  <priority>0.7</priority>
</url>
<?php endforeach; ?>

</urlset>
