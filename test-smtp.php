<?php
/**
 * Test de diagnostico SMTP — BORRAR DESPUES DE USAR
 * Acceder via: https://btlimpresion3d.com.ar/test-smtp.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTICO BTL IMPRESION 3D ===\n\n";

// 1. Version de PHP
echo "[1] PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo "    ⚠ PHP muy viejo, actualizar a 7.4+ en cPanel\n";
}

// 2. config.php existe?
echo "\n[2] config.php: ";
if (!file_exists(__DIR__ . '/config.php')) {
    echo "✗ NO EXISTE — subir config.php al servidor\n";
    exit;
}
echo "✓ OK\n";

$config = require __DIR__ . '/config.php';
echo "    Host: " . $config['smtp_host'] . "\n";
echo "    Puerto: " . $config['smtp_port'] . "\n";
echo "    Usuario: " . $config['smtp_user'] . "\n";

// 3. PHPMailer existe?
echo "\n[3] PHPMailer: ";
if (!file_exists(__DIR__ . '/lib/PHPMailer/PHPMailer.php')) {
    echo "✗ NO EXISTE — faltan archivos en lib/PHPMailer/\n";
    exit;
}
echo "✓ OK\n";

// 4. Conectividad al SMTP
echo "\n[4] Conectividad SMTP (puerto {$config['smtp_port']}): ";
$fp = @fsockopen(
    ($config['smtp_secure'] === 'ssl' ? 'ssl://' : '') . $config['smtp_host'],
    (int)$config['smtp_port'],
    $errno,
    $errstr,
    10
);
if (!$fp) {
    echo "✗ NO CONECTA — error $errno: $errstr\n";
    echo "    Probar puerto 587 con TLS en config.php\n";
} else {
    echo "✓ OK\n";
    fclose($fp);
}

// 5. Envio real
echo "\n[5] Envio de mail de prueba:\n";

require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);
$mail->SMTPDebug = 2; // muestra el dialogo SMTP
$mail->Debugoutput = function ($str, $level) { echo "    [$level] " . trim($str) . "\n"; };

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
    $mail->addAddress($config['to_email']);
    $mail->Subject = 'Test SMTP — BTL Impresion 3D';
    $mail->Body    = 'Test de envio funcionando — ' . date('Y-m-d H:i:s');

    $mail->send();
    echo "\n✓ MAIL ENVIADO OK — revisa la casilla hola@btlimpresion3d.com.ar\n";
} catch (Throwable $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n";
echo "IMPORTANTE: borrar este archivo despues de diagnosticar\n";
