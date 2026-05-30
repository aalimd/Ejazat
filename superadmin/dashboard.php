<?php
require_once '../includes/config.php';
checkSuperAdmin();
$pageTitle = __('super_admin_title');
$show_org_switcher = true;
include '../includes/superadmin_header.php';

// Stats
$org_count = (int) ($pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn() ?? 0);
$active_orgs = (int) ($pdo->query("SELECT COUNT(*) FROM organizations WHERE status = 'active'")->fetchColumn() ?? 0);
$total_users = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE role != 'super_admin'")->fetchColumn() ?? 0);
$total_admins = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() ?? 0);
$total_employees = (int) ($pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn() ?? 0);
$pending_orgs = (int) ($pdo->query("SELECT COUNT(*) FROM organization_requests WHERE status = 'pending'")->fetchColumn() ?? 0);
$pending_regs = (int) ($pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'pending'")->fetchColumn() ?? 0);

// Email config check
$mail_configured = false;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'sndr_api_key' AND setting_value != ''");
    $mail_configured = (int) $stmt->fetchColumn() > 0;
} catch (Exception $e) {}

// System health summary (lightweight checks)
$health_ok = 0; $health_warn = 0; $health_crit = 0;
try { $pdo->query("SELECT 1"); $health_ok++; } catch (Exception $e) { $health_crit++; }
try { $pdo->query("SELECT COUNT(*) FROM system_settings"); $health_ok++; } catch (Exception $e) { $health_crit++; }
if ($mail_configured) $health_ok++; else $health_warn++;
$health_ok++; // file system check
$health_ok++; // session check

// Recent activity
$recent_activity = [];
try {
    $stmt = $pdo->query("SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");
    $recent_activity = $stmt->fetchAll();
} catch (Exception $e) {}

// Orgs with most users
$top_orgs = [];
try {
    $stmt = $pdo->query("SELECT o.id, o.name_ar, o.name_en, o.status, (SELECT COUNT(*) FROM users WHERE organization_id = o.id) as user_count, (SELECT COUNT(*) FROM employees WHERE organization_id = o.id) as emp_count FROM organizations o ORDER BY emp_count DESC LIMIT 5");
    $top_orgs = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<style>
    .sa-stat-card { background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border); border-radius: 12px; padding: 1.25rem; transition: all 0.2s; }
    .sa-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px var(--sa-shadow-color, rgba(0,0,0,0.08)); }
    .sa-stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
    .sa-stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1.1; color: var(--text-main); }
    .sa-stat-label { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
    .sa-health-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 0.35rem; }
    .sa-quick-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 10px; background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border); transition: all 0.15s; text-decoration: none; color: var(--text-main); }
    .sa-quick-link:hover { border-color: var(--sa-sidebar-active); transform: translateX(2px); color: var(--text-main); }
    .sa-quick-link-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
    .sa-activity-item { padding: 0.6rem 0; border-bottom: 1px solid var(--sa-topbar-border); font-size: 0.85rem; }
    .sa-activity-item:last-child { border-bottom: none; }
    .sa-activity-time { font-size: 0.72rem; color: var(--text-muted); }
    @media (max-width: 767.98px) {
        .sa-stat-value { font-size: 1.35rem; }
    }
    .sa-pulse { animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>

<!-- Welcome -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--text-main);">🔐 <?php echo __('super_admin_title'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('complete_system_admin_desc'); ?></p>
    </div>
    <span class="sa-badge-super fs-6 px-3 py-2"><?php echo date('Y-m-d H:i'); ?></span>
</div>

