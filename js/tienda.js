/**
 * DyP Consultora - Tienda Online
 * Main JavaScript - Vanilla JS, no frameworks
 */

// ── Card gallery slider (global) ──
function cardSlide(btn, dir) {
  const container = btn.closest('.product-card__img');
  if (!container) return;
  const slides = container.querySelectorAll('.card-slide');
  const dots = container.querySelectorAll('.card-dot');
  if (slides.length < 2) return;
  let current = 0;
  slides.forEach((s, i) => { if (s.classList.contains('active')) current = i; });
  slides[current].classList.remove('active');
  if (dots[current]) dots[current].classList.remove('active');
  let next = current + dir;
  if (next < 0) next = slides.length - 1;
  if (next >= slides.length) next = 0;
  slides[next].classList.add('active');
  if (dots[next]) dots[next].classList.add('active');
}

// Delegate click events for card arrows to prevent link navigation
document.addEventListener('click', function(e) {
  const arrow = e.target.closest('.card-arrow');
  if (arrow) {
    e.preventDefault();
    e.stopPropagation();
    const dir = arrow.classList.contains('card-arrow--next') ? 1 : -1;
    cardSlide(arrow, dir);
    return;
  }
  // Prevent wishlist btn inside cards from navigating to product
  const wishBtn = e.target.closest('.product-card__wish .wishlist-btn');
  if (wishBtn) {
    e.preventDefault();
    e.stopImmediatePropagation();
    wishlistToggle(wishBtn);
    return;
  }
}, true);

// Wishlist toggle — works for both cards and product page
function wishlistToggle(btn) {
  const id = btn.dataset.productoId;
  if (!id) return;
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfInput = document.querySelector('input[name="csrf"]');
  const csrf = csrfMeta ? csrfMeta.content : (csrfInput ? csrfInput.value : '');

  fetch(SITE_URL + '/wishlist.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams({ action: 'toggle', producto_id: id, csrf: csrf })
  })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        // Update all wishlist buttons for this product on the page
        document.querySelectorAll('[data-producto-id="' + id + '"]').forEach(b => {
          if (!b.classList.contains('wishlist-btn') && !b.classList.contains('wishlist-btn-single')) return;
          if (data.in_wishlist) b.classList.add('active');
          else b.classList.remove('active');
          // Update text on single product page button
          const textEl = b.querySelector('.wishlist-btn-single__text');
          if (textEl) textEl.textContent = data.in_wishlist ? 'En favoritos' : 'Agregar a favoritos';
        });
        if (typeof showToast === 'function') showToast(data.mensaje);
      } else {
        if (data.mensaje && data.mensaje.includes('sesión')) {
          if (typeof showToast === 'function') showToast('Iniciá sesión para guardar favoritos', 'error');
        }
      }
    })
    .catch(() => { if (typeof showToast === 'function') showToast('Error al actualizar favoritos', 'error'); });
}

