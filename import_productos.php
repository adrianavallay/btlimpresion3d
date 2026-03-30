<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'mensaje' => 'Error al subir el archivo']);
    exit;
}

$mapeo = json_decode($_POST['mapeo'] ?? '{}', true);
if (empty($mapeo)) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se recibió mapeo de columnas']);
    exit;
}

$campos_validos = ['nombre','descripcion','descripcion_corta','precio','precio_oferta',
    'stock','stock_minimo','categoria','estado','destacado','slug',
    'meta_titulo','meta_descripcion'];

// Filter invalid mappings
$mapeo = array_filter($mapeo, fn($v) => in_array($v, $campos_validos));

if (empty($mapeo)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Ningún campo mapeado es válido']);
    exit;
}

$db = pdo();
$file = fopen($_FILES['csv_file']['tmp_name'], 'r');
$importados = 0;
$actualizados = 0;
$errores = 0;

// Skip header row
$header = fgetcsv($file);

// Preload categories for name->id mapping
$cats = $db->query("SELECT id, nombre FROM categorias")->fetchAll(PDO::FETCH_KEY_PAIR);
$cats_lower = [];
foreach ($cats as $id => $nombre) {
    $cats_lower[mb_strtolower($nombre, 'UTF-8')] = $id;
}

while (($row = fgetcsv($file)) !== false) {
    if (empty(array_filter($row))) continue; // skip empty rows

    $data = [];
    foreach ($mapeo as $col_index => $campo) {
        $val = isset($row[(int)$col_index]) ? trim($row[(int)$col_index]) : '';
        $data[$campo] = $val;
    }

    // Must have nombre
    if (empty($data['nombre'])) {
        $errores++;
        continue;
    }

    // Generate slug if not provided
    if (empty($data['slug'])) {
        $data['slug'] = slug($data['nombre']);
    }

    // Resolve categoria name to ID
    $categoria_id = null;
    if (isset($data['categoria']) && $data['categoria'] !== '') {
        $cat_lower = mb_strtolower($data['categoria'], 'UTF-8');
        if (isset($cats_lower[$cat_lower])) {
            $categoria_id = $cats_lower[$cat_lower];
        }
        unset($data['categoria']);
    }

    // Sanitize numeric fields
    if (isset($data['precio'])) $data['precio'] = (float) str_replace(',', '.', $data['precio']);
    if (isset($data['precio_oferta'])) {
        $val = str_replace(',', '.', $data['precio_oferta']);
        $data['precio_oferta'] = $val !== '' ? (float) $val : null;
    }
    if (isset($data['stock'])) $data['stock'] = (int) $data['stock'];
    if (isset($data['stock_minimo'])) $data['stock_minimo'] = (int) $data['stock_minimo'];
    if (isset($data['destacado'])) $data['destacado'] = in_array(strtolower($data['destacado']), ['1','si','sí','yes','true']) ? 1 : 0;
    if (isset($data['estado']) && !in_array($data['estado'], ['activo','borrador','agotado'])) {
        $data['estado'] = 'activo';
    }

    try {
        // Check if product exists by slug
        $stmt = $db->prepare("SELECT id FROM productos WHERE slug = ?");
        $stmt->execute([$data['slug']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $sets = [];
            $params = [];
            foreach ($data as $k => $v) {
                if ($k === 'slug') continue;
                $sets[] = "`{$k}` = ?";
                $params[] = $v;
            }
            if ($categoria_id !== null) {
                $sets[] = "categoria_id = ?";
                $params[] = $categoria_id;
            }
            $params[] = $existing['id'];
            $db->prepare("UPDATE productos SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
            $actualizados++;
        } else {
            // Insert
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($data), '?');
            $values = array_values($data);

            if ($categoria_id !== null) {
                $fields[] = 'categoria_id';
                $placeholders[] = '?';
                $values[] = $categoria_id;
            }

            $sql = "INSERT INTO productos (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $db->prepare($sql)->execute($values);
            $importados++;
        }
    } catch (PDOException $e) {
        $errores++;
    }
}

fclose($file);

echo json_encode([
    'ok' => true,
    'importados' => $importados,
    'actualizados' => $actualizados,
    'errores' => $errores,
    'mensaje' => "Proceso completado: {$importados} nuevos, {$actualizados} actualizados" . ($errores ? ", {$errores} errores" : "")
]);
