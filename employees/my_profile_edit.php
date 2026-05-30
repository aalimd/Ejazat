<?php
require_once '../includes/config.php';
checkAuth(['employee']);

$error = '';
$success = '';
$emp_id = $_SESSION['employee_id'];

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

// جلب أنواع الإجازات وأرصدة الموظف منها
$stmtLeaveTypes = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtLeaveTypes->execute([CURRENT_ORG_ID]);
$leave_types = $stmtLeaveTypes->fetchAll();
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
    $email = trim($_POST['email'] ?? '');
    
    // الموظف يمكنه تعديل الرصيد فقط إذا لم يتم تأكيده من المدير
    $leave_balances = $_POST['leave_balances'] ?? [];
    $new_total_balance = $emp['initial_leave_balance'];
    if (!$emp['leave_balance_verified']) {
        $new_total_balance = 0;
        foreach ($leave_types as $type) {
            $new_total_balance += intval($leave_balances[$type['id']] ?? 0);
        }
    }

    if (empty($full_name) || empty($email)) {
        $error = __('fill_fields_error');
    } else {
        try {
            $pdo->beginTransaction();

            // 1. تحديث البريد الإلكتروني
            $stmtUser = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmtUser->execute([$email, $emp['user_id']]);

            // 2. تحديث بيانات الموظف (فقط البيانات المسموح بها)
            $stmtEmp = $pdo->prepare("UPDATE employees SET full_name = ?, phone = ?, initial_leave_balance = ? WHERE id = ? AND organization_id = ?");
            $stmtEmp->execute([$full_name, $phone, $new_total_balance, $emp_id, CURRENT_ORG_ID]);

            if (!$emp['leave_balance_verified']) {
                $pdo->prepare("DELETE FROM employee_leave_balances WHERE employee_id = ?")->execute([$emp_id]);
                $stmtBalance = $pdo->prepare("INSERT INTO employee_leave_balances (employee_id, leave_type_id, balance) VALUES (?, ?, ?)");
                foreach ($leave_types as $type) {
                    $type_id = $type['id'];
                    $balance = intval($leave_balances[$type_id] ?? 0);
                    $stmtBalance->execute([$emp_id, $type_id, $balance]);
                }
            }

            logActivity("✏️ تحديث الملف الشخصي", "✏️ Update Profile", "Employee updated their own profile");

            $pdo->commit();
            $success = __('success_updated');
            
            // إعادة جلب البيانات وتحديث الجلسة
            $stmt->execute([$emp_id, CURRENT_ORG_ID]);
            $emp = $stmt->fetch();
            $_SESSION['full_name'] = $emp['full_name'];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = __('my_profile');
include '../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3"><?php echo __('edit'); ?>: <?php echo __('my_profile'); ?></h1>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 shadow-sm"><?php echo $success; ?></div>
                <?php endif; ?>

                <form action="my_profile_edit.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">
                                <?php echo __('basic_info'); ?>
                            </h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted"><?php echo __('full_name'); ?> *</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo h($emp['full_name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted"><?php echo __('email'); ?> *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo h($emp['email']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted"><?php echo __('phone'); ?></label>
                            <input type="text" name="phone" class="form-control" value="<?php echo h($emp['phone']); ?>">
                        </div>

                        <div class="col-md-12 mt-3">
                            <h6 class="text-primary fw-bold mb-3 border-bottom pb-2">
                                📅 <?php echo __('initial_leave_balances'); ?>
                            </h6>
                            <?php if (!$emp['leave_balance_verified']): ?>
                                <div class="alert alert-warning x-small">ℹ️ <?php echo __('enter_balance_accurately'); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($leave_types as $type): ?>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted"><?php echo h(get_name($type)); ?></label>
                            <?php if ($emp['leave_balance_verified']): ?>
                                <div class="input-group">
                                    <input type="number" class="form-control bg-light" value="<?php echo h($current_balances[$type['id']] ?? 0); ?>" readonly>
                                </div>
                            <?php else: ?>
                                <input type="number" name="leave_balances[<?php echo $type['id']; ?>]" class="form-control" value="<?php echo h($current_balances[$type['id']] ?? 0); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <div class="col-md-12 mt-4">
                            <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">
                                <?php echo __('manager_only_fields'); ?>
                            </h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted opacity-50"><?php echo __('employee_id'); ?></label>
                            <input type="text" class="form-control bg-light opacity-75" value="<?php echo h($emp['employee_id_number']); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted opacity-50"><?php echo __('job_title'); ?></label>
                            <input type="text" class="form-control bg-light opacity-75" value="<?php echo h($emp['job_title']); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted opacity-50"><?php echo __('hire_date'); ?></label>
                            <input type="text" class="form-control bg-light opacity-75" value="<?php echo h($emp['hire_date']); ?>" readonly>
                        </div>
                    </div>

                    <div class="mt-5 border-top pt-4 text-center">
                        <button type="submit" class="btn btn-primary px-5 shadow-sm fw-bold">
                            <?php echo __('save'); ?>
                        </button>
                        <a href="view.php" class="btn btn-outline-secondary px-5 ms-2">
                            <?php echo __('cancel'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
