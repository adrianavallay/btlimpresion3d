<?php
require_once __DIR__ . '/config.php';

$results = [];
$all_ok = true;

try {
    $db = pdo();
    $results[] = ['ok' => true, 'msg' => 'Conexión a MySQL exitosa'];

    $tables = [
        'categorias' => "CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE,
            descripcion TEXT,
            imagen VARCHAR(300),
            orden INT DEFAULT 0,
            activa TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'productos' => "CREATE TABLE IF NOT EXISTS productos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categoria_id INT,
            nombre VARCHAR(200) NOT NULL,
            slug VARCHAR(200) UNIQUE,
            descripcion TEXT,
            descripcion_corta VARCHAR(300),
            precio DECIMAL(10,2) NOT NULL,
            precio_oferta DECIMAL(10,2) DEFAULT NULL,
            stock INT DEFAULT 0,
            stock_minimo INT DEFAULT 5,
            imagen_principal VARCHAR(300),
            estado ENUM('activo','borrador','agotado') DEFAULT 'activo',
            destacado TINYINT(1) DEFAULT 0,
            meta_titulo VARCHAR(200),
            meta_descripcion VARCHAR(300),
            total_ventas INT DEFAULT 0,
            rating_promedio DECIMAL(3,2) DEFAULT 0,
            fecha_creacion DATETIME DEFAULT NOW(),
            fecha_modificacion DATETIME ON UPDATE NOW(),
            FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'producto_imagenes' => "CREATE TABLE IF NOT EXISTS producto_imagenes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            producto_id INT,
            imagen VARCHAR(300),
            orden INT DEFAULT 0,
            FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'producto_variantes' => "CREATE TABLE IF NOT EXISTS producto_variantes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            producto_id INT,
            nombre VARCHAR(100),
            valor VARCHAR(100),
            stock_extra INT DEFAULT 0,
            precio_extra DECIMAL(10,2) DEFAULT 0,
            FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'clientes' => "CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100),
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            telefono VARCHAR(20),
            direccion TEXT,
            ciudad VARCHAR(100),
            provincia VARCHAR(100),
            activo TINYINT(1) DEFAULT 1,
            fecha_registro DATETIME DEFAULT NOW(),
            ultimo_acceso DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'pedidos' => "CREATE TABLE IF NOT EXISTS pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT DEFAULT NULL,
            fecha DATETIME DEFAULT NOW(),
            nombre VARCHAR(100),
            email VARCHAR(100),
            telefono VARCHAR(20),
            direccion TEXT,
            ciudad VARCHAR(100),
            provincia VARCHAR(100),
            subtotal DECIMAL(10,2),
            descuento DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2),
            cupon_codigo VARCHAR(50),
            estado ENUM('pendiente','pagado','preparando','enviado','entregado','cancelado','reembolsado') DEFAULT 'pendiente',
            mp_preference_id VARCHAR(200),
            mp_payment_id VARCHAR(200),
            notas TEXT,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'pedido_items' => "CREATE TABLE IF NOT EXISTS pedido_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT,
            producto_id INT,
            nombre_producto VARCHAR(200),
            variante VARCHAR(100),
            cantidad INT,
            precio_unitario DECIMAL(10,2),
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
            FOREIGN KEY (producto_id) REFERENCES productos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'cupones' => "CREATE TABLE IF NOT EXISTS cupones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) UNIQUE,
            tipo ENUM('porcentaje','monto_fijo') DEFAULT 'porcentaje',
            valor DECIMAL(10,2),
            minimo_compra DECIMAL(10,2) DEFAULT 0,
            usos_maximos INT DEFAULT NULL,
            usos_actuales INT DEFAULT 0,
            fecha_inicio DATE,
            fecha_fin DATE,
            activo TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'resenas' => "CREATE TABLE IF NOT EXISTS resenas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            producto_id INT,
            cliente_id INT DEFAULT NULL,
            nombre VARCHAR(100),
            email VARCHAR(100),
            rating TINYINT NOT NULL,
            comentario TEXT,
            aprobada TINYINT(1) DEFAULT 0,
            fecha DATETIME DEFAULT NOW(),
            FOREIGN KEY (producto_id) REFERENCES productos(id),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'wishlist' => "CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT,
            producto_id INT,
            fecha DATETIME DEFAULT NOW(),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id),
            FOREIGN KEY (producto_id) REFERENCES productos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'carritos_abandonados' => "CREATE TABLE IF NOT EXISTS carritos_abandonados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(100),
            email VARCHAR(100),
            items JSON,
            total DECIMAL(10,2),
            fecha DATETIME DEFAULT NOW(),
            recuperado TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $name => $sql) {
        try {
            $db->exec($sql);
            $results[] = ['ok' => true, 'msg' => "Tabla '$name' OK"];
        } catch (PDOException $e) {
            $results[] = ['ok' => false, 'msg' => "Error en '$name': " . $e->getMessage()];
            $all_ok = false;
        }
    }
} catch (PDOException $e) {
    $results[] = ['ok' => false, 'msg' => 'Error de conexión: ' . $e->getMessage()];
    $all_ok = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — Tienda DB</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0a0a0f;color:#e4e4e7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#12121a;border:1px solid #27272a;border-radius:12px;padding:40px;max-width:600px;width:100%}
  h1{font-size:1.4rem;margin-bottom:24px;text-align:center}
  .item{padding:8px 12px;border-radius:6px;margin-bottom:6px;font-size:0.9rem;display:flex;align-items:center;gap:8px}
  .item.ok{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);color:#4ade80}
  .item.fail{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#f87171}
  .dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
  .ok .dot{background:#22c55e}
  .fail .dot{background:#ef4444}
  .summary{margin-top:20px;text-align:center;padding:16px;border-radius:8px}
  .summary.ok{background:rgba(34,197,94,0.1);border:1px solid #22c55e;color:#4ade80}
  .summary.fail{background:rgba(239,68,68,0.1);border:1px solid #ef4444;color:#f87171}
  .warning{margin-top:16px;color:#71717a;font-size:0.85rem;text-align:center}
</style>
</head>
<body>
<div class="card">
  <h1>Setup Base de Datos — Tienda</h1>
  <?php foreach ($results as $r): ?>
    <div class="item <?= $r['ok'] ? 'ok' : 'fail' ?>">
      <span class="dot"></span>
      <?= $r['msg'] ?>
    </div>
  <?php endforeach; ?>
  <div class="summary <?= $all_ok ? 'ok' : 'fail' ?>">
    <?= $all_ok ? '11 tablas creadas correctamente' : 'Hubo errores — revisá arriba' ?>
  </div>
  <p class="warning">Eliminá este archivo del servidor después de ejecutarlo.</p>
</div>
</body>
</html>
