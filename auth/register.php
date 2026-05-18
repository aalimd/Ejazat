<?php
require_once '../includes/config.php';

// إذا كان المستخدم مسجلاً بالفعل، يتم توجيهه للرئيسية
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// جلب جميع جهات العمل النشطة لتمكين الموظف من التسجيل بها
$organizations = $pdo->query("SELECT * FROM organizations WHERE status = 'active' ORDER BY name_ar ASC")->fetchAll();
$selected_org_id = intval($_GET['org_id'] ?? $_POST['organization_id'] ?? ($organizations[0]['id'] ?? 1));

// التحقق من تفعيل التسجيل لهذه المؤسسة تحديداً
if (getSetting('allow_registration', '1', $selected_org_id) !== '1') {
    $pageTitle = __('register');
    include '../includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-warning text-center p-5 shadow-sm">⚠️<h4>' . __('registration_disabled') . '</h4><a href="login.php" class="btn btn-primary mt-3">' . __('login') . '</a></div></div>';
    include '../includes/footer.php';
    exit;
}

// جلب أقسام الجهة المحددة
$departments = $pdo->prepare("SELECT * FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
$departments->execute([$selected_org_id]);
$departments = $departments->fetchAll();

// جلب أنواع إجازات الجهة المحددة
$leave_types = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
$leave_types->execute([$selected_org_id]);
$leave_types = $leave_types->fetchAll();

// جلب إعدادات الحقول للجهة المحددة
$reg_phone_visible = getSetting('reg_field_phone_visible', '1', $selected_org_id) == '1';
$reg_phone_required = getSetting('reg_field_phone_required', '0', $selected_org_id) == '1';
$reg_dept_visible = getSetting('reg_field_dept_visible', '1', $selected_org_id) == '1';
$reg_dept_required = getSetting('reg_field_dept_required', '0', $selected_org_id) == '1';
$reg_job_visible = getSetting('reg_field_job_visible', '1', $selected_org_id) == '1';
$reg_job_required = getSetting('reg_field_job_required', '0', $selected_org_id) == '1';
$reg_balance_visible = getSetting('reg_field_balance_visible', '1', $selected_org_id) == '1';
$reg_balance_required = getSetting('reg_field_balance_required', '0', $selected_org_id) == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $error = __('Fill required fields.');
    } elseif ($reg_phone_visible && $reg_phone_required && empty($phone)) {
        $error = 'رقم الجوال مطلوب إجبارياً لجهة العمل هذه.';
    } elseif ($reg_dept_visible && $reg_dept_required && empty($department_id)) {
        $error = 'القسم مطلوب إجبارياً لجهة العمل هذه.';
    } elseif ($reg_job_visible && $reg_job_required && empty($job_title)) {
        $error = 'المسمى الوظيفي مطلوب إجبارياً لجهة العمل هذه.';
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
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error = __('username') . " / " . __('email') . " / " . __('employee_id') . " " . __('already exists.');
            } else {
                $error = $e->getMessage();
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
                        👤➕
                        <h4 class="fw-bold"><?php echo __('register'); ?></h4>
                        <p class="text-muted small"><?php echo __('site_name'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger shadow-sm border-0">⚠️ <?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success shadow-sm border-0">
                            <h5 class="fw-bold">✅ <?php echo $success; ?></h5>
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

                    <form action="register.php?org_id=<?php echo $selected_org_id; ?>" method="POST">
                        <div class="row">
                            <!-- اختيار جهة العمل -->
                            <div class="col-md-12 mb-4">
                                <label class="form-label small fw-bold text-primary">🏢 جهة العمل / المؤسسة *</label>
                                <select name="organization_id" id="organization_id" class="form-select bg-light border-primary fw-bold" required onchange="window.location.href='register.php?org_id=' + this.value;">
                                    <?php foreach ($organizations as $org): ?>
                                        <option value="<?php echo $org['id']; ?>" <?php echo $selected_org_id === intval($org['id']) ? 'selected' : ''; ?>>
                                            <?php echo h(get_name($org)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">سيتم جلب أقسام وسياسات الإجازات الخاصة بهذه الجهة فور اختيارها.</small>
                            </div>

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

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm"><?php echo __('submit'); ?></button>
                        </div>
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
