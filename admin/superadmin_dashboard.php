<?php
require_once '../includes/config.php';

// Super Admin ONLY — redirect to new dedicated dashboard
if ($_SESSION['role'] !== 'super_admin') {
    redirect('index.php');
}

redirect('../superadmin/dashboard.php');

// Get all organizations
$orgs = $pdo->query("SELECT id, name_ar, name_en, is_public, is_active FROM organizations ORDER BY name_ar ASC")->fetchAll();
$current_org = CURRENT_ORG_ID;
?>

<style>
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
    }
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
    }
</style>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="fw-bold mb-2"><?= __('super_admin_title') ?></h1>
        <p class="text-muted lead"><?= __('complete_system_admin_desc') ?></p>
        <div class="alert alert-danger border-0 py-2 small">
            <?= __('super_admin_warning') ?>
        </div>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'select_org_first'): ?>
            <div class="alert alert-warning border-0 py-2 small">
                <?= __('select_org_first') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Organization Selector -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h5 class="card-title fw-bold mb-3"><?= __('select_organization') ?></h5>
        <form method="POST" class="row g-3">
            <div class="col-md-8">
                <select name="organization_id" class="form-select" required>
                    <option value=""><?= __('view_system_wide') ?></option>
                    <?php foreach($orgs as $org): ?>
                        <option value="<?php echo $org['id']; ?>" <?php echo ($current_org == $org['id']) ? 'selected' : ''; ?>>
                            <?php echo get_name($org); ?>
                            <?php echo $org['is_public'] ? '(' . __('public') . ')' : '(' . __('private') . ')'; ?>
                            <?php echo !$org['is_active'] ? '- ' . __('inactive') : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" name="select_org" class="btn btn-primary w-100"><?= __('load_data') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center p-4 bg-primary text-white">
            <h3 class="mb-1 fw-bold display-6"><?php 
                try {
                    $count = $pdo->query("SELECT COUNT(*) as cnt FROM organizations WHERE is_active = 1")->fetchColumn(0);
                    echo $count ?? '0';
                } catch (Exception $e) {
                    echo '0';
                }
            ?></h3>
            <p class="mb-0 small opacity-75"><?= __('active_organizations') ?></p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center p-4 bg-success text-white">
            <h3 class="mb-1 fw-bold display-6"><?php 
                try {
                    $count = $pdo->query("SELECT COUNT(*) as cnt FROM users")->fetchColumn(0);
                    echo $count ?? '0';
                } catch (Exception $e) {
                    echo '0';
                }
            ?></h3>
            <p class="mb-0 small opacity-75"><?= __('total_users_count') ?></p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center p-4 bg-warning text-dark">
            <h3 class="mb-1 fw-bold display-6"><?php 
                try {
                    $count = $pdo->query("SELECT COUNT(*) as cnt FROM employees WHERE status = 'approved'")->fetchColumn(0);
                    echo $count ?? '0';
                } catch (Exception $e) {
                    echo '0';
                }
            ?></h3>
            <p class="mb-0 small opacity-75"><?= __('approved_employees_count') ?></p>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm text-center p-4 bg-danger text-white">
            <h3 class="mb-1 fw-bold display-6"><?php 
                try {
                    $count = $pdo->query("SELECT COUNT(*) as cnt FROM login_attempts WHERE failed_attempts >= 3")->fetchColumn(0);
                    echo $count ?? '0';
                } catch (Exception $e) {
                    echo '0';
                }
            ?></h3>
            <p class="mb-0 small opacity-75"><?= __('accounts_at_risk') ?></p>
        </div>
    </div>
</div>

<!-- Section 1: Organizations Management -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><?= __('orgs_management_section') ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <a href="organizations.php" class="btn btn-lg btn-outline-primary w-100 py-3">
                    <strong><?= __('manage_orgs_btn') ?></strong><br>
                    <small class="text-muted"><?= __('create_edit_delete_orgs') ?></small>
                </a>
            </div>
            <div class="col-md-6 mb-3">
                <a href="organization_codes.php" class="btn btn-lg btn-outline-success w-100 py-3">
                    <strong><?= __('privacy_codes_btn') ?></strong><br>
                    <small class="text-muted"><?= __('control_visibility_codes') ?></small>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Section 2: Registration Control -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><?= __('registration_user_onboarding') ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <a href="registration_control.php" class="btn btn-lg btn-outline-warning w-100 py-3">
                    <strong><?= __('registration_settings_btn') ?></strong><br>
                    <small class="text-muted"><?= __('reg_control_desc') ?></small>
                </a>
            </div>
            <div class="col-md-6 mb-3">
                <a href="activity_log.php?filter=registration" class="btn btn-lg btn-outline-info w-100 py-3">
                    <strong><?= __('reg_activity_log_btn') ?></strong><br>
                    <small class="text-muted"><?= __('reg_activity_desc') ?></small>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Section 3: Users & Roles -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><?= __('users_roles_section') ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="users.php" class="btn btn-lg btn-outline-success w-100 py-3">
                    <strong><?= __('all_users_btn') ?></strong><br>
                    <small class="text-muted"><?= __('users_desc') ?></small>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="javascript:void(0)" onclick="alert('Super Admin role assignment panel')" class="btn btn-lg btn-outline-info w-100 py-3">
                    <strong><?= __('assign_roles_btn') ?></strong><br>
                    <small class="text-muted"><?= __('roles_desc') ?></small>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="javascript:void(0)" onclick="alert('Password reset interface')" class="btn btn-lg btn-outline-warning w-100 py-3">
                    <strong><?= __('reset_passwords_btn') ?></strong><br>
                    <small class="text-muted"><?= __('reset_passwords_desc') ?></small>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Section 4: Email & Notifications -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><?= __('email_notifications_section') ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="system_settings.php" class="btn btn-lg btn-outline-info w-100 py-3">
                    <strong><?= __('email_settings_btn') ?></strong><br>
                    <small class="text-muted"><?= __('email_settings_desc') ?></small>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="javascript:void(0)" onclick="alert('Email template editor coming soon')" class="btn btn-lg btn-outline-info w-100 py-3">
                    <strong><?= __('email_templates_btn') ?></strong><br>
                    <small class="text-muted"><?= __('email_templates_desc') ?></small>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="javascript:void(0)" onclick="alert('Email audit log')" class="btn btn-lg btn-outline-info w-100 py-3">
                    <strong><?= __('email_logs_btn') ?></strong><br>
                    <small class="text-muted"><?= __('email_logs_desc') ?></small>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Section 5: Security & Monitoring -->
