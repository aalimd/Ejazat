<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$error = '';
$success = '';

// جلب الأقسام للاختيار منها للجهة الحالية فقط
$stmtDepts = $pdo->prepare("SELECT * FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtDepts->execute([CURRENT_ORG_ID]);
$departments = $stmtDepts->fetchAll();

// جلب أنواع الإجازات للجهة الحالية فقط
$stmtTypes = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtTypes->execute([CURRENT_ORG_ID]);
$leave_types = $stmtTypes->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // بيانات المستخدم
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    
    // بيانات الموظف
    $employee_id_number = trim($_POST['employee_id_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $job_title = trim($_POST['job_title'] ?? '');
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    
    $leave_balances = $_POST['leave_balances'] ?? [];
    $total_balance = 0;
    foreach ($leave_types as $type) {
        $total_balance += intval($leave_balances[$type['id']] ?? 0);
    }

    if (empty($username) || empty($password) || empty($email) || empty($full_name) || empty($employee_id_number)) {
        $error = __('cancel'); // Can be improved
    } else {
        try {
            $pdo->beginTransaction();

            $system_id = generateSystemId();

            // 1. إنشاء المستخدم مع ربطه بالمؤسسة الحالية
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, email, role, organization_id) VALUES (?, ?, ?, 'employee', ?)");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser->execute([$username, $hashedPassword, $email, CURRENT_ORG_ID]);
            $user_id = $pdo->lastInsertId();

            // 2. إنشاء الموظف مع ربطه بالمؤسسة الحالية
            $stmtEmp = $pdo->prepare("INSERT INTO employees (user_id, employee_id_number, system_id, full_name, phone, department_id, job_title, hire_date, initial_leave_balance, status, organization_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $stmtEmp->execute([$user_id, $employee_id_number, $system_id, $full_name, $phone, $department_id, $job_title, $hire_date, $total_balance, CURRENT_ORG_ID]);
            $emp_id = $pdo->lastInsertId();

            // 3. إضافة أرصدة الإجازات التفصيلية
            $stmtBalance = $pdo->prepare("INSERT INTO employee_leave_balances (employee_id, leave_type_id, balance) VALUES (?, ?, ?)");
            foreach ($leave_types as $type) {
                $type_id = $type['id'];
                $balance = intval($leave_balances[$type_id] ?? 0);
                $stmtBalance->execute([$emp_id, $type_id, $balance]);
            }

            logActivity("➕ إضافة موظف جديد", "➕ Add New Employee", "Name: $full_name, System ID: $system_id");

            $pdo->commit();
            $success = __('success_added');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = __('add_new');
include '../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3"><?php echo __('add_new'); ?></h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="add.php" method="POST">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('username'); ?> *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('password'); ?> *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('email'); ?> *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('employee_id'); ?> *</label>
                    <input type="text" name="employee_id_number" class="form-control" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label"><?php echo __('full_name'); ?> *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('phone'); ?></label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('department'); ?></label>
                    <select name="department_id" class="form-select">
                        <option value="">-- <?php echo __('department'); ?> --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo h(get_name($dept)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('job_title'); ?></label>
                    <input type="text" name="job_title" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('hire_date'); ?></label>
                    <input type="date" name="hire_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-12 mb-3 mt-3">
                    <h6 class="fw-bold border-bottom pb-2"><?php echo __('initial_balances_per_type'); ?></h6>
                </div>
                <?php foreach ($leave_types as $type): ?>
                <div class="col-md-3 mb-3">
                    <label class="form-label small fw-bold text-primary"><?php echo h(get_name($type)); ?></label>
                    <input type="number" name="leave_balances[<?php echo $type['id']; ?>]" class="form-control border-primary" required min="0" value="0">
                </div>
                <?php endforeach; ?>
            </div>

            <hr>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4"><?php echo __('save'); ?></button>
                <a href="list.php" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
