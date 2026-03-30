<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_helper.php';

// ── Only POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('checkout.php');
}

// ── CSRF check ────────────────────────────────────────────
$csrf = $_POST['csrf'] ?? '';
if (!hash_equals(csrf_token(), $csrf)) {
    flash('error', 'Token de seguridad inválido. Intentá de nuevo.');
    redirect('checkout.php');
}

// ── Validate required fields ──────────────────────────────
$nombre    = trim($_POST['nombre'] ?? '');
$email     = trim($_POST['email'] ?? '');
$telefono  = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$ciudad    = trim($_POST['ciudad'] ?? '');
$provincia = trim($_POST['provincia'] ?? '');

if ($nombre === '' || $email === '' || $direccion === '' || $ciudad === '' || $provincia === '') {
    flash('error', 'Completá todos los campos obligatorios.');
    redirect('checkout.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'El email ingresado no es válido.');
    redirect('checkout.php');
}

// ── Cart check ────────────────────────────────────────────
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    redirect('carrito.php');
}

// ── Calculate totals ──────────────────────────────────────
$subtotal = 0;
$cart_items = [];
foreach ($cart as $key => $item) {
    $line_total = $item['precio'] * $item['qty'];
    $subtotal += $line_total;
    $cart_items[] = $item;
}

$descuento = 0;
$cupon = $_SESSION['cupon'] ?? null;
$cupon_codigo = null;
if ($cupon) {
    $cupon_codigo = $cupon['codigo'];
    if ($cupon['tipo'] === 'porcentaje') {
        $descuento = round($subtotal * $cupon['valor'] / 100, 2);
    } else {
        $descuento = min($cupon['valor'], $subtotal);
    }
}
$total = max(0, round($subtotal - $descuento, 2));

// ── Insert pedido ─────────────────────────────────────────
try {
    $db = pdo();
    $db->beginTransaction();

    $cliente_id = is_cliente() ? cliente_id() : null;

    $stmt = $db->prepare("
        INSERT INTO pedidos (cliente_id, nombre, email, telefono, direccion, ciudad, provincia, subtotal, descuento, total, cupon_codigo, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->execute([
        $cliente_id,
        $nombre,
        $email,
        $telefono,
        $direccion,
        $ciudad,
        $provincia,
        $subtotal,
        $descuento,
        $total,
        $cupon_codigo,
    ]);

    $pedido_id = (int) $db->lastInsertId();

    // ── Insert pedido_items ───────────────────────────────
    $stmtItem = $db->prepare("
        INSERT INTO pedido_items (pedido_id, producto_id, nombre_producto, variante, cantidad, precio_unitario)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($cart_items as $item) {
        $stmtItem->execute([
            $pedido_id,
            $item['producto_id'],
            $item['nombre'],
            $item['variante'] ?? '',
            $item['qty'],
            $item['precio'],
        ]);
    }

    // ── Increment coupon usage ────────────────────────────
    if ($cupon) {
        $db->prepare("UPDATE cupones SET usos_actuales = usos_actuales + 1 WHERE id = ?")
           ->execute([$cupon['id']]);
    }

    $db->commit();

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Checkout error: " . $e->getMessage());
    flash('error', 'Error al procesar el pedido. Intentá de nuevo.');
    redirect('checkout.php');
}

// ── Build pedido array for emails ─────────────────────────
$pedido = [
    'id'        => $pedido_id,
    'nombre'    => $nombre,
    'email'     => $email,
    'subtotal'  => $subtotal,
    'descuento' => $descuento,
    'total'     => $total,
    'estado'    => 'pendiente',
];

// Build items array for email
$email_items = [];
foreach ($cart_items as $item) {
    $email_items[] = [
        'nombre_producto' => $item['nombre'],
        'cantidad'        => $item['qty'],
        'precio_unitario' => $item['precio'],
    ];
}

// ── Send confirmation emails ──────────────────────────────
try {
    email_pedido_confirmacion($pedido, $email_items);
    email_admin_nuevo_pedido($pedido);
} catch (Exception $e) {
    error_log("Email error on pedido #{$pedido_id}: " . $e->getMessage());
}

// ── MercadoPago ───────────────────────────────────────────
if (MP_ACCESS_TOKEN === 'MP_ACCESS_TOKEN_AQUI' || MP_ACCESS_TOKEN === '') {
    // Placeholder token — skip MP, redirect to gracias page (testing mode)
    $_SESSION['cart'] = [];
    unset($_SESSION['cupon']);
    redirect("gracias.php?id={$pedido_id}");
}

// Build MP preference
$mp_items = [];
foreach ($cart_items as $item) {
    $mp_items[] = [
        'title'       => $item['nombre'],
        'quantity'    => (int) $item['qty'],
        'unit_price'  => (float) $item['precio'],
        'currency_id' => 'ARS',
    ];
}

// If there's a discount, add it as a negative item
if ($descuento > 0) {
    $mp_items[] = [
        'title'       => 'Descuento cupón ' . ($cupon_codigo ?? ''),
        'quantity'    => 1,
        'unit_price'  => -$descuento,
        'currency_id' => 'ARS',
    ];
}

$preference = [
    'items'              => $mp_items,
    'external_reference' => (string) $pedido_id,
    'back_urls'          => [
        'success' => SITE_URL . '/gracias.php?id=' . $pedido_id,
        'failure' => SITE_URL . '/cancelado.php',
        'pending' => SITE_URL . '/gracias.php?id=' . $pedido_id,
    ],
    'notification_url'   => SITE_URL . '/mp_webhook.php',
    'auto_return'        => 'approved',
    'payer'              => [
        'name'    => $nombre,
        'email'   => $email,
        'phone'   => ['number' => $telefono],
        'address' => [
            'street_name' => $direccion,
            'zip_code'    => '',
        ],
    ],
];

$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($preference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error || $http_code < 200 || $http_code >= 300) {
    error_log("MP preference error (HTTP {$http_code}): {$curl_error} — Response: {$response}");
    flash('error', 'Error al conectar con MercadoPago. Intentá de nuevo.');
    redirect('checkout.php');
}

$mp_data = json_decode($response, true);

if (empty($mp_data['id']) || empty($mp_data['init_point'])) {
    error_log("MP preference response invalid: {$response}");
    flash('error', 'Error al crear la preferencia de pago. Intentá de nuevo.');
    redirect('checkout.php');
}

// Save MP preference ID in pedido
try {
    pdo()->prepare("UPDATE pedidos SET mp_preference_id = ? WHERE id = ?")
         ->execute([$mp_data['id'], $pedido_id]);
} catch (Exception $e) {
    error_log("Error saving mp_preference_id: " . $e->getMessage());
}

// Clear cart and redirect to MercadoPago
$_SESSION['cart'] = [];
unset($_SESSION['cupon']);
redirect($mp_data['init_point']);
