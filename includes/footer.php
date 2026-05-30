        </main>
    </div>
</div>

<?php 
global $lang;
$footer_text = ($lang == 'en') ? getSetting('footer_text_en', 'All Rights Reserved') : getSetting('footer_text_ar', 'جميع الحقوق محفوظة');
?>
<footer class="footer mt-auto py-3 bg-white border-top">
    <div class="container text-center">
        <span class="text-muted small">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - <?php echo $footer_text; ?></span>
    </div>
</footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/script.js"></script>
    <script>
    document.addEventListener('submit', function(e) {
        const btn = e.target.querySelector('button[type="submit"]');
        if (btn && btn.disabled) { e.preventDefault(); return; }
        if (btn) { setTimeout(function() { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + btn.textContent.trim(); }, 0); }
    });
    </script>
</body>
</html>
