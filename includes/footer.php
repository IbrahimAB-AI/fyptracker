<?php
/**
 * FYPTracker — Shared Footer
 * includes/footer.php
 */
?>
    </div><!-- /.app-layout -->

    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script>
      // ── Icon/theme sync on every authenticated page ──────────────────
      (function () {
        function syncThemeIcon() {
          var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
          var sun  = document.getElementById('icon-sun');
          var moon = document.getElementById('icon-moon');
          if (sun)  sun.style.display  = isDark ? 'none'  : 'block';
          if (moon) moon.style.display = isDark ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
          syncThemeIcon();
          if (typeof lucide !== 'undefined') lucide.createIcons();
        });

        // Patch toggleTheme to also sync icon
        var _orig = window.toggleTheme;
        window.toggleTheme = function () {
          if (_orig) _orig();
          setTimeout(function () {
            syncThemeIcon();
            if (typeof lucide !== 'undefined') lucide.createIcons();
          }, 20);
        };
      })();
    </script>
  </body>
</html>
