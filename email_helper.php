<?php
require_once __DIR__ . '/config.php';

function email_template(string $title, string $body): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#0a0a0f;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">
    <div style="max-width:600px;margin:40px auto;background:#12121a;border-radius:12px;border:1px solid #27272a;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#7c3aed,#2563eb);padding:32px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:1.5rem;">' . SITE_NAME . '</h1>
        </div>
        <div style="padding:32px;color:#e4e4e7;">
            <h2 style="color:#a78bfa;margin:0 0 16px;font-size:1.2rem;">' . $title . '</h2>
            ' . $body . '
        </div>
        <div style="padding:20px 32px;border-top:1px solid #27272a;text-align:center;color:#71717a;font-size:0.85rem;">
            ' . SITE_NAME . ' &copy; ' . date('Y') . '
        </div>
    </div></body></html>';
}

function send_email(string $to, string $subject, string $htmlBody): bool {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\n";
    return @mail($to, $subject, $htmlBody, $headers);
}

function email_pedido_confirmacion(array $pedido, array $items): void {
    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr>
            <td style="padding:8px;border-bottom:1px solid #27272a;color:#e4e4e7;">' . sanitize($item['nombre_producto']) . '</td>
            <td style="padding:8px;border-bottom:1px solid #27272a;color:#a78bfa;text-align:center;">' . $item['cantidad'] . '</td>
            <td style="padding:8px;border-bottom:1px solid #27272a;color:#e4e4e7;text-align:right;">' . price($item['precio_unitario'] * $item['cantidad']) . '</td>
        </tr>';
    }
    $body = '
    <p style="color:#e4e4e7;line-height:1.6;">Tu pedido <strong style="color:#a78bfa;">#' . $pedido['id'] . '</strong> fue registrado con éxito.</p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0;">
        <thead><tr>
            <th style="padding:8px;border-bottom:1px solid #7c3aed;color:#71717a;text-align:left;">Producto</th>
            <th style="padding:8px;border-bottom:1px solid #7c3aed;color:#71717a;text-align:center;">Cant.</th>
            <th style="padding:8px;border-bottom:1px solid #7c3aed;color:#71717a;text-align:right;">Subtotal</th>
        </tr></thead>
        <tbody>' . $rows . '</tbody>
    </table>
    <div style="text-align:right;margin-top:16px;">
        <p style="color:#71717a;margin:4px 0;">Subtotal: ' . price($pedido['subtotal']) . '</p>
        ' . ($pedido['descuento'] > 0 ? '<p style="color:#10b981;margin:4px 0;">Descuento: -' . price($pedido['descuento']) . '</p>' : '') . '
        <p style="color:#a78bfa;font-size:1.2rem;font-weight:700;margin:8px 0;">Total: ' . price($pedido['total']) . '</p>
    </div>';
    $html = email_template('Confirmación de Pedido #' . $pedido['id'], $body);
    send_email($pedido['email'], 'Pedido #' . $pedido['id'] . ' — ' . SITE_NAME, $html);
}

function email_pedido_estado(array $pedido): void {
    $estados = [
        'pendiente' => 'Pendiente de pago',
        'pagado' => 'Pago confirmado',
        'preparando' => 'En preparación',
        'enviado' => 'Enviado',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado'
    ];
    $estado = $estados[$pedido['estado']] ?? $pedido['estado'];
    $body = '<p style="color:#e4e4e7;line-height:1.6;">El estado de tu pedido <strong style="color:#a78bfa;">#' . $pedido['id'] . '</strong> cambió a:</p>
    <div style="background:#0a0a0f;border:1px solid #7c3aed;border-radius:8px;padding:16px;text-align:center;margin:20px 0;">
        <span style="color:#a78bfa;font-size:1.3rem;font-weight:700;">' . $estado . '</span>
    </div>
    <p style="color:#71717a;font-size:0.9rem;">Total del pedido: ' . price($pedido['total']) . '</p>';
    $html = email_template('Pedido #' . $pedido['id'] . ' — ' . $estado, $body);
    send_email($pedido['email'], 'Pedido #' . $pedido['id'] . ' actualizado — ' . SITE_NAME, $html);
}

function email_admin_nuevo_pedido(array $pedido): void {
    $body = '<p style="color:#e4e4e7;line-height:1.6;">Nuevo pedido recibido:</p>
    <ul style="color:#e4e4e7;line-height:2;">
        <li>Pedido: <strong>#' . $pedido['id'] . '</strong></li>
        <li>Cliente: ' . sanitize($pedido['nombre']) . ' (' . sanitize($pedido['email']) . ')</li>
        <li>Total: <strong style="color:#a78bfa;">' . price($pedido['total']) . '</strong></li>
    </ul>';
    $html = email_template('Nuevo Pedido #' . $pedido['id'], $body);
    send_email(NOTIFY_EMAIL, 'Nuevo Pedido #' . $pedido['id'] . ' — ' . SITE_NAME, $html);
}

function email_stock_bajo(array $producto): void {
    $body = '<p style="color:#e4e4e7;line-height:1.6;">El producto <strong style="color:#f43f5e;">' . sanitize($producto['nombre']) . '</strong> tiene stock bajo:</p>
    <div style="background:#0a0a0f;border:1px solid #f43f5e;border-radius:8px;padding:16px;text-align:center;margin:20px 0;">
        <span style="color:#f43f5e;font-size:2rem;font-weight:700;">' . $producto['stock'] . ' unidades</span>
    </div>';
    $html = email_template('Stock Bajo — ' . $producto['nombre'], $body);
    send_email(NOTIFY_EMAIL, 'Stock bajo: ' . $producto['nombre'] . ' — ' . SITE_NAME, $html);
}