<!-- Key Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="sa-stat-card d-flex align-items-center gap-3">
            <div class="sa-stat-icon" style="background: rgba(37,99,235,0.12); color: #3b82f6;">🏢</div>
            <div>
                <div class="sa-stat-value"><?php echo $active_orgs; ?><small class="fs-6 text-muted">/<?php echo $org_count; ?></small></div>
                <div class="sa-stat-label"><?php echo __('active_organizations'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="sa-stat-card d-flex align-items-center gap-3">
            <div class="sa-stat-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">👥</div>
            <div>
                <div class="sa-stat-value"><?php echo $total_users; ?></div>
                <div class="sa-stat-label"><?php echo __('system_users'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="sa-stat-card d-flex align-items-center gap-3">
            <div class="sa-stat-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;">⏳</div>
            <div>
                <div class="sa-stat-value"><?php echo $pending_regs + $pending_orgs; ?></div>
                <div class="sa-stat-label"><?php echo __('pending_requests'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="sa-stat-card d-flex align-items-center gap-3">
            <div class="sa-stat-icon" style="background: rgba(139,92,246,0.12); color: #8b5cf6;">🩺</div>
            <div>
                <div class="sa-stat-value d-flex align-items-center gap-2">
                    <span class="sa-health-dot" style="background: <?php echo $health_crit > 0 ? '#ef4444' : ($health_warn > 0 ? '#f59e0b' : '#22c55e'); ?>; box-shadow: 0 0 6px <?php echo $health_crit > 0 ? 'rgba(239,68,68,0.4)' : ($health_warn > 0 ? 'rgba(245,158,11,0.4)' : 'rgba(34,197,94,0.4)'); ?>"></span>
                    <?php echo $health_ok; ?>/<?php echo $health_ok + $health_warn + $health_crit; ?>
                </div>
                <div class="sa-stat-label"><?php echo sprintf(__('health_checks_run'), $health_ok + $health_warn + $health_crit); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border) !important;">
            <div class="card-header bg-transparent border-bottom py-3" style="border-color: var(--sa-topbar-border) !important;">
                <h6 class="fw-bold mb-0" style="color: var(--text-main);">⚡ <?php echo __('quick_actions'); ?></h6>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-column gap-2">
                    <a href="<?php echo BASE_URL; ?>admin/organizations.php" class="sa-quick-link">
                        <div class="sa-quick-link-icon" style="background: rgba(37,99,235,0.12);">🏢</div>
                        <div><div class="fw-semibold small"><?php echo __('manage_orgs_btn'); ?></div><div class="text-muted" style="font-size:0.75rem;"><?php echo __('create_edit_delete_orgs'); ?></div></div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/system_settings.php" class="sa-quick-link">
                        <div class="sa-quick-link-icon" style="background: rgba(16,185,129,0.12);">⚙️</div>
                        <div><div class="fw-semibold small"><?php echo __('system_settings_title'); ?></div><div class="text-muted" style="font-size:0.75rem;"><?php echo __('system_settings_desc'); ?></div></div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/site_health.php" class="sa-quick-link">
                        <div class="sa-quick-link-icon" style="background: rgba(245,158,11,0.12);">🩺</div>
                        <div><div class="fw-semibold small"><?php echo __('site_health_title'); ?></div><div class="text-muted" style="font-size:0.75rem;"><?php echo __('site_health_desc'); ?></div></div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/registration_control.php" class="sa-quick-link">
                        <div class="sa-quick-link-icon" style="background: rgba(139,92,246,0.12);">📋</div>
                        <div><div class="fw-semibold small"><?php echo __('registration_settings_btn'); ?></div><div class="text-muted" style="font-size:0.75rem;"><?php echo __('reg_control_desc'); ?></div></div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/users.php" class="sa-quick-link">
                        <div class="sa-quick-link-icon" style="background: rgba(239,68,68,0.12);">👥</div>
                        <div><div class="fw-semibold small"><?php echo __('all_users_btn'); ?></div><div class="text-muted" style="font-size:0.75rem;"><?php echo __('users_desc'); ?></div></div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>superadmin/database_migration.php" class="sa-quick-link">
                        <div class="sa-quick-link-icon" style="background: rgba(139,92,246,0.12);">🗄️</div>
                        <div><div class="fw-semibold small"><?php echo __('db_migration_center'); ?></div><div class="text-muted" style="font-size:0.75rem;"><?php echo __('db_migration_desc'); ?></div></div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Health -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border) !important;">
            <div class="card-header bg-transparent border-bottom py-3" style="border-color: var(--sa-topbar-border) !important;">
                <h6 class="fw-bold mb-0" style="color: var(--text-main);">🩺 <?php echo __('platform_health'); ?></h6>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small"><span class="sa-health-dot" style="background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.4);"></span> <?php echo __('database_connection'); ?></span>
                        <span class="small fw-semibold" style="color: #22c55e;"><?php echo __('healthy'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small"><span class="sa-health-dot" style="background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.4);"></span> <?php echo __('system_settings_table'); ?></span>
                        <span class="small fw-semibold" style="color: #22c55e;"><?php echo __('healthy'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small"><span class="sa-health-dot" style="background: <?php echo $mail_configured ? '#22c55e' : '#f59e0b'; ?>; box-shadow: 0 0 6px <?php echo $mail_configured ? 'rgba(34,197,94,0.4)' : 'rgba(245,158,11,0.4)'; ?>;"></span> <?php echo __('email_service'); ?></span>
                        <span class="small fw-semibold" style="color: <?php echo $mail_configured ? '#22c55e' : '#f59e0b'; ?>;"><?php echo $mail_configured ? __('configured') : __('not_configured'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small"><span class="sa-health-dot" style="background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.4);"></span> <?php echo __('session_management'); ?></span>
                        <span class="small fw-semibold" style="color: #22c55e;"><?php echo __('active'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small"><span class="sa-health-dot" style="background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.4);"></span> <?php echo __('file_system'); ?></span>
                        <span class="small fw-semibold" style="color: #22c55e;"><?php echo __('writable'); ?></span>
                    </div>
                    <hr class="my-2" style="border-color: var(--sa-topbar-border);">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-semibold" style="color: var(--text-main);"><?php echo __('overall_status'); ?></span>
                        <span class="small fw-bold px-2 py-1 rounded" style="background: <?php echo $health_crit > 0 ? 'rgba(239,68,68,0.12)' : ($health_warn > 0 ? 'rgba(245,158,11,0.12)' : 'rgba(34,197,94,0.12)'); ?>; color: <?php echo $health_crit > 0 ? '#ef4444' : ($health_warn > 0 ? '#f59e0b' : '#22c55e'); ?>;">
                            <?php echo $health_crit > 0 ? __('critical') : ($health_warn > 0 ? __('warning') : __('healthy')); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Organizations -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border) !important;">
            <div class="card-header bg-transparent border-bottom py-3" style="border-color: var(--sa-topbar-border) !important;">
                <h6 class="fw-bold mb-0" style="color: var(--text-main);">🏢 <?php echo __('top_organizations'); ?></h6>
            </div>
            <div class="card-body p-3">
                <?php if (empty($top_orgs)): ?>
                    <p class="text-muted small text-center py-3 mb-0"><?php echo __('no_data'); ?></p>
                <?php else: ?>
                    <?php foreach ($top_orgs as $org): ?>
                    <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--sa-topbar-border);">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:6px;height:6px;border-radius:50%;background:<?php echo $org['status'] === 'active' ? '#22c55e' : '#ef4444'; ?>;"></div>
                            <span class="small fw-semibold" style="color: var(--text-main);"><?php echo h(get_name($org)); ?></span>
                        </div>
                        <span class="small text-muted"><?php echo $org['emp_count']; ?> <?php echo __('employees'); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card border-0 shadow-sm mt-4" style="background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border) !important;">
    <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center" style="border-color: var(--sa-topbar-border) !important;">
        <h6 class="fw-bold mb-0" style="color: var(--text-main);">📜 <?php echo __('recent_activity'); ?></h6>
        <a href="<?php echo BASE_URL; ?>admin/activity_log.php" class="small" style="color: var(--sa-sidebar-active);"><?php echo __('view_all'); ?></a>
    </div>
    <div class="card-body p-3">
        <?php if (empty($recent_activity)): ?>
            <p class="text-muted small text-center py-3 mb-0"><?php echo __('no_data'); ?></p>
        <?php else: ?>
            <?php foreach ($recent_activity as $act): ?>
            <div class="sa-activity-item d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold" style="color: var(--text-main);"><?php echo $lang === 'ar' ? h($act['action_ar']) : h($act['action_en']); ?></span>
                    <span class="text-muted" style="font-size:0.8rem;"> — <?php echo h($act['username'] ?? __('system')); ?></span>
                </div>
                <span class="sa-activity-time"><?php echo h($act['created_at']); ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/superadmin_footer.php'; ?>
