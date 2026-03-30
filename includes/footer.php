<footer class="site-footer" id="contacto">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <a href="<?= SITE_URL ?>/" class="footer-logo"><?= SITE_NAME ?></a>
        <p class="footer-desc">Productos premium para quienes buscan calidad y estilo. Envios a todo el pais.</p>
      </div>
      <div class="footer-col">
        <h4>Tienda</h4>
        <ul>
          <li><a href="<?= SITE_URL ?>/">Inicio</a></li>
          <li><a href="<?= url_pagina('tienda') ?>">Productos</a></li>
          <li><a href="<?= SITE_URL ?>/#categorias">Categorias</a></li>
          <li><a href="<?= url_pagina('carrito') ?>">Carrito</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Cuenta</h4>
        <ul>
          <li><a href="<?= url_pagina('login') ?>">Ingresar</a></li>
          <li><a href="<?= url_pagina('mi-cuenta') ?>">Mi cuenta</a></li>
          <li><a href="<?= url_pagina('mis-pedidos') ?>">Mis pedidos</a></li>
          <li><a href="<?= url_pagina('wishlist') ?>">Favoritos</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contacto</h4>
        <ul>
          <li>info@estudiopixelpro.com.ar</li>
          <li>Buenos Aires, Argentina</li>
        </ul>
        <div class="footer-social">
          <a href="#" aria-label="Instagram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/></svg></a>
          <a href="#" aria-label="Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
          <a href="#" aria-label="WhatsApp"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Todos los derechos reservados.</p>
    </div>
  </div>
</footer>

<!-- Cart Drawer -->
<div class="cart-overlay" id="cartOverlay"></div>
<aside class="cart-drawer" id="cartDrawer">
  <div class="cart-drawer-header">
    <h3>Tu carrito</h3>
    <button id="cartClose">&times;</button>
  </div>
  <div class="cart-drawer-items" id="cartDrawerItems">
  </div>
  <div class="cart-drawer-footer">
    <div class="cart-summary-line">
      <span>Subtotal</span>
      <span id="drawerSubtotal">$0,00</span>
    </div>
    <div class="cart-summary-line muted">
      <span>Envío</span>
      <span>Se calcula en el checkout</span>
    </div>
    <div class="cart-summary-line total">
      <span>Total</span>
      <span id="drawerTotal">$0,00</span>
    </div>
    <a href="<?= url_pagina('carrito') ?>" class="btn-cart-checkout">Ver carrito completo</a>
    <a href="<?= url_pagina('checkout') ?>" class="btn-cart-primary">Iniciar compra</a>
  </div>
</aside>

<script src="<?= SITE_URL ?>/js/tienda.js"></script>
<?php if (isset($extra_js)): ?><script src="<?= $extra_js ?>"></script><?php endif; ?>
</body>
</html>
