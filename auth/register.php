<?php
require_once '../includes/config.php';

// إذا كان المستخدم مسجلاً بالفعل، يتم توجيهه للرئيسية
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// جلب جميع جهات العمل العامة (المرئية للجميع)
$public_orgs = $pdo->query("SELECT id, name_ar, name_en FROM organizations WHERE is_active = 1 AND is_public = 1 ORDER BY name_ar ASC")->fetchAll();

// التحقق من اختيار جهة عمل عام أو كود خاص
$selected_org_id = null;
$code_used = false;
$org_by_code = null;

// إذا تم إدخال كود، تحقق منه
if (!empty($_POST['invitation_code']) || !empty($_GET['code'])) {
    $code = trim($_POST['invitation_code'] ?? $_GET['code'] ?? '');
    if ($code) {
        $code_result = validateInvitationCode($code);
        if ($code_result['success']) {
            $selected_org_id = $code_result['org_id'];
            $code_used = true;
            $org_by_code = $code_result;
        } else {
            $error = __('code_invalid') . ': ' . $code_result['message'];
        }
    }
}

// إذا لم يتم اختيار من الكود، حاول من الـ GET أو POST للمؤسسات العامة
if ($selected_org_id === null) {
    if (!empty($_GET['org_id'])) {
        $selected_org_id = intval($_GET['org_id']);
        // تحقق أن المؤسسة عامة
        $stmt = $pdo->prepare("SELECT id FROM organizations WHERE id = ? AND is_public = 1 AND is_active = 1");
        $stmt->execute([$selected_org_id]);
        if (!$stmt->fetch()) {
            $selected_org_id = null;
        }
    } elseif (!empty($_POST['organization_id'])) {
        $selected_org_id = intval($_POST['organization_id']);
        // تحقق أن المؤسسة عامة
        $stmt = $pdo->prepare("SELECT id FROM organizations WHERE id = ? AND is_public = 1 AND is_active = 1");
        $stmt->execute([$selected_org_id]);
        if (!$stmt->fetch()) {
            $selected_org_id = null;
        }
    }
}

// إذا لم يتم اختيار أي شيء، اختر المؤسسة الأولى العامة
if ($selected_org_id === null && !empty($public_orgs)) {
    $selected_org_id = intval($public_orgs[0]['id']);
}

// التحقق من تفعيل التسجيل لهذه المؤسسة تحديداً
$is_reg_enabled = $selected_org_id ? (getSetting('allow_registration', '1', $selected_org_id) === '1') : false;