<div class="card shadow-sm border-0 mb-4" id="security-section">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><?= __('security_monitoring_section') ?></h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="fw-bold mb-3"><?= __('failed_login_attempts') ?></h6>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th><?= __('username_label') ?></th>
                                <th><?= __('attempts_label') ?></th>
                                <th><?= __('last_try_label') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $failed = $pdo->query("
                                SELECT username, failed_attempts, last_attempt 
                                FROM login_attempts 
                                WHERE last_attempt > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                ORDER BY failed_attempts DESC 
                                LIMIT 15
                            ")->fetchAll();
                            
                            if (count($failed) == 0) {
                                echo "<tr><td colspan='3' class='text-muted text-center'>" . __('no_failed_attempts') . "</td></tr>";
                            }
                            
                            foreach($failed as $attempt):
                            ?>
                                <tr>
                                    <td><strong><?php echo h($attempt['username']); ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo ($attempt['failed_attempts'] >= 5) ? 'bg-danger' : 'bg-warning'; ?>">
                                            <?php echo $attempt['failed_attempts']; ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('H:i', strtotime($attempt['last_attempt'])); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold mb-3"><?= __('code_attempt_breaches') ?></h6>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th><?= __('ip_address') ?></th>
                                <th><?= __('attempts_label') ?></th>
                                <th><?= __('last_try_label') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $code_attempts = $pdo->query("
                                SELECT ip_address, COUNT(*) as failed_count, MAX(created_at) as last_attempt
                                FROM organization_code_attempts 
                                WHERE success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                GROUP BY ip_address 
                                ORDER BY failed_count DESC 
                                LIMIT 15
                            ")->fetchAll();
                            
                            if (count($code_attempts) == 0) {
                                echo "<tr><td colspan='3' class='text-muted text-center'>" . __('no_code_attempts') . "</td></tr>";
                            }
                            
                            foreach($code_attempts as $ca):
                            ?>
                                <tr>
                                    <td><code><?php echo h($ca['ip_address']); ?></code></td>
                                    <td>
                                        <span class="badge <?php echo ($ca['failed_count'] >= 5) ? 'bg-danger' : 'bg-warning'; ?>">
                                            <?php echo $ca['failed_count']; ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?php echo date('H:i', strtotime($ca['last_attempt'])); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <a href="activity_log.php" class="btn btn-outline-danger w-100 py-2">
                    <strong><?= __('view_activity_log_btn') ?></strong>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Section 6: System Settings -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><?= __('system_settings_config') ?></h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <strong><?= __('system_config_tips') ?></strong>
            <ul class="mb-0 mt-2">
                <li><?= __('config_tip1') ?></li>
                <li><?= __('config_tip2') ?></li>
                <li><?= __('config_tip3') ?></li>
                <li><?= __('config_tip4') ?></li>
                <li><?= __('config_tip5') ?></li>
                <li><?= __('config_tip6') ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Section 7: Quick Actions -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0 fw-bold"><?= __('quick_actions') ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <a href="javascript:void(0)" onclick="alert('Clear login lockouts for a user')" class="btn btn-outline-secondary w-100 py-2">
                    <?= __('clear_login_lockouts') ?>
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="organization_codes.php" class="btn btn-outline-secondary w-100 py-2">
                    <?= __('regenerate_codes_btn') ?>
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="javascript:void(0)" onclick="alert('Export system data for backup')" class="btn btn-outline-secondary w-100 py-2">
                    <?= __('export_data_btn') ?>
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="site_health.php" class="btn btn-outline-secondary w-100 py-2">
                    <?= __('system_diagnostics_btn') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
