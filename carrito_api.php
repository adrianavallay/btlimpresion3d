<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$cart = &$_SESSION['cart'];
if (!is_array($cart)) $cart = [];

switch ($action) {

    case 'add':
        $pid = (int) ($_POST['producto_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        $variante = sanitize($_POST['variante'] ?? '');

        $stmt = pdo()->prepare("SELECT id, nombre, precio, precio_oferta, stock, imagen_principal FROM productos WHERE id = ? AND estado = 'activo'");
        $stmt->execute([$pid]);
        $prod = $stmt->fetch();
        if (!$prod) json_response(['ok' => false, 'mensaje' => 'Producto no encontrado']);

        $key = $pid . ($variante ? "-$variante" : '');
        $current_qty = $cart[$key]['qty'] ?? 0;
        $new_qty = $current_qty + $qty;

        if ($new_qty > $prod['stock']) {
            json_response(['ok' => false, 'mensaje' => "Stock insuficiente (disponible: {$prod['stock']})"]);
        }

        $precio = $prod['precio_oferta'] > 0 ? $prod['precio_oferta'] : $prod['precio'];
        $cart[$key] = [
            'producto_id' => $pid,
            'nombre' => $prod['nombre'],
            'precio' => (float) $precio,
            'qty' => $new_qty,
            'imagen' => $prod['imagen_principal'],
            'variante' => $variante,
        ];
        json_response(['ok' => true, 'mensaje' => 'Agregado al carrito', 'cart_count' => cart_count()]);

    case 'update':
        $key = $_POST['key'] ?? '';
        $qty = max(0, (int) ($_POST['qty'] ?? 0));
        if (!isset($cart[$key])) json_response(['ok' => false, 'mensaje' => 'Item no encontrado']);
        if ($qty === 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = $qty;
        }
        json_response(['ok' => true, 'cart' => cart_summary()]);

    case 'remove':
        $key = $_POST['key'] ?? '';
        unset($cart[$key]);
        json_response(['ok' => true, 'cart' => cart_summary()]);

    case 'get':
        json_response(['ok' => true, 'cart' => cart_summary()]);

    case 'apply_coupon':
        $code = strtoupper(trim($_POST['codigo'] ?? ''));
        if ($code === '') json_response(['ok' => false, 'mensaje' => 'Ingresá un código']);

        $stmt = pdo()->prepare("SELECT * FROM cupones WHERE codigo = ? AND activo = 1");
        $stmt->execute([$code]);
        $cupon = $stmt->fetch();
        if (!$cupon) json_response(['ok' => false, 'mensaje' => 'Cupón inválido']);

        $today = date('Y-m-d');
        if ($cupon['fecha_inicio'] && $today < $cupon['fecha_inicio']) json_response(['ok' => false, 'mensaje' => 'Cupón aún no vigente']);
        if ($cupon['fecha_fin'] && $today > $cupon['fecha_fin']) json_response(['ok' => false, 'mensaje' => 'Cupón vencido']);
        if ($cupon['usos_maximos'] && $cupon['usos_actuales'] >= $cupon['usos_maximos']) json_response(['ok' => false, 'mensaje' => 'Cupón agotado']);

        $subtotal = array_sum(array_map(fn($i) => $i['precio'] * $i['qty'], $cart));
        if ($subtotal < $cupon['minimo_compra']) json_response(['ok' => false, 'mensaje' => 'Mínimo de compra: ' . price($cupon['minimo_compra'])]);

        $_SESSION['cupon'] = $cupon;
        json_response(['ok' => true, 'mensaje' => 'Cupón aplicado', 'cart' => cart_summary()]);

    case 'remove_coupon':
        unset($_SESSION['cupon']);
        json_response(['ok' => true, 'cart' => cart_summary()]);

    case 'count':
        json_response(['ok' => true, 'count' => cart_count()]);

    default:
        json_response(['ok' => false, 'mensaje' => 'Acción no válida'], 400);
}

function cart_summary(): array {
    $cart = $_SESSION['cart'] ?? [];
    $subtotal = 0;
    $items = [];
    foreach ($cart as $key => $item) {
        $line_total = $item['precio'] * $item['qty'];
        $subtotal += $line_total;
        $items[] = array_merge($item, ['key' => $key, 'line_total' => $line_total]);
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
    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'cupon' => $cupon ? $cupon['codigo'] : null,
        'total' => max(0, $subtotal - $descuento),
        'count' => cart_count(),
    ];
}
