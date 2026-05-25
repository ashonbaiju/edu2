    </main><!-- /page-content -->
</div><!-- /main-layout -->
</div><!-- /app-wrapper -->

<script src="<?= BASE_URL ?>js/dashboard.js"></script>
<script>
// #region agent log
(function () {
    if (typeof window.openModal !== 'function') {
        window.openModal = function (id) {
            var el = document.getElementById(id);
            if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
        };
    }
    if (typeof window.closeModal !== 'function') {
        window.closeModal = function (id) {
            var el = document.getElementById(id);
            if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
        };
    }
})();
// #endregion
</script>
</body>
</html>
