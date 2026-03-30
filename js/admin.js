/* ============================================
   DyP Consultora — Admin Panel JavaScript
   Vanilla JS — No dependencies (except Chart.js)
   ============================================ */

document.addEventListener('DOMContentLoaded', function () {

  // ─── 1. Mobile Menu Toggle ───────────────────────────────
  var hamburger = document.querySelector('.admin-navbar__hamburger');
  var sidebar = document.querySelector('.admin-sidebar');

  if (hamburger && sidebar) {
    hamburger.addEventListener('click', function () {
      hamburger.classList.toggle('open');
      sidebar.classList.toggle('open');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') &&
          !sidebar.contains(e.target) &&
          !hamburger.contains(e.target)) {
        sidebar.classList.remove('open');
        hamburger.classList.remove('open');
      }
    });
  }

  // ─── Admin Hamburger Menu ──────────────────────────────────
  var adminHamburger = document.getElementById('adminHamburger');
  var adminNavLinks = document.getElementById('adminNavLinks');
  if (adminHamburger && adminNavLinks) {
    adminHamburger.addEventListener('click', function () {
      adminNavLinks.classList.toggle('open');
    });
  }

  // ─── Theme Toggle ─────────────────────────────────────────
  var themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-theme') || 'light';
      var next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
    });
  }

  // ─── 6. Table Search ─────────────────────────────────────
  var searchInputs = document.querySelectorAll('[data-table-search]');
  searchInputs.forEach(function (input) {
    var tableId = input.getAttribute('data-table-search');
    var table = document.getElementById(tableId);
    if (!table) return;

    input.addEventListener('input', function () {
      var query = this.value.toLowerCase().trim();
      var rows = table.querySelectorAll('tbody tr');
      rows.forEach(function (row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
      });
    });
  });

  // ─── 7. Tab Switching ────────────────────────────────────
  var tabContainers = document.querySelectorAll('.admin-tabs');
  tabContainers.forEach(function (tabs) {
    var buttons = tabs.querySelectorAll('.admin-tabs__item');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = this.getAttribute('data-tab');
        // Deactivate siblings
        buttons.forEach(function (b) { b.classList.remove('active'); });
        this.classList.add('active');
        // Show target pane
        var wrapper = this.closest('.admin-tabs-wrapper');
        var panes = wrapper
          ? wrapper.querySelectorAll('.tab-pane')
          : document.querySelectorAll('.tab-pane');
        panes.forEach(function (p) {
          p.classList.toggle('active', p.id === target);
        });
      });
    });
  });

  // ─── 8. Image Upload Preview ─────────────────────────────
  var fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
  fileInputs.forEach(function (input) {
    input.addEventListener('change', function () {
      var previewId = this.getAttribute('data-preview');
      var preview = document.getElementById(previewId);
      if (!preview) return;
      preview.innerHTML = '';

      Array.from(this.files).forEach(function (file) {
        if (!file.type.startsWith('image/')) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          var item = document.createElement('div');
          item.className = 'file-preview__item';
          item.innerHTML = '<img src="' + e.target.result + '" alt="preview">' +
            '<button type="button" class="file-preview__remove" onclick="this.parentElement.remove()">&times;</button>';
          preview.appendChild(item);
        };
        reader.readAsDataURL(file);
      });
    });
  });

  // ─── 10. Slug Auto-generation ────────────────────────────
  var nameInput = document.querySelector('[data-slug-source]');
  var slugInput = document.querySelector('[data-slug-target]');
  if (nameInput && slugInput) {
    nameInput.addEventListener('input', function () {
      slugInput.value = this.value
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    });
  }

  // ─── 12. Flash Message Auto-dismiss ──────────────────────
  var alerts = document.querySelectorAll('.alert[data-autodismiss]');
  alerts.forEach(function (alert) {
    var delay = parseInt(alert.getAttribute('data-autodismiss')) || 5000;
    setTimeout(function () {
      dismissAlert(alert);
    }, delay);
  });

  document.querySelectorAll('.alert__close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      dismissAlert(this.closest('.alert'));
    });
  });

  function dismissAlert(el) {
    if (!el || el.classList.contains('dismissing')) return;
    el.classList.add('dismissing');
    setTimeout(function () { el.remove(); }, 300);
  }

  // ─── 13. Select All Checkbox ─────────────────────────────
  var selectAllBoxes = document.querySelectorAll('[data-select-all]');
  selectAllBoxes.forEach(function (master) {
    var tableId = master.getAttribute('data-select-all');
    var table = document.getElementById(tableId);
    if (!table) return;

    master.addEventListener('change', function () {
      var checked = this.checked;
      table.querySelectorAll('tbody input[type="checkbox"]').forEach(function (cb) {
        cb.checked = checked;
      });
    });
  });

  // ─── 14. Sortable Table Headers ──────────────────────────
  document.querySelectorAll('.admin-table th.sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var table = this.closest('table');
      var tbody = table.querySelector('tbody');
      var index = Array.from(this.parentNode.children).indexOf(this);
      var rows = Array.from(tbody.querySelectorAll('tr'));
      var asc = !this.classList.contains('sort-asc');

      // Reset all headers in this table
      table.querySelectorAll('th.sortable').forEach(function (h) {
        h.classList.remove('sort-asc', 'sort-desc');
      });
      this.classList.add(asc ? 'sort-asc' : 'sort-desc');

      rows.sort(function (a, b) {
        var aVal = (a.children[index] ? a.children[index].textContent.trim() : '');
        var bVal = (b.children[index] ? b.children[index].textContent.trim() : '');
        var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
        var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));

        if (!isNaN(aNum) && !isNaN(bNum)) {
          return asc ? aNum - bNum : bNum - aNum;
        }
        return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
      });

      rows.forEach(function (row) { tbody.appendChild(row); });
    });
  });

  // ─── Drag-and-drop styling for file upload ───────────────
  document.querySelectorAll('.file-upload').forEach(function (zone) {
    var input = zone.querySelector('input[type="file"]');
    zone.addEventListener('click', function () { if (input) input.click(); });

    ['dragenter', 'dragover'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
      });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
      });
    });

    zone.addEventListener('drop', function (e) {
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    });
  });

});


