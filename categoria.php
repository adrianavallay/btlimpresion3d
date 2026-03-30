<?php
require_once __DIR__ . '/config.php';

// Get category by slug
$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    redirect('tienda.php');
}

$stmt = pdo()->prepare("SELECT * FROM categorias WHERE slug = ? AND activa = 1 LIMIT 1");
$stmt->execute([$slug]);
$categoria = $stmt->fetch();

if (!$categoria) {
    redirect('tienda.php');
}

// Redirect to tienda with category filter
redirect(url_pagina('tienda') . '?cat=' . urlencode($categoria['slug']));
