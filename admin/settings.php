<?php
require_once '../includes/config.php';
checkAuth(['admin']); // فقط مدير النظام

$success = '';
$error = '';

// معالجة تحديث الإعدادات العامة للمؤسسة النشطة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_general'])) {
    $settings_to_update = [
        'site_name_ar' => $_POST['site_name_ar'],
        'site_name_en' => $_POST['site_name_en'],
        'primary_color' => $_POST['primary_color'],
        'font_family_ar' => $_POST['font_family_ar'],
        'font_family_en' => $_POST['font_family_en'],
        'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
        'allow_leave_requests' => isset($_POST['allow_leave_requests']) ? '1' : '0',
        'prevent_overlapping_leaves' => isset($_POST['prevent_overlapping_leaves']) ? '1' : '0',
        'allow_past_leaves' => isset($_POST['allow_past_leaves']) ? '1' : '0',
        'weekend_days' => $_POST['weekend_days'] ?? 'Friday,Saturday',
            'footer_text_ar' => $_POST['footer_text_ar'],
            'footer_text_en' => $_POST['footer_text_en'],
        
        // تخصيص حقول طلب التسجيل
        'reg_field_phone_visible' => isset($_POST['reg_field_phone_visible']) ? '1' : '0',
        'reg_field_phone_required' => isset($_POST['reg_field_phone_required']) ? '1' : '0',
        'reg_field_dept_visible' => isset($_POST['reg_field_dept_visible']) ? '1' : '0',
        'reg_field_dept_required' => isset($_POST['reg_field_dept_required']) ? '1' : '0',
        'reg_field_job_visible' => isset($_POST['reg_field_job_visible']) ? '1' : '0',
        'reg_field_job_required' => isset($_POST['reg_field_job_required']) ? '1' : '0',
        'reg_field_balance_visible' => isset($_POST['reg_field_balance_visible']) ? '1' : '0',
        'reg_field_balance_required' => isset($_POST['reg_field_balance_required']) ? '1' : '0',
        
        // تخصيص حقول طلب الإجازة
        'leave_field_reason_visible' => isset($_POST['leave_field_reason_visible']) ? '1' : '0',
        'leave_field_reason_required' => isset($_POST['leave_field_reason_required']) ? '1' : '0',
        'leave_field_attachment_visible' => isset($_POST['leave_field_attachment_visible']) ? '1' : '0',
        'leave_field_attachment_required' => isset($_POST['leave_field_attachment_required']) ? '1' : '0'
    ];

    try {
        $org_id = CURRENT_ORG_ID ?? 1;
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, organization_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings_to_update as $key => $value) {
            $stmt->execute([$key, $value, $org_id]);
        }
        logActivity("⚙️ تحديث إعدادات المنشأة", "⚙️ Update Organization Settings", "Settings updated for organization ID: " . $org_id);
        $pdo->commit();
        $success = __('success_updated');
        
        // إعادة تحميل الإعدادات في الجلسة الحالية
        foreach ($settings_to_update as $key => $value) {
            $app_settings[$key] = $value;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$pageTitle = __('settings');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<div class="mb-4 d-flex align-items-center">
    <div class="bg-primary text-white rounded p-2 me-3">
        ⚙️
    </div>
    <h1 class="h3 mb-0"><?php echo __('settings'); ?></h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 d-flex align-items-center">
        ✅ <?php echo $success; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
        ⚠️ <?php echo $error; ?>
    </div>
<?php endif; ?>

<form action="settings.php" method="POST">
    <div class="row g-4 justify-content-center">
        <!-- إعدادات النظام العامة -->
        <div class="col-lg-10">
            <div class="card shadow-sm border-0 overflow-hidden">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div class="fw-bold text-dark">
                        <?php echo __('general_settings'); ?>
                    </div>
                    <button type="submit" name="update_general" class="btn btn-primary px-4 shadow-sm">
                        <?php echo __('save_settings'); ?>
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- مسميات الموقع -->
                        <div class="col-md-12">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">
                                <?php echo __('site_name'); ?>
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('site_name_ar_setting'); ?> (AR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">🅰️</span>
                                        <input type="text" name="site_name_ar" class="form-control" value="<?php echo h(getSetting('site_name_ar')); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('site_name_en_setting'); ?> (EN)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">🌐</span>
                                        <input type="text" name="site_name_en" class="form-control" value="<?php echo h(getSetting('site_name_en')); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- إعدادات المظهر -->
                        <div class="col-md-12">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2 mt-2">
                                <?php echo __('ui_settings'); ?>
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('primary_color'); ?></label>
                                    <div class="input-group">
                                        <input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?php echo h(getSetting('primary_color', '#0d6efd')); ?>" style="height: 38px;">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('font_family'); ?> (AR)</label>
                                    <select name="font_family_ar" class="form-select bg-light">
                                        <option value="Cairo" <?php echo getSetting('font_family_ar') == 'Cairo' ? 'selected' : ''; ?>>Cairo (Recommended)</option>
                                        <option value="Almarai" <?php echo getSetting('font_family_ar') == 'Almarai' ? 'selected' : ''; ?>>Almarai</option>
                                        <option value="Tajawal" <?php echo getSetting('font_family_ar') == 'Tajawal' ? 'selected' : ''; ?>>Tajawal</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('font_family'); ?> (EN)</label>
                                    <select name="font_family_en" class="form-select bg-light">
                                        <option value="Inter" <?php echo getSetting('font_family_en') == 'Inter' ? 'selected' : ''; ?>>Inter (Recommended)</option>
                                        <option value="Roboto" <?php echo getSetting('font_family_en') == 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                        <option value="Poppins" <?php echo getSetting('font_family_en') == 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- إعدادات التحكم الوظيفي -->
                        <div class="col-md-12">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2 mt-2">
                                <?php echo __('functional_settings'); ?>
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="p-3 rounded border bg-light d-flex align-items-center justify-content-between shadow-sm">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white p-2 rounded shadow-sm me-3 text-primary">
                                                👤➕
                                            </div>
                                            <label class="fw-bold mb-0" for="allowReg"><?php echo __('allow_registration'); ?></label>
                                        </div>
                                        <div class="form-check form-switch fs-4">
                                            <input class="form-check-input" type="checkbox" name="allow_registration" id="allowReg" <?php echo getSetting('allow_registration') == '1' ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded border bg-light d-flex align-items-center justify-content-between shadow-sm">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white p-2 rounded shadow-sm me-3 text-primary">
                                                ✈️
                                            </div>
                                            <label class="fw-bold mb-0" for="allowLeaves"><?php echo __('allow_leave_requests'); ?></label>
                                        </div>
                                        <div class="form-check form-switch fs-4">
                                            <input class="form-check-input" type="checkbox" name="allow_leave_requests" id="allowLeaves" <?php echo getSetting('allow_leave_requests') == '1' ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ضوابط وأنظمة الإجازات -->
                        <div class="col-md-12">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2 mt-2">
                                <?php echo __('leave_controls'); ?>
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="p-3 rounded border bg-light d-flex align-items-center justify-content-between shadow-sm">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white p-2 rounded shadow-sm me-3 text-danger">
                                                🛡️
                                            </div>
                                            <label class="fw-bold mb-0" for="preventOverlap"><?php echo __('prevent_overlapping_leaves'); ?></label>
                                        </div>
                                        <div class="form-check form-switch fs-4">
                                            <input class="form-check-input" type="checkbox" name="prevent_overlapping_leaves" id="preventOverlap" <?php echo getSetting('prevent_overlapping_leaves', '1') == '1' ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded border bg-light d-flex align-items-center justify-content-between shadow-sm">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white p-2 rounded shadow-sm me-3 text-warning">
                                                ⏳
                                            </div>
                                            <label class="fw-bold mb-0" for="allowPast"><?php echo __('allow_past_leaves'); ?></label>
                                        </div>
                                        <div class="form-check form-switch fs-4">
                                            <input class="form-check-input" type="checkbox" name="allow_past_leaves" id="allowPast" <?php echo getSetting('allow_past_leaves', '0') == '1' ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- تخصيص حقول النماذج -->
                        <div class="col-md-12 mt-4">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">
                                <?php echo __('form_fields_customization'); ?>
                            </h6>
                            <p class="text-muted small"><?php echo __('form_fields_desc'); ?></p>
                            
                            <div class="row g-4">
                                <!-- حقول طلب التسجيل -->
                                <div class="col-md-6">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-header bg-light fw-bold py-3"><?php echo __('reg_form_fields'); ?></div>
                                        <div class="card-body">
                                            <!-- رقم الجوال -->
                                            <div class="border-bottom pb-3 mb-3">
                                                <div class="fw-bold mb-2"><?php echo __('phone_field'); ?></div>
                                                <div class="d-flex gap-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_phone_visible" id="regPhoneVis" <?php echo getSetting('reg_field_phone_visible', '1') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small" for="regPhoneVis"><?php echo __('visible_in_form'); ?></label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_phone_required" id="regPhoneReq" <?php echo getSetting('reg_field_phone_required', '0') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small text-danger fw-bold" for="regPhoneReq"><?php echo __('required_field'); ?></label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- القسم -->
                                            <div class="border-bottom pb-3 mb-3">
                                                <div class="fw-bold mb-2"><?php echo __('dept_field'); ?></div>
                                                <div class="d-flex gap-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_dept_visible" id="regDeptVis" <?php echo getSetting('reg_field_dept_visible', '1') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small" for="regDeptVis"><?php echo __('visible_in_form'); ?></label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_dept_required" id="regDeptReq" <?php echo getSetting('reg_field_dept_required', '0') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small text-danger fw-bold" for="regDeptReq"><?php echo __('required_field'); ?></label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- المسمى الوظيفي -->
                                            <div class="border-bottom pb-3 mb-3">
                                                <div class="fw-bold mb-2"><?php echo __('job_field'); ?></div>
                                                <div class="d-flex gap-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_job_visible" id="regJobVis" <?php echo getSetting('reg_field_job_visible', '1') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small" for="regJobVis"><?php echo __('visible_in_form'); ?></label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_job_required" id="regJobReq" <?php echo getSetting('reg_field_job_required', '0') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small text-danger fw-bold" for="regJobReq"><?php echo __('required_field'); ?></label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- الأرصدة الافتتاحية -->
                                            <div>
                                                <div class="fw-bold mb-2"><?php echo __('balance_field'); ?></div>
                                                <div class="d-flex gap-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_balance_visible" id="regBalVis" <?php echo getSetting('reg_field_balance_visible', '1') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small" for="regBalVis"><?php echo __('visible_in_form'); ?></label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="reg_field_balance_required" id="regBalReq" <?php echo getSetting('reg_field_balance_required', '0') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small text-danger fw-bold" for="regBalReq"><?php echo __('required_field'); ?></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- حقول طلب الإجازة -->
                                <div class="col-md-6">
                                    <div class="card border shadow-sm h-100">
                                        <div class="card-header bg-light fw-bold py-3"><?php echo __('leave_form_fields'); ?></div>
                                        <div class="card-body">
                                            <!-- سبب الإجازة -->
                                            <div class="border-bottom pb-3 mb-3">
                                                <div class="fw-bold mb-2"><?php echo __('reason_field'); ?></div>
                                                <div class="d-flex gap-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="leave_field_reason_visible" id="leaveReasonVis" <?php echo getSetting('leave_field_reason_visible', '1') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small" for="leaveReasonVis"><?php echo __('visible_in_form'); ?></label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="leave_field_reason_required" id="leaveReasonReq" <?php echo getSetting('leave_field_reason_required', '0') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small text-danger fw-bold" for="leaveReasonReq"><?php echo __('required_field'); ?></label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- مرفق الإثبات -->
                                            <div>
                                                <div class="fw-bold mb-2"><?php echo __('attachment_field'); ?></div>
                                                <div class="d-flex gap-4">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="leave_field_attachment_visible" id="leaveAttachVis" <?php echo getSetting('leave_field_attachment_visible', '1') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small" for="leaveAttachVis"><?php echo __('visible_in_form'); ?></label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="leave_field_attachment_required" id="leaveAttachReq" <?php echo getSetting('leave_field_attachment_required', '0') == '1' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small text-danger fw-bold" for="leaveAttachReq"><?php echo __('required_field'); ?></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- إعدادات عطلة نهاية الأسبوع -->
                        <div class="col-md-12 mt-4">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">
                                📅 <?php echo __('weekend_settings'); ?>
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('weekend_days'); ?></label>
                                    <select name="weekend_days" class="form-select bg-light">
                                        <option value="Friday,Saturday" <?php echo getSetting('weekend_days', 'Friday,Saturday') == 'Friday,Saturday' ? 'selected' : ''; ?>><?php echo __('weekend_friday_saturday'); ?></option>
                                        <option value="Friday" <?php echo getSetting('weekend_days', 'Friday,Saturday') == 'Friday' ? 'selected' : ''; ?>><?php echo __('weekend_friday'); ?></option>
                                        <option value="Saturday,Sunday" <?php echo getSetting('weekend_days', 'Friday,Saturday') == 'Saturday,Sunday' ? 'selected' : ''; ?>><?php echo __('weekend_saturday_sunday'); ?></option>
                                        <option value="Sunday" <?php echo getSetting('weekend_days', 'Friday,Saturday') == 'Sunday' ? 'selected' : ''; ?>><?php echo __('weekend_sunday'); ?></option>
                                        <option value="None" <?php echo getSetting('weekend_days', 'Friday,Saturday') == 'None' ? 'selected' : ''; ?>><?php echo __('weekend_none'); ?></option>
                                    </select>
                                    <small class="text-muted d-block mt-1"><?php echo __('weekend_desc'); ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- الفوتر -->
                        <div class="col-md-12">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2 mt-2">
                                <?php echo __('footer_text'); ?>
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('footer_text'); ?> (AR)</label>
                                    <input type="text" name="footer_text_ar" class="form-control bg-light" value="<?php echo h(getSetting('footer_text_ar')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted"><?php echo __('footer_text'); ?> (EN)</label>
                                    <input type="text" name="footer_text_en" class="form-control bg-light" value="<?php echo h(getSetting('footer_text_en')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


</form>

<style>
    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
    .input-group-text {
        border: none;
        color: var(--primary-color);
    }
    .form-control, .form-select {
        border: 1px solid var(--border-color, #eee);
    }
    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.1);
        border-color: var(--primary-color);
    }
</style>

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>
