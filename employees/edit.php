<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$error = '';
$success = '';
$emp_id = $_GET['id'] ?? 0;

// جلب بيانات الموظف
$stmt = $pdo->prepare("SELECT e.*, u.email, u.username 
                       FROM employees e 
                       JOIN users u ON e.user_id = u.id 
                       WHERE e.id = ? AND e.organization_id = ?");
$stmt->execute([$emp_id, CURRENT_ORG_ID]);
$emp = $stmt->fetch();

if (!$emp) {
    die(__('employee_not_found'));
}

// جلب الأقسام للاختيار منها للجهة الحالية فقط
$stmtDepts = $pdo->prepare("SELECT * FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtDepts->execute([CURRENT_ORG_ID]);
$departments = $stmtDepts->fetchAll();

// جلب أنواع الإجازات وأرصدة الموظف منها للجهة الحالية فقط
$stmtTypes = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtTypes->execute([CURRENT_ORG_ID]);
$leave_types = $stmtTypes->fetchAll();

$stmtBalances = $pdo->prepare("SELECT * FROM employee_leave_balances WHERE employee_id = ?");
$stmtBalances->execute([$emp_id]);
$current_balances_raw = $stmtBalances->fetchAll();
$current_balances = [];
foreach($current_balances_raw as $b) {
    $current_balances[$b['leave_type_id']] = $b['balance'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $job_title = trim($_POST['job_title'] ?? '');
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    $email = trim($_POST['email'] ?? '');
    $leave_balances = $_POST['leave_balances'] ?? [];
    $total_balance = 0;
    foreach ($leave_types as $type) {
        $total_balance += intval($leave_balances[$type['id']] ?? 0);
    }
    
    $leave_balance_verified = isset($_POST['leave_balance_verified']) ? 1 : 0;

    if (empty($full_name) || empty($email)) {
        $error = 'Required fields missing.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. تحديث البريد الإلكتروني في جدول المستخدمين
            $stmtUser = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmtUser->execute([$email, $emp['user_id']]);

            // 2. تحديث بيانات الموظف (وتحديث الإجمالي) مع التحقق من المؤسسة
            $stmtEmp = $pdo->prepare("UPDATE employees SET full_name = ?, phone = ?, department_id = ?, job_title = ?, hire_date = ?, initial_leave_balance = ?, leave_balance_verified = ? WHERE id = ? AND organization_id = ?");
            $stmtEmp->execute([$full_name, $phone, $department_id, $job_title, $hire_date, $total_balance, $leave_balance_verified, $emp_id, CURRENT_ORG_ID]);

            // 3. تحديث أرصدة الإجازات التفصيلية للموظف التابع للجهة الحالية فقط
            $stmtDel = $pdo->prepare("DELETE FROM employee_leave_balances WHERE employee_id = ? AND employee_id IN (SELECT id FROM employees WHERE organization_id = ?)");
            $stmtDel->execute([$emp_id, CURRENT_ORG_ID]);
            
            $stmtBalance = $pdo->prepare("INSERT INTO employee_leave_balances (employee_id, leave_type_id, balance) VALUES (?, ?, ?)");
            foreach ($leave_types as $type) {
                $type_id = $type['id'];
                $balance = intval($leave_balances[$type_id] ?? 0);
                $stmtBalance->execute([$emp_id, $type_id, $balance]);
            }

            logActivity("✏️ تعديل بيانات موظف", "✏️ Edit Employee Info", "Employee: $full_name (ID: $emp_id)");

            $pdo->commit();
            $success = __('success_updated');
            
            // إعادة جلب البيانات المحدثة مع التحقق من المؤسسة
            $stmt->execute([$emp_id, CURRENT_ORG_ID]);
            $emp = $stmt->fetch();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = __('edit');
include '../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3"><?php echo __('edit'); ?>: <?php echo h($emp['full_name']); ?></h1>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="edit.php?id=<?php echo $emp_id; ?>" method="POST">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <h5 class="border-bottom pb-2"><?php echo __('basic_info'); ?></h5>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('employee_id'); ?></label>
                    <input type="text" class="form-control bg-light" value="<?php echo h($emp['employee_id_number']); ?>" readonly>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label"><?php echo __('full_name'); ?> *</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo h($emp['full_name']); ?>" required>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('email'); ?> *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo h($emp['email']); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('phone'); ?></label>
                    <input type="text" name="phone" class="form-control" value="<?php echo h($emp['phone']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('department'); ?></label>
                    <select name="department_id" class="form-select">
                        <option value="">-- <?php echo __('department'); ?> --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo ($emp['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo h(get_name($dept)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('job_title'); ?></label>
                    <input type="text" name="job_title" class="form-control" value="<?php echo h($emp['job_title']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('hire_date'); ?></label>
                    <input type="date" name="hire_date" class="form-control" value="<?php echo h($emp['hire_date']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <div>
                        <?php if ($emp['status'] == 'approved'): ?>
                            <span class="badge bg-success py-2"><?php echo __('approved'); ?></span>
                        <?php elseif ($emp['status'] == 'pending'): ?>
                            <span class="badge bg-warning text-dark py-2"><?php echo __('pending'); ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger py-2"><?php echo __('rejected'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-12 mb-3">
                    <h5 class="border-bottom pb-2 mt-3"><?php echo __('leave_stats') . ' ' . __('per_type'); ?></h5>
                </div>
                <?php foreach ($leave_types as $type): ?>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold text-primary"><?php echo h(get_name($type)); ?></label>
                    <input type="number" name="leave_balances[<?php echo $type['id']; ?>]" class="form-control" value="<?php echo h($current_balances[$type['id']] ?? 0); ?>">
                </div>
                <?php endforeach; ?>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="leave_balance_verified" id="verifyBalance" <?php echo $emp['leave_balance_verified'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="verifyBalance"><?php echo __('balance_verified'); ?></label>
                    </div>
                </div>
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
