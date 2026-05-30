<?php
require_once '../includes/config.php';

checkAuth(['super_admin']);

$error = '';
$success = '';

// --- Handle test email ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
        $test_recipient = trim($_POST['test_email'] ?? '');
        if (empty($test_recipient) || !filter_var($test_recipient, FILTER_VALIDATE_EMAIL)) {
            $error = __('invalid_email');
        } else {
            require_once '../includes/EmailHelper.php';
            $mailer = new EmailHelper($pdo);
            if (!$mailer->isConfigured()) {
                $error = __('email_not_configured');
            } else {
                $subject = ($_SESSION['lang'] ?? 'ar') === 'ar' ? '📧 إعدادات النظام — رسالة اختبارية' : '📧 System Settings — Test Message';
                $body_html = '<h2>' . (($_SESSION['lang'] ?? 'ar') === 'ar' ? 'تم إرسال رسالة الاختبار بنجاح ✓' : 'Test message sent successfully ✓') . '</h2>'
                           . '<p>' . (($_SESSION['lang'] ?? 'ar') === 'ar' ? 'تم إرسال هذه الرسالة من إعدادات النظام.' : 'This message was sent from the System Settings page.') . '</p>'
                           . '<p><small>' . date('Y-m-d H:i:s') . '</small></p>';
                $body_text = (($_SESSION['lang'] ?? 'ar') === 'ar' ? 'رسالة اختبارية من إعدادات النظام.' : 'Test message from System Settings.')
                           . ' — ' . date('Y-m-d H:i:s');
                $result = $mailer->send($test_recipient, $subject, $body_html, $body_text);
                if (is_array($result) && !empty($result['success'])) {
                    $success = __('test_email_sent') . ' ' . __('check_spam');
                    logActivity("📧 إرسال بريد اختبار", "📧 Send Test Email", "From system_settings to: $test_recipient");
                } else {
                    $error = __('test_email_failed') . ' ' . h($result['message'] ?? '');
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
    $settings_map = [
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'allow_new_org_registration' => isset($_POST['allow_new_org_registration']) ? '1' : '0',
        'auto_approve_orgs' => isset($_POST['auto_approve_orgs']) ? '1' : '0',
        'max_organizations' => intval($_POST['max_organizations'] ?? 100),
        'min_password_length' => intval($_POST['min_password_length'] ?? 6),
        'session_lifetime_minutes' => intval($_POST['session_lifetime_minutes'] ?? 120),
        'default_language' => in_array($_POST['default_language'] ?? '', ['ar', 'en']) ? $_POST['default_language'] : 'ar',
        'company_name_ar' => trim($_POST['company_name_ar'] ?? ''),
        'company_name_en' => trim($_POST['company_name_en'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'max_login_attempts' => intval($_POST['max_login_attempts'] ?? 5),
        // Email delivery (global — super admin only)
        'sndr_api_key' => trim($_POST['sndr_api_key'] ?? ''),
        'sndr_sender_email' => trim($_POST['sndr_sender_email'] ?? ''),
        'sndr_sender_name' => trim($_POST['sndr_sender_name'] ?? 'HR Management System'),
    ];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by)
                               VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
        foreach ($settings_map as $key => $value) {
            $stmt->execute([$key, (string)$value, $_SESSION['user_id']]);
        }
        $pdo->commit();
        $success = __('success_updated');
        logActivity("⚙️ تحديث إعدادات النظام", "⚙️ Update System Settings", "Updated " . count($settings_map) . " settings");
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$current_settings = [];
foreach ($stmt->fetchAll() as $row) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

function ss($key, $default = '') {
    global $current_settings;
    return $current_settings[$key] ?? $default;
}

$pageTitle = __('system_settings_title');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">⚙️ <?php echo __('system_settings_title'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('system_settings_desc'); ?></p>
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

<form method="POST">
    <?php echo csrf_field(); ?>
    <!-- General Settings -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">🌐 <?php echo __('general_settings'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo ss('maintenance_mode') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="maintenance_mode"><?php echo __('maintenance_mode'); ?></label>
                        <div class="text-muted small"><?php echo __('maintenance_mode_desc'); ?></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="allow_new_org_registration" id="allow_new_org_registration" value="1" <?php echo ss('allow_new_org_registration', '1') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="allow_new_org_registration"><?php echo __('allow_new_org_registration'); ?></label>
                        <div class="text-muted small"><?php echo __('allow_new_org_registration_desc'); ?></div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="auto_approve_orgs" id="auto_approve_orgs" value="1" <?php echo ss('auto_approve_orgs') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="auto_approve_orgs"><?php echo __('auto_approve_orgs'); ?></label>
                        <div class="text-muted small"><?php echo __('auto_approve_orgs_desc'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Settings -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">🔒 <?php echo __('security_settings'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('min_password_length'); ?></label>
                    <input type="number" name="min_password_length" class="form-control" value="<?php echo h(ss('min_password_length', '6')); ?>" min="4" max="64">
                    <div class="text-muted small"><?php echo __('min_password_length_desc'); ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('max_login_attempts'); ?></label>
                    <input type="number" name="max_login_attempts" class="form-control" value="<?php echo h(ss('max_login_attempts', '5')); ?>" min="1" max="20">
                    <div class="text-muted small"><?php echo __('max_login_attempts_desc'); ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('session_lifetime'); ?></label>
                    <div class="input-group">
                        <input type="number" name="session_lifetime_minutes" class="form-control" value="<?php echo h(ss('session_lifetime_minutes', '120')); ?>" min="5" max="1440">
                        <span class="input-group-text"><?php echo __('minutes'); ?></span>
                    </div>
                    <div class="text-muted small"><?php echo __('session_lifetime_desc'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Localization -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">🌍 <?php echo __('localization'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('company_name_ar'); ?></label>
                    <input type="text" name="company_name_ar" class="form-control" value="<?php echo h(ss('company_name_ar', __('company_name_ar_default'))); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('company_name_en'); ?></label>
                    <input type="text" name="company_name_en" class="form-control" value="<?php echo h(ss('company_name_en', 'HR Management System')); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('default_language'); ?></label>
                    <select name="default_language" class="form-select">
                        <option value="ar" <?php echo ss('default_language', 'ar') === 'ar' ? 'selected' : ''; ?>><?php echo __('arabic'); ?></option>
                        <option value="en" <?php echo ss('default_language', 'ar') === 'en' ? 'selected' : ''; ?>><?php echo __('english'); ?></option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('contact_email'); ?></label>
                    <input type="email" name="contact_email" class="form-control" value="<?php echo h(ss('contact_email')); ?>">
                    <div class="text-muted small"><?php echo __('contact_email_desc'); ?></div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('max_organizations'); ?></label>
                    <input type="number" name="max_organizations" class="form-control" value="<?php echo h(ss('max_organizations', '100')); ?>" min="1" max="9999">
                    <div class="text-muted small"><?php echo __('max_organizations_desc'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Delivery (Global — Super Admin only) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">📧 <?php echo __('email_settings'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('sndr_api_key'); ?></label>
                    <div class="input-group">
                        <input type="password" name="sndr_api_key" class="form-control" value="<?php echo h(ss('sndr_api_key')); ?>" autocomplete="off" id="sndrApiKey">
                        <button class="btn btn-outline-secondary" type="button" onclick="const i=document.getElementById('sndrApiKey');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁️':'🙈'">👁️</button>
                    </div>
                    <div class="text-muted small"><?php echo __('sndr_api_desc'); ?></div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('sender_email'); ?></label>
                    <input type="email" name="sndr_sender_email" class="form-control" value="<?php echo h(ss('sndr_sender_email')); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold small"><?php echo __('sender_name'); ?></label>
                    <input type="text" name="sndr_sender_name" class="form-control" value="<?php echo h(ss('sndr_sender_name', 'HR Management System')); ?>">
                </div>
            </div>
            <!-- Test Email -->
            <hr class="my-3">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-bold small"><?php echo __('test_email_label'); ?></label>
                    <div class="input-group">
                        <input type="email" name="test_email" class="form-control" placeholder="<?php echo __('test_email_placeholder'); ?>" value="<?php echo h(ss('contact_email')); ?>">
                        <button type="submit" name="send_test_email" class="btn btn-outline-primary">📤 <?php echo __('send_test'); ?></button>
                    </div>
                    <div class="text-muted small mt-1"><?php echo __('test_email_desc'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
        <button type="submit" name="update_settings" class="btn btn-primary px-5 py-2 fw-bold shadow-sm">
            💾 <?php echo __('save_settings'); ?>
        </button>
    </div>
</form>

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>