/* ==========================================================
   Global functions (available outside DOMContentLoaded)
   ========================================================== */

// ─── 2. Chart.js Initialization ──────────────────────────────
// Requires Chart.js to be loaded before this script

function initSalesChart(canvasId, labels, data) {
  var canvas = document.getElementById(canvasId);
  if (!canvas) return null;
  var ctx = canvas.getContext('2d');

  var gradient = ctx.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(131, 161, 104, 0.25)');
  gradient.addColorStop(1, 'rgba(131, 161, 104, 0)');

  return new Chart(canvas, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Ventas',
        data: data,
        borderColor: '#83a168',
        backgroundColor: gradient,
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#83a168',
        pointBorderColor: '#fff',
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: _adminChartOptions()
  });
}

function initCategoryChart(canvasId, labels, data) {
  var canvas = document.getElementById(canvasId);
  if (!canvas) return null;

  var colors = ['#83a168', '#4a90a4', '#e6a817', '#c0392b', '#6b8a52', '#d4a574', '#7a9e9f', '#b8860b'];

  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Productos',
        data: data,
        backgroundColor: colors.slice(0, labels.length),
        borderRadius: 6,
        barThickness: 22
      }]
    },
    options: Object.assign({}, _adminChartOptions(), {
      indexAxis: 'y',
      plugins: { legend: { display: false } }
    })
  });
}

function initStatusChart(canvasId, labels, data, colors) {
  var canvas = document.getElementById(canvasId);
  if (!canvas) return null;

  return new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{
        data: data,
        backgroundColor: colors,
        borderWidth: 0,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#999', padding: 16, font: { family: 'Jost', size: 12 } }
        }
      }
    }
  });
}

function _adminChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        grid: { color: '#e5e3de', drawBorder: false },
        ticks: { color: '#999', font: { family: 'Jost', size: 11 } }
      },
      y: {
        grid: { color: '#e5e3de', drawBorder: false },
        ticks: { color: '#999', font: { family: 'Jost', size: 11 } }
      }
    },
    plugins: {
      legend: {
        labels: { color: '#555', font: { family: 'Jost', size: 12 } }
      },
      tooltip: {
        backgroundColor: '#fff',
        titleColor: '#222',
        bodyColor: '#555',
        borderColor: '#e5e3de',
        borderWidth: 1,
        cornerRadius: 4,
        padding: 12,
        titleFont: { family: 'Jost' },
        bodyFont: { family: 'Jost' }
      }
    }
  };
}

// ─── 3. Modal Open / Close ───────────────────────────────────

function openModal(id) {
  var modal = document.getElementById(id);
  if (modal) modal.classList.add('open');
}

function closeModal(id) {
  var modal = document.getElementById(id);
  if (modal) modal.classList.remove('open');
}

// Close modal on outside click
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay') && e.target.classList.contains('open')) {
    e.target.classList.remove('open');
  }
});

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
      m.classList.remove('open');
    });
  }
});

// ─── 4. Toggle Switches (AJAX POST) ─────────────────────────

function handleToggle(checkbox, url) {
  var value = checkbox.checked ? 1 : 0;
  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'value=' + value
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    if (!data.success) checkbox.checked = !checkbox.checked;
  })
  .catch(function () {
    checkbox.checked = !checkbox.checked;
  });
}

// ─── 5. Confirm Delete ──────────────────────────────────────

function confirmDelete(url, name) {
  var modal = document.getElementById('deleteModal');
  if (!modal) return;

  var nameEl = modal.querySelector('[data-delete-name]');
  var confirmBtn = modal.querySelector('[data-delete-confirm]');
  if (nameEl) nameEl.textContent = name;
  if (confirmBtn) confirmBtn.setAttribute('href', url);

  openModal('deleteModal');
}

// ─── 9. Dynamic Variant Rows ────────────────────────────────

function addVariantRow() {
  var tbody = document.querySelector('.variants-table tbody');
  if (!tbody) return;

  var row = document.createElement('tr');
  row.innerHTML =
    '<td><input type="text" name="variant_name[]" class="form-control" placeholder="Ej: Talle M"></td>' +
    '<td><input type="text" name="variant_sku[]" class="form-control" placeholder="SKU"></td>' +
    '<td><input type="number" name="variant_price[]" class="form-control" placeholder="0.00" step="0.01"></td>' +
    '<td><input type="number" name="variant_stock[]" class="form-control" placeholder="0" min="0"></td>' +
    '<td><button type="button" class="btn btn--danger btn--sm" onclick="removeVariantRow(this)">Eliminar</button></td>';
  tbody.appendChild(row);
}

function removeVariantRow(btn) {
  var row = btn.closest('tr');
  if (row) row.remove();
}

// ─── 11. CSV Export ─────────────────────────────────────────

function exportCSV(tableId, filename) {
  var table = document.getElementById(tableId);
  if (!table) return;

  var csv = [];
  var rows = table.querySelectorAll('tr');
  rows.forEach(function (row) {
    var cols = [];
    row.querySelectorAll('th, td').forEach(function (cell) {
      var text = cell.textContent.trim().replace(/"/g, '""');
      cols.push('"' + text + '"');
    });
    csv.push(cols.join(','));
  });

  var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename || 'export.csv';
  link.click();
  URL.revokeObjectURL(link.href);
}
