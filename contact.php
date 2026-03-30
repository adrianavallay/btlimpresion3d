<?php
// ============================================================
// CONFIGURACIÓN — Completar con los datos del servidor
// ============================================================
$db_host = "localhost";
$db_name = "a0090877_aprueba";
$db_user = "a0090877_aprueba";
$db_pass = "rudaVO40/ribi9/";

// ============================================================

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["ok" => false, "mensaje" => "Método no permitido"]);
    exit;
}

// Leer datos del body (JSON o form-data)
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (stripos($contentType, "application/json") !== false) {
    $input = json_decode(file_get_contents("php://input"), true);
} else {
    $input = $_POST;
}

$nombre   = htmlspecialchars(trim($input["nombre"] ?? ""), ENT_QUOTES, "UTF-8");
$email    = filter_var(trim($input["email"] ?? ""), FILTER_SANITIZE_EMAIL);
$telefono = htmlspecialchars(trim($input["telefono"] ?? ""), ENT_QUOTES, "UTF-8");
$servicio = htmlspecialchars(trim($input["servicio"] ?? ""), ENT_QUOTES, "UTF-8");
$mensaje  = htmlspecialchars(trim($input["mensaje"] ?? ""), ENT_QUOTES, "UTF-8");

// Validación
if ($nombre === "" || $email === "" || $mensaje === "") {
    echo json_encode(["ok" => false, "mensaje" => "Nombre, email y mensaje son obligatorios"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["ok" => false, "mensaje" => "Email inválido"]);
    exit;
}

// Conexión a MySQL
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear tabla si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contactos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha DATETIME DEFAULT NOW(),
            nombre VARCHAR(100),
            email VARCHAR(100),
            telefono VARCHAR(20),
            servicio VARCHAR(100),
            mensaje TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error de conexión a la base de datos"]);
    exit;
}

// Insertar lead
try {
    $stmt = $pdo->prepare(
        "INSERT INTO contactos (nombre, email, telefono, servicio, mensaje)
         VALUES (:nombre, :email, :telefono, :servicio, :mensaje)"
    );
    $stmt->execute([
        ":nombre"   => $nombre,
        ":email"    => $email,
        ":telefono" => $telefono,
        ":servicio" => $servicio,
        ":mensaje"  => $mensaje,
    ]);
} catch (PDOException $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error al guardar en la base de datos"]);
    exit;
}

echo json_encode(["ok" => true, "mensaje" => "Mensaje enviado con éxito"]);
