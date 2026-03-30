<?php
require_once __DIR__ . '/config.php';

// ── CLIENTE AUTH ──

function cliente_register(string $nombre, string $email, string $password, string $telefono = ''): array {
    $db = pdo();
    $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'mensaje' => 'Ya existe una cuenta con ese email'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO clientes (nombre, email, password, telefono) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $email, $hash, $telefono]);
    $id = (int) $db->lastInsertId();
    $_SESSION['cliente_id'] = $id;
    $_SESSION['cliente_nombre'] = $nombre;
    $_SESSION['cliente_email'] = $email;
    return ['ok' => true, 'mensaje' => 'Cuenta creada con éxito'];
}

function cliente_login(string $email, string $password): array {
    $db = pdo();
    $stmt = $db->prepare("SELECT * FROM clientes WHERE email = ? AND activo = 1");
    $stmt->execute([$email]);
    $c = $stmt->fetch();
    if (!$c || !password_verify($password, $c['password'])) {
        return ['ok' => false, 'mensaje' => 'Email o contraseña incorrectos'];
    }
    $_SESSION['cliente_id'] = (int) $c['id'];
    $_SESSION['cliente_nombre'] = $c['nombre'];
    $_SESSION['cliente_email'] = $c['email'];
    $db->prepare("UPDATE clientes SET ultimo_acceso = NOW() WHERE id = ?")->execute([$c['id']]);
    return ['ok' => true, 'mensaje' => 'Sesión iniciada'];
}

function cliente_logout(): void {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_email']);
}

function cliente_data(): ?array {
    if (!is_cliente()) return null;
    $stmt = pdo()->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([cliente_id()]);
    return $stmt->fetch() ?: null;
}

function cliente_change_password(int $id, string $current, string $new): array {
    $db = pdo();
    $stmt = $db->prepare("SELECT password FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password'])) {
        return ['ok' => false, 'mensaje' => 'Contraseña actual incorrecta'];
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $db->prepare("UPDATE clientes SET password = ? WHERE id = ?")->execute([$hash, $id]);
    return ['ok' => true, 'mensaje' => 'Contraseña actualizada'];
}

// ── ADMIN AUTH ──

function admin_login(string $user, string $pass): bool {
    if ($user === ADMIN_USER && $pass === ADMIN_PASS_PLAIN) {
        $_SESSION['admin_auth'] = true;
        return true;
    }
    return false;
}

function admin_logout(): void {
    unset($_SESSION['admin_auth']);
}

function require_admin(): void {
    if (!is_admin()) {
        redirect('admin.php');
    }
}

function require_cliente(): void {
    if (!is_cliente()) {
        redirect('login.php');
    }
}
