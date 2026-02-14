    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        var sidebar = document.getElementById('sidebar');
        var topBar = document.getElementById('topBar');
        var mainContent = document.getElementById('mainContent');
        var overlay = document.getElementById('sidebarOverlay');
        var btn = document.getElementById('btnHamburger');
        function toggle() {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('show', sidebar.classList.contains('mobile-open'));
            } else {
                sidebar.classList.toggle('collapsed');
                topBar.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
                mainContent.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
            }
        }
        if (btn) btn.addEventListener('click', toggle);
        if (overlay) overlay.addEventListener('click', function() { sidebar.classList.remove('mobile-open'); overlay.classList.remove('show'); });
    })();
    </script>
    <?php displayAlert(); ?>
    <?php if (isset($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>
