    </main>

    <footer class="sa-footer border-top py-3 mt-auto" style="margin-inline-start: var(--sa-sidebar-width); background: var(--sa-topbar-bg); border-color: var(--sa-topbar-border) !important;">
        <div class="footer-glow" style="background: linear-gradient(90deg, transparent, #3b82f6, transparent);"></div>
        <div class="container-fluid pt-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small text-muted">&copy; <?php echo date('Y'); ?> <?php echo h(SITE_NAME); ?> — <?php echo __('super_admin_title'); ?></span>
                <span class="small text-muted d-flex align-items-center gap-2">
                    <a href="<?php echo BASE_URL; ?>index.php" class="footer-link small text-decoration-none">
                        <span class="emoji-icon">🏠</span> <?php echo __('back_to_app'); ?>
                    </a>
                    <span class="footer-dot">•</span>
                    <span><span class="emoji-icon">💾</span> v1.4</span>
                </span>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/script.js"></script>
    </body>
    </html>
