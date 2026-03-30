<?php
require_once __DIR__ . '/config.php';

$pedido_id = (int) ($_GET['id'] ?? 0);
$cartCount = cart_count();

$page_title = 'Pago cancelado';
include __DIR__ . '/includes/header.php';
?>

<style>
    .cancel-card {
      max-width: 560px;
      margin: 0 auto;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 48px 40px;
      text-align: center;
    }
    @media (max-width: 600px) {
      .cancel-card { padding: 32px 20px; }
    }

    /* Red X icon */
    .x-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: rgba(239, 68, 68, 0.12);
      border: 3px solid #ef4444;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
      position: relative;
      animation: scaleIn .4s ease;
    }
    .x-icon::before,
    .x-icon::after {
      content: '';
      position: absolute;
      width: 32px;
      height: 4px;
      background: #ef4444;
      border-radius: 2px;
    }
    .x-icon::before { transform: rotate(45deg); }
    .x-icon::after  { transform: rotate(-45deg); }

    @keyframes scaleIn {
      0% { transform: scale(0); opacity: 0; }
      60% { transform: scale(1.15); }
      100% { transform: scale(1); opacity: 1; }
    }

    .cancel-card h1 {
      font-family: 'Sora', sans-serif;
      font-size: 1.8rem;
      margin-bottom: 8px;
      color: var(--text);
    }
    .cancel-card .subtitle {
      color: var(--text-muted);
      font-size: .95rem;
      margin-bottom: 12px;
      line-height: 1.6;
    }
    .cancel-card .detail {
      color: var(--text-muted);
      font-size: .85rem;
      margin-bottom: 32px;
      line-height: 1.6;
    }

    /* Buttons */
    .cancel-actions {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
    }
    .cancel-actions .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 14px 32px;
      background: var(--accent);
      color: #fff;
      font-weight: 600;
      font-size: .9rem;
      border: none;
      border-radius: 10px;
      text-decoration: none;
      cursor: pointer;
      transition: background .2s, transform .15s;
    }
    .cancel-actions .btn-primary:hover {
      background: var(--accent-light);
      transform: translateY(-1px);
    }
    .cancel-actions .link-secondary {
      color: var(--accent-light);
      font-size: .88rem;
      text-decoration: none;
      font-weight: 500;
      transition: opacity .2s;
    }
    .cancel-actions .link-secondary:hover {
      opacity: .8;
      text-decoration: underline;
    }
  </style>

<!-- ═══════════ MAIN CONTENT ═══════════ -->
<section class="container" style="padding-top:120px;padding-bottom:80px;min-height:80vh;display:flex;align-items:center;justify-content:center;">
  <div class="cancel-card fade-in">

    <!-- Red X -->
    <div class="x-icon"></div>

    <h1>Pago cancelado</h1>
    <p class="subtitle">Tu pedido no fue procesado.</p>
    <p class="detail">
      No se realiz&oacute; ning&uacute;n cargo a tu cuenta.
      <?php if ($pedido_id > 0): ?>
        <br>Referencia de pedido: <strong>#<?= $pedido_id ?></strong>
      <?php endif; ?>
    </p>

    <!-- Actions -->
    <div class="cancel-actions">
      <a href="<?= url_pagina('checkout') ?>" class="btn-primary">Reintentar pago</a>
      <a href="<?= SITE_URL ?>/" class="link-secondary">&larr; Volver a la tienda</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
