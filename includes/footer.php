        </main>
    </div>
</div>

<?php 
global $lang;
$footer_text = ($lang == 'en') ? getSetting('footer_text_en', 'All Rights Reserved') : getSetting('footer_text_ar', 'جميع الحقوق محفوظة');
$primary_hex = sanitizeCssValue(getSetting('primary_color', '#0d6efd'));
?>
<footer class="app-footer mt-auto">
    <div class="footer-glow" style="background: linear-gradient(90deg, transparent, <?php echo $primary_hex; ?>, transparent);"></div>
    <div class="container py-4">
        <div class="row align-items-center g-3">
            <div class="col-md-4 text-center text-md-start">
                <a class="footer-brand d-inline-flex align-items-center gap-2 text-decoration-none" href="<?php echo BASE_URL; ?>index.php">
                    <span class="brand-mark brand-mark-sm d-flex align-items-center justify-content-center"><span class="emoji-icon">🏢</span></span>
                    <span class="fw-bold"><?php echo SITE_NAME; ?></span>
                </a>
            </div>
            <div class="col-md-4 text-center">
                <span class="footer-text small text-muted">&copy; <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?> — <?php echo $footer_text; ?></span>
            </div>
            <div class="col-md-4 text-center text-md-end d-flex justify-content-center justify-content-md-end gap-1">
                <?php if (isLoggedIn()): ?>
                <a href="<?php echo BASE_URL; ?>auth/security.php" class="footer-link small" title="<?php echo __('security_settings'); ?>">
                    <span class="emoji-icon">🔑</span> <?php echo __('security_settings'); ?>
                </a>
                <span class="footer-dot">•</span>
                <?php endif; ?>
                <span class="footer-link small text-muted">
                    <span class="emoji-icon">💾</span> v1.4
                </span>
            </div>
        </div>
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
