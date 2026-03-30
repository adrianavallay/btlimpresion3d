    </div><!-- /.admin-content -->
  </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<script>
document.addEventListener('DOMContentLoaded', function() {
  var toggle   = document.getElementById('sidebarToggle');
  var sidebar  = document.getElementById('adminSidebar');
  var overlay  = document.getElementById('sidebarOverlay');
  var closeBtn = document.getElementById('sidebarClose');

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }

  if (toggle)   toggle.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlay)  overlay.addEventListener('click', closeSidebar);

  // Sidebar dropdown toggles
  document.querySelectorAll('.sidebar-dropdown-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
      this.closest('.sidebar-dropdown').classList.toggle('open');
    });
  });

  // Sidebar collapse/expand
  var collapseBtn = document.getElementById('sidebarCollapseBtn');
  var layout = document.querySelector('.admin-layout');
  var savedState = localStorage.getItem('sidebar_collapsed');

  if (savedState === 'true' && window.innerWidth > 768) {
    sidebar.classList.add('collapsed');
    layout.classList.add('collapsed');
  }

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      layout.classList.toggle('collapsed');
      var isCollapsed = sidebar.classList.contains('collapsed');
      localStorage.setItem('sidebar_collapsed', isCollapsed);
    });
  }
});
</script>
