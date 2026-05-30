<?php
require_once '../includes/config.php';

checkAuth(['super_admin']);

$error = '';
$success = '';
$system_org_id = 1;

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
    
    // Toggle registration globally
    if ($_POST['action'] === 'toggle_registration') {
        $enabled = isset($_POST['registration_enabled']) ? '1' : '0';
        $org_ids = $pdo->query("SELECT id FROM organizations")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT INTO settings (organization_id, setting_key, setting_value) 
                              VALUES (?, 'allow_registration', ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($org_ids as $org_id) {
            $stmt->execute([(int)$org_id, $enabled]);
        }
        $success = $enabled ? __('reg_enabled_msg') : __('reg_disabled_msg');
    }
    
    // Toggle email verification requirement
    elseif ($_POST['action'] === 'toggle_email_verification') {
        $required = isset($_POST['email_verification_required']) ? '1' : '0';
        $org_ids = $pdo->query("SELECT id FROM organizations")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT INTO settings (organization_id, setting_key, setting_value) 
                              VALUES (?, 'email_verification_required', ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($org_ids as $org_id) {
            $stmt->execute([(int)$org_id, $required]);
        }
        $success = $required ? __('email_verif_required_msg') : __('email_verif_not_required_msg');
    }
    
    // Update organization registration settings
    elseif ($_POST['action'] === 'update_org_settings' && isset($_POST['org_id'])) {
        $org_id = intval($_POST['org_id']);
        $is_public = isset($_POST['org_is_public']) ? 1 : 0;
        $requires_code = isset($_POST['org_requires_code']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE organizations 
                              SET is_public = ?, requires_invitation_code = ? 
                              WHERE id = ?");
        $stmt->execute([$is_public, $requires_code, $org_id]);
        $success = __('org_settings_updated');
    }
    
    // Approve pending registration
    elseif ($_POST['action'] === 'approve_registration' && isset($_POST['employee_id'])) {
        $employee_id = intval($_POST['employee_id']);
        $stmt = $pdo->prepare("UPDATE employees SET status = 'approved', rejection_reason = NULL, decision_date = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$employee_id]);
        $success = __('reg_approved_msg');
    }
    
    // Reject/Delete pending registration
    elseif ($_POST['action'] === 'reject_registration' && isset($_POST['employee_id'])) {
        $employee_id = intval($_POST['employee_id']);
        $stmt = $pdo->prepare("UPDATE employees SET status = 'rejected', rejection_reason = 'Rejected by Super Admin', decision_date = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$employee_id]);
        $success = __('reg_rejected_msg');
    }
    
    // Block user from registration
    elseif ($_POST['action'] === 'block_user' && isset($_POST['email'])) {
        $email = trim($_POST['email']);
        $stmt = $pdo->prepare("INSERT INTO settings (organization_id, setting_key, setting_value) 
                              VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$system_org_id, 'blocked_email_' . $email, '1']);
        $success = __('email_blocked_msg');
    }
    
    // Unblock email
    elseif ($_POST['action'] === 'unblock_email' && isset($_POST['email'])) {
        $email = trim($_POST['email']);
        $stmt = $pdo->prepare("DELETE FROM settings WHERE organization_id = ? AND setting_key = ?");
        $stmt->execute([$system_org_id, 'blocked_email_' . $email]);
        $success = __('email_unblocked_msg');
    }
    }
}

// Get current settings
$registration_enabled = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'allow_registration' AND setting_value = '0'")->fetchColumn() == 0;
$email_verification_required = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'email_verification_required' AND setting_value = '0'")->fetchColumn() == 0;

// Get all organizations with registration settings
$orgs_stmt = $pdo->query("SELECT id, name_ar, name_en, is_public, requires_invitation_code, 
                                  created_at, (SELECT COUNT(*) FROM users WHERE organization_id = organizations.id) as user_count 
                          FROM organizations ORDER BY name_ar ASC");
$organizations = $orgs_stmt->fetchAll();

// Get pending registrations from employee approval queue
$pending_stmt = $pdo->query("SELECT e.id AS employee_id, e.full_name, e.organization_id, e.created_at,
                                    u.username, u.email, o.name_ar, o.name_en
                            FROM employees e
                            JOIN users u ON e.user_id = u.id
                            LEFT JOIN organizations o ON e.organization_id = o.id 
                            WHERE e.status = 'pending'
                            ORDER BY e.created_at DESC LIMIT 10");
$pending_users = $pending_stmt->fetchAll();

// Get registration statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$verified_users = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'approved'")->fetchColumn();
$pending_count = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'pending'")->fetchColumn();

$pageTitle = __('reg_control_title');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<style>
    .reg-stat-box { background: var(--card-bg, #fff); border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .reg-stat-number { font-size: 2.5rem; font-weight: bold; color: var(--primary-color, #667eea); }
    .reg-stat-label { font-size: 0.9rem; }
    .control-section { background: var(--card-bg, #fff); border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .org-card { background: var(--card-bg, #f8f9fa); border-left: 4px solid var(--primary-color, #667eea); padding: 15px; margin-bottom: 10px; border-radius: 5px; }
    .badge-public { background: #28a745; } .badge-private { background: #dc3545; }
    .pending-item { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 10px; border-radius: 5px; }
    table { margin-top: 15px; }
    .btn-sm-custom { padding: 5px 10px; font-size: 0.85rem; }
    [data-theme="dark"] .reg-stat-number[style*="color: #28a745"] {
        color: #75b798 !important;
    }
    [data-theme="dark"] .reg-stat-number[style*="color: #ffc107"] {
        color: #ffda6a !important;
    }
    [data-theme="dark"] .badge-public { background: rgba(25, 135, 84, 0.3); }
    [data-theme="dark"] .badge-private { background: rgba(220, 53, 69, 0.3); }
    [data-theme="dark"] .org-card { background: rgba(30, 41, 59, 0.7); }
    [data-theme="dark"] .pending-item { background: rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.4); }
    [data-theme="dark"] .pending-item strong { color: #ffda6a; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">🎯 <?php echo __('reg_control_title'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('reg_control_desc_text'); ?></p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="reg-stat-box text-center">
            <div class="reg-stat-number"><?php echo $total_users; ?></div>
            <div class="reg-stat-label text-muted"><?php echo __('total_users_stat'); ?></div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="reg-stat-box text-center">
            <div class="reg-stat-number" style="color: #28a745;"><?php echo $verified_users; ?></div>
            <div class="reg-stat-label text-muted"><?php echo __('verified_users'); ?></div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="reg-stat-box text-center">
            <div class="reg-stat-number" style="color: #ffc107;"><?php echo $pending_count; ?></div>
            <div class="reg-stat-label text-muted"><?php echo __('pending_verification'); ?></div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="reg-stat-box text-center">
            <div class="reg-stat-number" style="color: var(--primary-color, #667eea);"><?php echo count($organizations); ?></div>
            <div class="reg-stat-label text-muted"><?php echo __('organizations_stat'); ?></div>
        </div>
    </div>
</div>

<!-- Global Registration Settings -->
<div class="control-section">
    <h5 class="fw-bold mb-4">⚙️ <?php echo __('global_reg_settings'); ?></h5>
    
    <form method="POST" class="mb-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="toggle_registration">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="registration_enabled" id="registration_enabled" 
                   <?php echo $registration_enabled ? 'checked' : ''; ?> onchange="this.form.submit();">
            <label class="form-check-label" for="registration_enabled">
                <strong><?php echo __('allow_public_reg'); ?></strong>
                <small class="d-block text-muted mt-1"><?php echo __('reg_disabled_desc'); ?></small>
            </label>
        </div>
    </form>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="toggle_email_verification">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="email_verification_required" id="email_verification_required" 
                   <?php echo $email_verification_required ? 'checked' : ''; ?> onchange="this.form.submit();">
            <label class="form-check-label" for="email_verification_required">
                <strong><?php echo __('require_email_verif'); ?></strong>
                <small class="d-block text-muted mt-1"><?php echo __('email_verif_desc'); ?></small>
            </label>
        </div>
    </form>
</div>

<!-- Organizations Registration Settings -->
<div class="control-section">
    <h5 class="fw-bold mb-4">🏢 <?php echo __('org_reg_settings'); ?></h5>
    
    <?php foreach ($organizations as $org): ?>
        <div class="org-card">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h6 class="mb-1"><?php echo h(get_name($org)); ?></h6>
                    <small class="text-muted">👥 <?php echo $org['user_count']; ?> <?php echo __('users_count'); ?></small>
                </div>
                <div class="col-md-4">
                    <span class="badge <?php echo $org['is_public'] ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $org['is_public'] ? '🌐 ' . __('public') : '🔒 ' . __('private'); ?>
                    </span>
                    <?php if ($org['requires_invitation_code']): ?>
                        <span class="badge bg-warning text-dark">🔐 <?php echo __('code_required'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editOrgModal<?php echo $org['id']; ?>">
                        ⚙️ <?php echo __('edit_settings'); ?>
                    </button>
                </div>
            </div>

            <div class="modal fade" id="editOrgModal<?php echo $org['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo __('edit_org_settings'); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="update_org_settings">
                                <input type="hidden" name="org_id" value="<?php echo $org['id']; ?>">
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="org_is_public" id="org_is_public<?php echo $org['id']; ?>" 
                                           <?php echo $org['is_public'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="org_is_public<?php echo $org['id']; ?>">
                                        <strong><?php echo __('show_in_public_list'); ?></strong>
                                    </label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="org_requires_code" id="org_requires_code<?php echo $org['id']; ?>" 
                                           <?php echo $org['requires_invitation_code'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="org_requires_code<?php echo $org['id']; ?>">
                                        <strong><?php echo __('require_inv_code'); ?></strong>
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                                <button type="submit" class="btn btn-primary"><?php echo __('save_changes'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pending Registrations -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header fw-bold py-3">
        ⏳ <?php echo __('pending_verifications_title'); ?> (<?php echo $pending_count; ?>)
    </div>
    <div class="card-body">
        <?php if ($pending_users): ?>
            <?php foreach ($pending_users as $user): ?>
                <div class="pending-item">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <strong><?php echo h($user['full_name'] ?: $user['username']); ?></strong>
                            <br>
                            <small><?php echo h($user['email']); ?></small>
                            <br>
                            <small class="text-muted">📅 <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></small>
                            <?php if ($user['organization_id']): ?>
                                <br>
                                <small class="badge bg-info"><?php echo h(get_name($user)); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7 text-end">
                            <form method="POST" style="display: inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="approve_registration">
                                <input type="hidden" name="employee_id" value="<?php echo $user['employee_id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">✅ <?php echo __('approve_reg'); ?></button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="reject_registration">
                                <input type="hidden" name="employee_id" value="<?php echo $user['employee_id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?php echo __('confirm_delete_reg'); ?>');">❌ <?php echo __('reject_reg'); ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-4 text-muted">
                ✅ <?php echo __('no_pending_verifications'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Help & Guide -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header fw-bold py-3">
        📚 <?php echo __('how_to_control_reg'); ?>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-0">
            <h6 class="fw-bold"><?php echo __('available_controls'); ?></h6>
            <ul class="mb-0">
                <li><strong><?php echo __('global_settings_label'); ?></strong> <?php echo __('global_settings_desc'); ?></li>
                <li><strong><?php echo __('per_org_label'); ?></strong> <?php echo __('per_org_desc'); ?></li>
                <li><strong><?php echo __('pending_approvals_label'); ?></strong> <?php echo __('pending_approvals_desc'); ?></li>
            </ul>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>