document.addEventListener('DOMContentLoaded', () => {

  /* ==========================================================================
     1. NAVBAR — scroll effect, mobile menu, mini-cart toggle
     ========================================================================== */

  const navbar = document.querySelector('.navbar');
  const menuToggle = document.getElementById('menuToggle');
  const navLinks = document.getElementById('navLinks');
  const mobileOverlay = document.getElementById('mobileOverlay');
  const miniCartBtn = document.querySelector('.mini-cart-btn');
  const miniCartDropdown = document.querySelector('.mini-cart-dropdown');

  // Scroll effect — add .scrolled after 50px
  window.addEventListener('scroll', () => {
    if (!navbar) return;
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  });

  // Mobile menu toggle
  if (menuToggle && navLinks) {
    menuToggle.addEventListener('click', () => {
      navLinks.classList.toggle('open');
      menuToggle.classList.toggle('active');
      if (mobileOverlay) mobileOverlay.classList.toggle('active');
    });
    if (mobileOverlay) {
      mobileOverlay.addEventListener('click', () => {
        navLinks.classList.remove('open');
        menuToggle.classList.remove('active');
        mobileOverlay.classList.remove('active');
      });
    }
  }

  // Mini-cart dropdown toggle (legacy, kept for compat)
  if (miniCartBtn && miniCartDropdown) {
    miniCartBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      miniCartDropdown.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!miniCartDropdown.contains(e.target) && !miniCartBtn.contains(e.target)) {
        miniCartDropdown.classList.remove('open');
      }
    });
  }

  /* ==========================================================================
     CART DRAWER — slide-in from right
     ========================================================================== */

  const cartIcon = document.getElementById('cartIcon');
  const cartDrawer = document.getElementById('cartDrawer');
  const cartOverlay = document.getElementById('cartOverlay');
  const cartClose = document.getElementById('cartClose');

  function openCartDrawer() {
    if (cartDrawer) cartDrawer.classList.add('open');
    if (cartOverlay) cartOverlay.classList.add('open');
    loadCartDrawer();
  }
  function closeCartDrawer() {
    if (cartDrawer) cartDrawer.classList.remove('open');
    if (cartOverlay) cartOverlay.classList.remove('open');
  }

  if (cartIcon) {
    cartIcon.addEventListener('click', (e) => {
      e.preventDefault();
      openCartDrawer();
    });
  }
  if (cartClose) cartClose.addEventListener('click', closeCartDrawer);
  if (cartOverlay) cartOverlay.addEventListener('click', closeCartDrawer);

  function formatPrice(val) {
    return '$' + parseFloat(val).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function loadCartDrawer() {
    fetch('carrito_api.php?action=get')
      .then(r => r.json())
      .then(data => {
        const cart = data.cart || data;
        const container = document.getElementById('cartDrawerItems');
        if (!container) return;

        if (!cart.items || cart.items.length === 0) {
          container.innerHTML = '<p style="text-align:center;color:var(--color-text-light);padding:40px 0;font-size:0.9rem;">Tu carrito está vacío</p>';
          const sub = document.getElementById('drawerSubtotal');
          const tot = document.getElementById('drawerTotal');
          if (sub) sub.textContent = '$0,00';
          if (tot) tot.textContent = '$0,00';
          return;
        }

        const uploadUrl = 'uploads/productos/';
        container.innerHTML = cart.items.map(item => {
          const img = item.imagen ? uploadUrl + item.imagen : 'assets/no-image.png';
          return `
          <div class="cart-drawer-item">
            <img src="${img}" alt="${item.nombre}">
            <div class="cart-d-info">
              <div class="cart-d-name">${item.nombre}</div>
              ${item.variante ? '<div class="cart-d-variant">' + item.variante + '</div>' : ''}
              <div class="cart-d-price">${formatPrice(item.precio)}</div>
              <div class="cart-d-controls">
                <button onclick="window.dyp._drawerUpdate('${item.key}', ${item.qty - 1})">−</button>
                <span class="cart-d-qty">${item.qty}</span>
                <button onclick="window.dyp._drawerUpdate('${item.key}', ${item.qty + 1})">+</button>
              </div>
            </div>
            <button class="cart-d-remove" onclick="window.dyp._drawerRemove('${item.key}')">&times;</button>
          </div>`;
        }).join('');

        const sub = document.getElementById('drawerSubtotal');
        const tot = document.getElementById('drawerTotal');
        if (sub) sub.textContent = formatPrice(cart.subtotal);
        if (tot) tot.textContent = formatPrice(cart.total);
      })
      .catch(() => {});
  }

  function drawerUpdateItem(key, qty) {
    if (qty < 1) { drawerRemoveItem(key); return; }
    fetch('carrito_api.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'update', key, qty })
    }).then(r => r.json()).then(data => {
      loadCartDrawer();
      updateCartCounter(data.cart ? data.cart.count : 0);
    });
  }

  function drawerRemoveItem(key) {
    fetch('carrito_api.php', {
      method: 'POST',
      body: new URLSearchParams({ action: 'remove', key })
    }).then(r => r.json()).then(data => {
      loadCartDrawer();
      updateCartCounter(data.cart ? data.cart.count : 0);
    });
  }

  /* ==========================================================================
     THEME TOGGLE — dark/light mode
     ========================================================================== */

  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme') || 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
    });
  }

  /* ==========================================================================
     2. CART AJAX — all requests go to carrito_api.php
     ========================================================================== */

  const API_URL = 'carrito_api.php';
  const cartCounter = document.querySelector('.cart-counter') || document.getElementById('cartBadge');

  function addToCart(productoId, qty = 1, variante = null) {
    const body = new URLSearchParams({ action: 'add', producto_id: productoId, cantidad: qty });
    if (variante) body.append('variante', variante);

    return fetch(API_URL, { method: 'POST', body })
      .then(r => r.json())
      .then(data => {
        updateCartCounter(data.total_items || data.cart_count);
        showToast(data.message || data.mensaje || 'Producto agregado al carrito');
        renderMiniCart(data);
        openCartDrawer();
        return data;
      })
      .catch(() => showToast('Error al agregar al carrito', 'error'));
  }

  function updateCart(key, qty) {
    return fetch(API_URL, {
      method: 'POST',
      body: new URLSearchParams({ action: 'update', key, cantidad: qty })
    })
      .then(r => r.json())
      .then(data => {
        updateCartCounter(data.total_items);
        updateCartUI(data);
        return data;
      })
      .catch(() => showToast('Error al actualizar el carrito', 'error'));
  }

  function removeFromCart(key) {
    return fetch(API_URL, {
      method: 'POST',
      body: new URLSearchParams({ action: 'remove', key })
    })
      .then(r => r.json())
      .then(data => {
        updateCartCounter(data.total_items);
        updateCartUI(data);
        showToast('Producto eliminado');
        return data;
      })
      .catch(() => showToast('Error al eliminar', 'error'));
  }

  function getCart() {
    return fetch(`${API_URL}?action=get`)
      .then(r => r.json())
      .then(data => { updateCartUI(data); return data; })
      .catch(() => showToast('Error al obtener el carrito', 'error'));
  }

  function applyCoupon(code) {
    return fetch(API_URL, {
      method: 'POST',
      body: new URLSearchParams({ action: 'apply_coupon', codigo: code })
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast(data.message || 'Cupón aplicado');
          updateCartUI(data);
        } else {
          showToast(data.message || 'Cupón inválido', 'error');
        }
        return data;
      })
      .catch(() => showToast('Error al aplicar cupón', 'error'));
  }

  function removeCoupon() {
    return fetch(API_URL, {
      method: 'POST',
      body: new URLSearchParams({ action: 'remove_coupon' })
    })
      .then(r => r.json())
      .then(data => {
        showToast('Cupón removido');
        updateCartUI(data);
        return data;
      })
      .catch(() => showToast('Error al remover cupón', 'error'));
  }

  // Cart counter badge with bounce animation
  function updateCartCounter(count) {
    if (!cartCounter) return;
    cartCounter.textContent = count;
    cartCounter.classList.toggle('hidden', count === 0);
    cartCounter.classList.remove('bounce');
    // Force reflow to restart animation
    void cartCounter.offsetWidth;
    cartCounter.classList.add('bounce');
  }

  // Render full cart page UI
  function updateCartUI(data) {
    const cartContainer = document.querySelector('.cart-items');
    if (!cartContainer || !data.items) return;

    if (data.items.length === 0) {
      cartContainer.innerHTML = '<p class="cart-empty">Tu carrito está vacío.</p>';
    } else {
      cartContainer.innerHTML = data.items.map(item => `
        <div class="cart-item" data-key="${item.key}">
          <img src="${item.imagen}" alt="${item.nombre}" class="cart-item-img">
          <div class="cart-item-info">
            <h4>${item.nombre}</h4>
            ${item.variante ? `<span class="cart-item-variant">${item.variante}</span>` : ''}
            <div class="cart-item-qty">
              <button class="qty-btn qty-minus" data-key="${item.key}">-</button>
              <input type="number" value="${item.cantidad}" min="1" max="${item.stock}" data-key="${item.key}" class="qty-input">
              <button class="qty-btn qty-plus" data-key="${item.key}">+</button>
            </div>
          </div>
          <div class="cart-item-price">$${item.subtotal.toLocaleString('es-AR')}</div>
          <button class="cart-item-remove" data-key="${item.key}">&times;</button>
        </div>
      `).join('');
    }

    // Update totals
    const subtotalEl = document.querySelector('.cart-subtotal');
    const discountEl = document.querySelector('.cart-discount');
    const totalEl = document.querySelector('.cart-total');
    if (subtotalEl) subtotalEl.textContent = `$${data.subtotal.toLocaleString('es-AR')}`;
    if (discountEl) {
      discountEl.textContent = data.descuento ? `-$${data.descuento.toLocaleString('es-AR')}` : '';
      discountEl.closest('.cart-discount-row')?.classList.toggle('hidden', !data.descuento);
    }
    if (totalEl) totalEl.textContent = `$${data.total.toLocaleString('es-AR')}`;

    renderMiniCart(data);
    bindCartEvents();
  }

  // Bind click handlers inside cart (delegation-friendly re-bind)
  function bindCartEvents() {
    document.querySelectorAll('.cart-item-remove').forEach(btn => {
      btn.onclick = () => removeFromCart(btn.dataset.key);
    });
    document.querySelectorAll('.cart-item .qty-minus').forEach(btn => {
      btn.onclick = () => {
        const input = btn.parentElement.querySelector('.qty-input');
        const newQty = Math.max(1, parseInt(input.value) - 1);
        updateCart(btn.dataset.key, newQty);
      };
    });
    document.querySelectorAll('.cart-item .qty-plus').forEach(btn => {
      btn.onclick = () => {
        const input = btn.parentElement.querySelector('.qty-input');
        const newQty = Math.min(parseInt(input.max) || 99, parseInt(input.value) + 1);
        updateCart(btn.dataset.key, newQty);
      };
    });
    document.querySelectorAll('.cart-item .qty-input').forEach(input => {
      input.onchange = () => {
        const val = Math.max(1, Math.min(parseInt(input.max) || 99, parseInt(input.value)));
        updateCart(input.dataset.key, val);
      };
    });
  }

  /* ==========================================================================
     3. MINI-CART DROPDOWN — last 3 items + subtotal
     ========================================================================== */

  function renderMiniCart(data) {
    const container = document.querySelector('.mini-cart-items');
    if (!container || !data.items) return;

    const lastThree = data.items.slice(-3);
    container.innerHTML = lastThree.length === 0
      ? '<p class="mini-cart-empty">Carrito vacío</p>'
      : lastThree.map(item => `
          <div class="mini-cart-item">
            <img src="${item.imagen}" alt="${item.nombre}">
            <div>
              <span class="mini-cart-name">${item.nombre}</span>
              <span class="mini-cart-price">${item.cantidad} x $${item.precio.toLocaleString('es-AR')}</span>
            </div>
          </div>
        `).join('');

    const subtotalEl = document.querySelector('.mini-cart-subtotal');
    if (subtotalEl) subtotalEl.textContent = `Subtotal: $${data.subtotal.toLocaleString('es-AR')}`;
  }

  /* ==========================================================================
     4. PRODUCT GALLERY — thumbnail swap + zoom on hover
     ========================================================================== */

  const mainImage = document.querySelector('.product-main-img');
  const thumbnails = document.querySelectorAll('.product-thumb');

  thumbnails.forEach(thumb => {
    thumb.addEventListener('click', () => {
      if (!mainImage) return;
      thumbnails.forEach(t => t.classList.remove('active'));
      thumb.classList.add('active');
      mainImage.classList.add('fade-out');
      setTimeout(() => {
        mainImage.src = thumb.dataset.src || thumb.src;
        mainImage.alt = thumb.alt || '';
        mainImage.classList.remove('fade-out');
      }, 200);
    });
  });

  // Zoom on hover (CSS transform scale inside overflow:hidden wrapper)
  const zoomWrap = document.querySelector('.product-img-zoom');
  if (zoomWrap && mainImage) {
    zoomWrap.addEventListener('mousemove', (e) => {
      const rect = zoomWrap.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;
      mainImage.style.transformOrigin = `${x}% ${y}%`;
      mainImage.style.transform = 'scale(1.8)';
    });
    zoomWrap.addEventListener('mouseleave', () => {
      mainImage.style.transform = 'scale(1)';
    });
  }

  /* ==========================================================================
     5. VARIANT SELECTOR — highlight active, update price
     ========================================================================== */

  document.querySelectorAll('.variant-option').forEach(option => {
    option.addEventListener('click', () => {
      option.closest('.variant-group').querySelectorAll('.variant-option').forEach(o => o.classList.remove('active'));
      option.classList.add('active');

      const extra = parseFloat(option.dataset.precioExtra) || 0;
      const basePrice = parseFloat(document.querySelector('.product-price')?.dataset.base) || 0;
      const priceEl = document.querySelector('.product-price');
      if (priceEl) priceEl.textContent = `$${(basePrice + extra).toLocaleString('es-AR')}`;
    });
  });

  /* ==========================================================================
     6. QUANTITY INPUT — +/- buttons with min/max
     ========================================================================== */

  document.querySelectorAll('.product-qty').forEach(wrapper => {
    const input = wrapper.querySelector('input[type="number"]');
    const minus = wrapper.querySelector('.qty-minus');
    const plus = wrapper.querySelector('.qty-plus');
    if (!input) return;

    const min = parseInt(input.min) || 1;
    const max = parseInt(input.max) || 99;

    if (minus) minus.addEventListener('click', () => { input.value = Math.max(min, parseInt(input.value) - 1); });
    if (plus) plus.addEventListener('click', () => { input.value = Math.min(max, parseInt(input.value) + 1); });
    input.addEventListener('change', () => { input.value = Math.max(min, Math.min(max, parseInt(input.value) || min)); });
  });

  /* ==========================================================================
     7. WISHLIST TOGGLE — heart icon (product page + card buttons)
     ========================================================================== */

  // Product single page wishlist button
  document.querySelectorAll('.wishlist-btn-single').forEach(btn => {
    btn.addEventListener('click', () => {
      wishlistToggle(btn);
    });
  });

  // Generic .wishlist-btn outside cards (legacy)
  document.querySelectorAll('.wishlist-btn').forEach(btn => {
    if (btn.closest('.product-card__wish')) return;
    btn.addEventListener('click', () => wishlistToggle(btn));
  });

  /* ==========================================================================
     8. PRODUCT FILTERS — categories, price range, sort, view toggle
     ========================================================================== */

  const filterForm = document.querySelector('.product-filters');

  // Category checkboxes
  document.querySelectorAll('.filter-category').forEach(cb => {
    cb.addEventListener('change', () => applyFilters());
  });

  // Price range inputs
  document.querySelectorAll('.filter-price-min, .filter-price-max').forEach(input => {
    input.addEventListener('change', () => applyFilters());
  });

  // Sort select
  const sortSelect = document.querySelector('.filter-sort');
  if (sortSelect) sortSelect.addEventListener('change', () => applyFilters());

  function applyFilters() {
    const params = new URLSearchParams(window.location.search);

    // Categories
    const cats = Array.from(document.querySelectorAll('.filter-category:checked')).map(cb => cb.value);
    params.delete('categoria');
    cats.forEach(c => params.append('categoria', c));

    // Price range
    const minPrice = document.querySelector('.filter-price-min')?.value;
    const maxPrice = document.querySelector('.filter-price-max')?.value;
    if (minPrice) params.set('precio_min', minPrice); else params.delete('precio_min');
    if (maxPrice) params.set('precio_max', maxPrice); else params.delete('precio_max');

    // Sort
    if (sortSelect?.value) params.set('orden', sortSelect.value); else params.delete('orden');

    // Reset to page 1
    params.delete('pagina');

    window.location.search = params.toString();
  }

  // View toggle (grid / list)
  const viewToggles = document.querySelectorAll('.view-toggle-btn');
  const productGrid = document.querySelector('.products-grid');

  viewToggles.forEach(btn => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.view; // 'grid' or 'list'
      viewToggles.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      if (productGrid) {
        productGrid.classList.remove('view-grid', 'view-list');
        productGrid.classList.add(`view-${mode}`);
      }
      localStorage.setItem('dyp_view_mode', mode);
    });
  });

  // Restore saved view preference
  const savedView = localStorage.getItem('dyp_view_mode');
  if (savedView && productGrid) {
    productGrid.classList.remove('view-grid', 'view-list');
    productGrid.classList.add(`view-${savedView}`);
    viewToggles.forEach(b => b.classList.toggle('active', b.dataset.view === savedView));
  }

  /* ==========================================================================
     9. SEARCH — debounced autocomplete
     ========================================================================== */

  const searchInput = document.querySelector('.search-input');
  const searchResults = document.querySelector('.search-autocomplete');
  let searchTimeout = null;

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      const term = searchInput.value.trim();
      if (term.length < 2) { hideSearchResults(); return; }

      searchTimeout = setTimeout(() => {
        fetch(`buscar.php?q=${encodeURIComponent(term)}&ajax=1`)
          .then(r => r.json())
          .then(results => {
            if (!searchResults) return;
            if (results.length === 0) {
              searchResults.innerHTML = '<div class="search-no-results">Sin resultados</div>';
            } else {
              searchResults.innerHTML = results.map(r => `
                <a href="${r.url}" class="search-result-item">
                  <img src="${r.imagen}" alt="${r.nombre}">
                  <div>
                    <span class="search-result-name">${r.nombre}</span>
                    <span class="search-result-price">$${r.precio.toLocaleString('es-AR')}</span>
                  </div>
                </a>
              `).join('');
            }
            searchResults.classList.add('open');
          })
          .catch(() => hideSearchResults());
      }, 300);
    });

    // Enter key — full search page
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const term = searchInput.value.trim();
        if (term) {
          const base = window.SITE_URL || '';
          window.location.href = `${base}/buscar/${encodeURIComponent(term)}`;
        }
      }
    });

    // Close autocomplete on outside click
    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !searchResults?.contains(e.target)) hideSearchResults();
    });
  }

  function hideSearchResults() {
    if (searchResults) searchResults.classList.remove('open');
  }

  /* ==========================================================================
     10. PAGINATION — smooth scroll to top on page link click
     ========================================================================== */

  document.querySelectorAll('.pagination a').forEach(link => {
    link.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  /* ==========================================================================
     11. REVIEWS — star rating + submit via fetch
     ========================================================================== */

  const starsContainer = document.querySelector('.review-stars');
  const ratingInput = document.querySelector('input[name="rating"]');

  if (starsContainer && ratingInput) {
    starsContainer.querySelectorAll('.star').forEach(star => {
      star.addEventListener('click', () => {
        const val = parseInt(star.dataset.value);
        ratingInput.value = val;
        starsContainer.querySelectorAll('.star').forEach(s => {
          s.classList.toggle('filled', parseInt(s.dataset.value) <= val);
        });
      });
    });
  }

  const reviewForm = document.querySelector('.review-form');
  if (reviewForm) {
    reviewForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const formData = new FormData(reviewForm);

      fetch('review_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            showToast('Reseña enviada. Gracias!');
            reviewForm.reset();
            starsContainer?.querySelectorAll('.star').forEach(s => s.classList.remove('filled'));
          } else {
            showToast(data.message || 'Error al enviar reseña', 'error');
          }
        })
        .catch(() => showToast('Error de conexión', 'error'));
    });
  }

  /* ==========================================================================
     12. PRODUCT TABS — click to switch panels
     ========================================================================== */

  document.querySelectorAll('.product-tabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tabGroup = btn.closest('.product-tabs');
      tabGroup.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      tabGroup.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

      btn.classList.add('active');
      const panel = tabGroup.querySelector(`.tab-panel[data-tab="${btn.dataset.tab}"]`);
      if (panel) panel.classList.add('active');
    });
  });

  /* ==========================================================================
     13. TOAST NOTIFICATIONS — slide in from top-right, auto-remove
     ========================================================================== */

  function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const colors = { success: '#28a745', error: '#dc3545', info: '#007bff' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
      background:${colors[type] || colors.success};color:#fff;padding:12px 20px;
      border-radius:6px;margin-bottom:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);
      transform:translateX(120%);transition:transform .3s ease;font-size:14px;
    `;
    toast.textContent = message;
    container.appendChild(toast);

    // Slide in
    requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });

    // Auto-remove after 3s
    setTimeout(() => {
      toast.style.transform = 'translateX(120%)';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  /* ==========================================================================
     14. CHECKOUT FORM — validation + loading state
     ========================================================================== */

  const checkoutForm = document.querySelector('.checkout-form');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', (e) => {
      const required = checkoutForm.querySelectorAll('[required]');
      let valid = true;

      required.forEach(field => {
        field.classList.remove('field-error');
        if (!field.value.trim()) {
          field.classList.add('field-error');
          valid = false;
        }
      });

      // Email format check
      const email = checkoutForm.querySelector('input[type="email"]');
      if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        email.classList.add('field-error');
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
        showToast('Por favor completá todos los campos obligatorios', 'error');
        return;
      }

      // Show loading on pay button
      const payBtn = checkoutForm.querySelector('.btn-pay');
      if (payBtn) {
        payBtn.disabled = true;
        payBtn.dataset.originalText = payBtn.textContent;
        payBtn.textContent = 'Procesando...';
      }
    });
  }

  /* ==========================================================================
     15. SCROLL ANIMATIONS — IntersectionObserver on .fade-in elements
     ========================================================================== */

  const fadeElements = document.querySelectorAll('.fade-in');
  if (fadeElements.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    fadeElements.forEach(el => observer.observe(el));
  }

  /* ==========================================================================
     16. COUPON FIELD — AJAX validation on cart & checkout pages
     ========================================================================== */

  const couponForm = document.querySelector('.coupon-form');
  if (couponForm) {
    const couponInput = couponForm.querySelector('.coupon-input');
    const couponApply = couponForm.querySelector('.coupon-apply');
    const couponRemove = couponForm.querySelector('.coupon-remove');

    if (couponApply) {
      couponApply.addEventListener('click', (e) => {
        e.preventDefault();
        const code = couponInput?.value.trim();
        if (!code) { showToast('Ingresá un código de cupón', 'info'); return; }
        applyCoupon(code);
      });
    }

    if (couponRemove) {
      couponRemove.addEventListener('click', (e) => {
        e.preventDefault();
        removeCoupon();
        if (couponInput) couponInput.value = '';
      });
    }
  }

  /* ==========================================================================
     INIT — Add-to-cart buttons + load cart on cart page
     ========================================================================== */

  // Bind all "Add to Cart" buttons
  document.querySelectorAll('.btn-add-cart').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.productoId;
      const qtyInput = btn.closest('.product-actions')?.querySelector('.qty-input');
      const qty = qtyInput ? parseInt(qtyInput.value) : 1;
      const activeVariant = btn.closest('.product-actions')?.querySelector('.variant-option.active');
      const variante = activeVariant?.dataset.value || null;
      addToCart(id, qty, variante);
    });
  });

  // If on cart page, load current cart
  if (document.querySelector('.cart-items')) getCart();

  /* ==========================================================================
     HERO SLIDER
     ========================================================================== */
  (function() {
    const slides  = document.querySelectorAll('.slide');
    const dots    = document.querySelectorAll('.slider-dot');
    const btnPrev = document.getElementById('sliderPrev');
    const btnNext = document.getElementById('sliderNext');

    if (slides.length <= 1) return;

    let current  = 0;
    let autoplay = null;
    const DELAY  = 5000;

    function goTo(index) {
      slides[current].classList.remove('active');
      if (dots[current]) dots[current].classList.remove('active');
      current = (index + slides.length) % slides.length;
      slides[current].classList.add('active');
      if (dots[current]) dots[current].classList.add('active');
    }

    function startAutoplay() { autoplay = setInterval(() => goTo(current + 1), DELAY); }
    function stopAutoplay()  { clearInterval(autoplay); }

    if (btnPrev) btnPrev.addEventListener('click', () => { stopAutoplay(); goTo(current - 1); startAutoplay(); });
    if (btnNext) btnNext.addEventListener('click', () => { stopAutoplay(); goTo(current + 1); startAutoplay(); });
    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => { stopAutoplay(); goTo(i); startAutoplay(); });
    });

    // Swipe on mobile
    let touchStartX = 0;
    const slider = document.getElementById('heroSlider');
    if (slider) {
      slider.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
      slider.addEventListener('touchend', e => {
        const diff = touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
          stopAutoplay();
          diff > 0 ? goTo(current + 1) : goTo(current - 1);
          startAutoplay();
        }
      });
    }

    startAutoplay();
  })();

  // Expose utilities globally for inline use if needed
  window.dyp = {
    addToCart, removeFromCart, updateCart, getCart, showToast, applyCoupon, removeCoupon,
    openCartDrawer, closeCartDrawer, loadCartDrawer,
    _drawerUpdate: drawerUpdateItem,
    _drawerRemove: drawerRemoveItem
  };

});