// جلب أقسام الجهة المحددة
$departments = [];
$leave_types = [];
if ($is_reg_enabled && $selected_org_id) {
    $departments = $pdo->prepare("SELECT * FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
    $departments->execute([$selected_org_id]);
    $departments = $departments->fetchAll();

    // جلب أنواع إجازات الجهة المحددة
    $leave_types = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
    $leave_types->execute([$selected_org_id]);
    $leave_types = $leave_types->fetchAll();
}

// جلب إعدادات الحقول للجهة المحددة
$reg_phone_visible = $is_reg_enabled && (getSetting('reg_field_phone_visible', '1', $selected_org_id) == '1');
$reg_phone_required = $is_reg_enabled && (getSetting('reg_field_phone_required', '0', $selected_org_id) == '1');
$reg_dept_visible = $is_reg_enabled && (getSetting('reg_field_dept_visible', '1', $selected_org_id) == '1');
$reg_dept_required = $is_reg_enabled && (getSetting('reg_field_dept_required', '0', $selected_org_id) == '1');
$reg_job_visible = $is_reg_enabled && (getSetting('reg_field_job_visible', '1', $selected_org_id) == '1');
$reg_job_required = $is_reg_enabled && (getSetting('reg_field_job_required', '0', $selected_org_id) == '1');
$reg_balance_visible = $is_reg_enabled && (getSetting('reg_field_balance_visible', '1', $selected_org_id) == '1');
$reg_balance_required = $is_reg_enabled && (getSetting('reg_field_balance_required', '0', $selected_org_id) == '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = __('fill_fields_error');
    } elseif (!$selected_org_id) {
        $error = __('no_org_selected');
    } elseif (!$is_reg_enabled) {
        $error = __('registration_disabled');
    } else {
        // بيانات المستخدم
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        // بيانات الموظف
        $employee_id_number = trim($_POST['employee_id_number'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = $reg_phone_visible ? trim($_POST['phone'] ?? '') : '';
        $department_id = ($reg_dept_visible && !empty($_POST['department_id'])) ? $_POST['department_id'] : null;
        $job_title = $reg_job_visible ? trim($_POST['job_title'] ?? '') : '';
        
        $leave_balances = $_POST['leave_balances'] ?? [];
        $total_balance = 0;
        if ($reg_balance_visible) {
            foreach ($leave_types as $type) {
                $total_balance += intval($leave_balances[$type['id']] ?? 0);
            }
        }

        if (empty($username) || empty($password) || empty($email) || empty($full_name) || empty($employee_id_number)) {
            $error = __('fill_fields_error');
        } elseif ($reg_phone_visible && $reg_phone_required && empty($phone)) {
            $error = __('phone_required_org');
        } elseif ($reg_dept_visible && $reg_dept_required && empty($department_id)) {
            $error = __('dept_required_org');
        } elseif ($reg_job_visible && $reg_job_required && empty($job_title)) {
            $error = __('job_required_org');
        } else {
            try {
                $pdo->beginTransaction();

                $system_id = generateSystemId();
                $registration_code = generateOperationCode('REG');

                // 1. إنشاء المستخدم بجهة العمل المحددة
                $stmtUser = $pdo->prepare("INSERT INTO users (username, password, email, role, organization_id) VALUES (?, ?, ?, 'employee', ?)");
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser->execute([$username, $hashedPassword, $email, $selected_org_id]);
                $user_id = $pdo->lastInsertId();

                // 2. إنشاء الموظف بجهة العمل المحددة
                $stmtEmp = $pdo->prepare("INSERT INTO employees (user_id, employee_id_number, system_id, full_name, phone, department_id, job_title, initial_leave_balance, status, registration_code, organization_id) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
                $stmtEmp->execute([$user_id, $employee_id_number, $system_id, $full_name, $phone, $department_id, $job_title, $total_balance, $registration_code, $selected_org_id]);
                $emp_id = $pdo->lastInsertId();

                // 3. إضافة أرصدة الإجازات التفصيلية للجهة
                $stmtBalance = $pdo->prepare("INSERT INTO employee_leave_balances (employee_id, leave_type_id, balance) VALUES (?, ?, ?)");
                foreach ($leave_types as $type) {
                    $type_id = $type['id'];
                    $balance = $reg_balance_visible ? intval($leave_balances[$type_id] ?? 0) : 0;
                    $stmtBalance->execute([$emp_id, $type_id, $balance]);
                }

                logActivity("📝 طلب تسجيل جديد", "📝 New Registration Request", "Username: $username, System ID: $system_id, Code: $registration_code");

                $pdo->commit();
                $success = __('registration_success');
                $op_code = $registration_code;
                $op_time = date('Y-m-d H:i:s');

                // Send verification email if the organization requires it, otherwise optionally send welcome email
                try {
                    if (getSetting('email_verification_required', '1', $selected_org_id) == '1') {
                        $res = sendVerificationEmail($user_id, $email, $username);
                        if ($res['success']) {
                            logActivity("✉️ إرسال تأكيد البريد الإلكتروني", "✉️ Sent Verification Email", "User ID: $user_id, Email: $email", $selected_org_id);
                        } else {
                            error_log('Verification email failed: ' . ($res['message'] ?? 'unknown'));
                        }
                    } else {
                        if (getSetting('email_notifications_enabled', '1', $selected_org_id) == '1') {
                            sendWelcomeEmailToUser($email, $username, $full_name, $selected_org_id);
                        }
                    }
                } catch (Exception $e) {
                    error_log('Error sending post-registration email: ' . $e->getMessage());
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) {
                    $error = __('user_exists_error');
                } else {
                    $error = __('db_error') . ': ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = __('register');
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center py-5">
        <div class="col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <span class="emoji-icon">👤➕</span>
                        <h1 class="h4 fw-bold"><?php echo __('register'); ?></h1>
                        <p class="text-muted small"><?php echo __('site_name'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger shadow-sm border-0"><span class="emoji-icon">⚠️</span> <?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success shadow-sm border-0">
                            <h5 class="fw-bold"><span class="emoji-icon">✅</span> <?php echo $success; ?></h5>
                            <hr>
                            <div class="row text-start small">
                                <div class="col-6 mb-2"><strong><?php echo __('operation_code'); ?>:</strong></div>
                                <div class="col-6 mb-2 text-primary fw-bold"><?php echo $op_code; ?></div>
                                <div class="col-6"><strong><?php echo __('operation_time'); ?>:</strong></div>
                                <div class="col-6 text-muted"><?php echo $op_time; ?> (Makkah)</div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary px-5 shadow-sm"><?php echo __('login'); ?></a>
                        </div>
                    <?php else: ?>

                    <form action="register.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <!-- Organization Selection - Public List -->
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label class="form-label small fw-bold text-primary"><?php echo __('choose_org'); ?></label>
                                <select name="organization_id" class="form-select bg-light border-primary fw-bold" onchange="this.form.submit();">
                                    <option value=""><?php echo __('choose_from_list'); ?></option>
                                    <?php foreach ($public_orgs as $org): ?>
                                        <option value="<?php echo $org['id']; ?>" <?php echo $selected_org_id === intval($org['id']) && !$code_used ? 'selected' : ''; ?>>
                                            <?php echo get_name($org); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-2"><?php echo __('org_not_listed'); ?></small>
                            </div>

                            <!-- Alternative: Private Organization with Code -->
                            <div class="col-md-12 mb-4 pt-2 border-top">
                                <h6 class="text-muted fw-bold mb-3"><?php echo __('have_invite_code'); ?></h6>
                                <div class="input-group">
                                    <input type="text" name="invitation_code" class="form-control bg-light text-center fw-bold" placeholder="<?php echo __('enter_code_placeholder'); ?>" value="<?php echo h($_POST['invitation_code'] ?? $_GET['code'] ?? ''); ?>">
                                    <button class="btn btn-success" type="submit"><?php echo __('verify_code'); ?></button>
                                </div>
                                <?php if ($code_used && $org_by_code): ?>
                                    <div class="alert alert-info mt-2 py-2 small">
                                        <?php echo __('code_verified'); ?>: <strong><?php echo h($org_by_code['org_name']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted d-block mt-2"><?php echo __('code_description'); ?></small>
                            </div>
                        </div>

                        <?php if (!$selected_org_id || !$is_reg_enabled): ?>
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-warning text-center py-4 shadow-sm border-0">
                                <span class="emoji-icon">⚠️</span> <h5 class="fw-bold mb-2"><?php echo __('no_org_selected'); ?></h5>
                                <p class="mb-0 text-muted small"><?php echo __('select_org_instruction'); ?></p>
                            </div>
                        </div>
                        <?php else: ?>

                        <!-- Hidden field to store selected organization -->
                        <input type="hidden" name="organization_id" value="<?php echo $selected_org_id; ?>">
                        <input type="hidden" name="invitation_code" value="<?php echo h($_POST['invitation_code'] ?? $_GET['code'] ?? ''); ?>">

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <h6 class="fw-bold border-bottom pb-2"><?php echo __('account_info'); ?></h6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('username'); ?> *</label>
                                <input type="text" name="username" class="form-control bg-light" required value="<?php echo h($_POST['username'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('password'); ?> *</label>
                                <input type="password" name="password" class="form-control bg-light" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('email'); ?> *</label>
                                <input type="email" name="email" class="form-control bg-light" required value="<?php echo h($_POST['email'] ?? ''); ?>">
                            </div>

                            <div class="col-md-12 mb-3 mt-3">
                                <h6 class="fw-bold border-bottom pb-2"><?php echo __('basic_info'); ?></h6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('employee_id'); ?> *</label>
                                <input type="text" name="employee_id_number" class="form-control bg-light" required value="<?php echo h($_POST['employee_id_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('full_name'); ?> *</label>
                                <input type="text" name="full_name" class="form-control bg-light" required value="<?php echo h($_POST['full_name'] ?? ''); ?>">
                            </div>

                            <!-- حقل رقم الجوال التفاعلي -->
                            <?php if ($reg_phone_visible): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('phone'); ?> <?php echo $reg_phone_required ? '*' : ''; ?></label>
                                <input type="text" name="phone" class="form-control bg-light" <?php echo $reg_phone_required ? 'required' : ''; ?> value="<?php echo h($_POST['phone'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>

                            <!-- حقل القسم التفاعلي -->
                            <?php if ($reg_dept_visible): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('department'); ?> <?php echo $reg_dept_required ? '*' : ''; ?></label>
                                <select name="department_id" class="form-select bg-light" <?php echo $reg_dept_required ? 'required' : ''; ?>>
                                    <option value="">-- <?php echo __('department'); ?> --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo h(get_name($dept)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <!-- حقل المسمى الوظيفي التفاعلي -->
                            <?php if ($reg_job_visible): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small fw-bold"><?php echo __('job_title'); ?> <?php echo $reg_job_required ? '*' : ''; ?></label>
                                <input type="text" name="job_title" class="form-control bg-light" <?php echo $reg_job_required ? 'required' : ''; ?> value="<?php echo h($_POST['job_title'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>

                            <!-- حقول الأرصدة الافتتاحية التفاعلية -->
                            <?php if ($reg_balance_visible && !empty($leave_types)): ?>
                            <div class="col-md-12 mb-3 mt-3">
                                <h6 class="fw-bold border-bottom pb-2"><?php echo __('initial_balances_per_type'); ?> <?php echo $reg_balance_required ? '*' : ''; ?></h6>
                            </div>
                            <?php foreach ($leave_types as $type): ?>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold text-primary"><?php echo h(get_name($type)); ?></label>
                                <input type="number" name="leave_balances[<?php echo $type['id']; ?>]" class="form-control border-primary" <?php echo $reg_balance_required ? 'required' : ''; ?> min="0" value="<?php echo h($_POST['leave_balances'][$type['id']] ?? 0); ?>">
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($is_reg_enabled): ?>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm"><?php echo __('submit'); ?></button>
                        </div>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <span class="text-muted small"><?php echo __('already_have_account'); ?></span>
                            <a href="login.php" class="small fw-bold text-decoration-none"><?php echo __('login'); ?></a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
