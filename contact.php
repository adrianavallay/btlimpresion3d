<?php
/**
 * Endpoint de contacto — recibe POST JSON desde el form y envía mail via SMTP.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

// CORS: permitir solo el dominio oficial (ajustar si hace falta dev local)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $config['allowed_origin']) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido']);
    exit;
}

// Leer body: soporta form-urlencoded o JSON
$raw = file_get_contents('php://input');
$data = [];
if ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $data = json_decode($raw, true) ?? [];
} else {
    $data = $_POST;
}

$name    = trim((string)($data['name']    ?? ''));
$email   = trim((string)($data['email']   ?? ''));
$phone   = trim((string)($data['phone']   ?? ''));
$service = trim((string)($data['service'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$honey   = trim((string)($data['website'] ?? '')); // honeypot antispam

// Honeypot: si bot llena el campo oculto, descartar silenciosamente
if ($honey !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

// Validaciones
if ($name === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nombre y email son obligatorios']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email invalido']);
    exit;
}
if (mb_strlen($name) > 100 || mb_strlen($email) > 150 || mb_strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Campos demasiado largos']);
    exit;
}

// Cargar PHPMailer
require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'];
    $mail->Password   = $config['smtp_pass'];
    $mail->SMTPSecure = $config['smtp_secure'];
    $mail->Port       = (int)$config['smtp_port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email'], $config['to_name']);
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Nueva consulta web — ' . $name;

    $rows = [
        ['Nombre',   $name],
        ['Email',    $email],
        ['Telefono', $phone !== ''   ? $phone   : '—'],
        ['Servicio', $service !== '' ? $service : '—'],
    ];

    $rowsHtml = '';
    foreach ($rows as [$label, $value]) {
        $rowsHtml .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:600;color:#555;width:120px;">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;color:#111;">'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            . '</td></tr>';
    }

    $msgHtml = nl2br(htmlspecialchars($message !== '' ? $message : '(sin mensaje)', ENT_QUOTES, 'UTF-8'));

    $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">'
        . '<h2 style="color:#111;border-bottom:2px solid #ff6a00;padding-bottom:8px;">Nueva consulta desde la web</h2>'
        . '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">' . $rowsHtml . '</table>'
        . '<h3 style="color:#555;margin-bottom:8px;">Mensaje:</h3>'
        . '<div style="padding:12px;background:#f8f8f8;border-left:3px solid #ff6a00;color:#222;">' . $msgHtml . '</div>'
        . '<p style="color:#999;font-size:12px;margin-top:24px;">Enviado desde btlimpresion3d.com.ar</p>'
        . '</div>';

    $mail->AltBody = "Nombre: $name\nEmail: $email\nTelefono: $phone\nServicio: $service\n\nMensaje:\n$message";

    $mail->send();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[contact.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo enviar el mensaje. Intenta de nuevo o escribinos por WhatsApp.']);
}
