<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

if (!is_admin()) { http_response_code(403); exit('No autorizado'); }

$campos_permitidos = ['id','nombre','descripcion','descripcion_corta',
  'precio','precio_oferta','stock','stock_minimo','estado','destacado',
  'imagen_principal','slug','meta_titulo','meta_descripcion',
  'total_ventas','fecha_creacion'];

$campos = array_filter($_POST['campos'] ?? [], fn($c) => in_array($c, $campos_permitidos));
$necesita_categoria = in_array('categoria', $_POST['campos'] ?? []);

if (empty($campos) && !$necesita_categoria) { die('Sin campos seleccionados'); }

$campos_sql = array_map(fn($c) => "p.`{$c}`", $campos);
if ($necesita_categoria) {
    $campos_sql[] = 'c.nombre as categoria';
    $campos[] = 'categoria';
}

$solo_activos = ($_POST['solo_activos'] ?? '0') === '1';
$where = $solo_activos ? "WHERE p.estado = 'activo'" : '';

$db = pdo();
$sql = "SELECT " . implode(', ', $campos_sql) . "
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        {$where}
        ORDER BY p.fecha_creacion DESC";

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="productos_' . date('Y-m-d_H-i') . '.csv"');
echo "\xEF\xBB\xBF"; // BOM for Excel

$out = fopen('php://output', 'w');

if (($_POST['con_encabezado'] ?? '1') === '1') {
    $labels = [
        'id'=>'ID','nombre'=>'Nombre','descripcion'=>'Descripción',
        'descripcion_corta'=>'Desc. corta','precio'=>'Precio',
        'precio_oferta'=>'Precio oferta','stock'=>'Stock',
        'stock_minimo'=>'Stock mínimo','categoria'=>'Categoría',
        'estado'=>'Estado','destacado'=>'Destacado',
        'imagen_principal'=>'Imagen','slug'=>'Slug',
        'meta_titulo'=>'Meta título','meta_descripcion'=>'Meta descripción',
        'total_ventas'=>'Total ventas','fecha_creacion'=>'Fecha creación',
    ];
    fputcsv($out, array_map(fn($c) => $labels[$c] ?? $c, $campos));
}

foreach ($rows as $row) { fputcsv($out, $row); }
fclose($out);
exit();
