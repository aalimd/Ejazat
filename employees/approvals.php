<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$success = '';
$error = '';

// معالجة قرار الاعتماد أو الرفض
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $emp_id = $_POST['employee_id'];
    $action = $_POST['action']; // approved or rejected
    $reason = $_POST['rejection_reason'] ?? '';
    $verify_balance = isset($_POST['verify_balance']) ? 1 : 0;

    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("UPDATE employees SET status = ?, rejection_reason = ?, leave_balance_verified = ?, decision_date = NOW() WHERE id = ?");
    if ($stmt->execute([$status, $reason, $verify_balance, $emp_id])) {
        // إضافة إشعار للموظف
        $stmt_user = $pdo->prepare("SELECT user_id, full_name FROM employees WHERE id = ?");
        $stmt_user->execute([$emp_id]);
        $emp_data = $stmt_user->fetch();
        
        if ($status === 'approved') {
            logActivity("✅ اعتماد موظف", "✅ Approve Employee", "Employee: " . $emp_data['full_name'] . " (ID: $emp_id)");
            addNotification($emp_data['user_id'], __('profile_approved'), __('profile_approved'));
        } else {
            logActivity("❌ رفض اعتماد موظف", "❌ Reject Employee", "Employee: " . $emp_data['full_name'] . " (ID: $emp_id), Reason: $reason");
            addNotification($emp_data['user_id'], __('leave_request_rejected') . ": " . $reason, __('leave_request_rejected') . ": " . $reason);
        }
        
        $success = __('success_updated');
    } else {
        $error = 'Error updating record.';
    }
}

// جلب الموظفين بانتظار الاعتماد
$query = "SELECT e.*, d.name_ar as dept_ar, d.name_en as dept_en 
          FROM employees e 
          LEFT JOIN departments d ON e.department_id = d.id 
          WHERE e.status = 'pending' 
          ORDER BY e.created_at ASC";
$pending_employees = $pdo->query($query)->fetchAll();

$pageTitle = __('approvals');
include '../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3"><?php echo __('approvals'); ?></h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <?php if (empty($pending_employees)): ?>
        <div class="col-12">
            <div class="card bg-light border-0 py-5">
                <div class="card-body text-center">
                    ✔️
                    <h5 class="text-muted"><?php echo __('no_data'); ?></h5>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($pending_employees as $emp): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm border-warning">
                    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><?php echo __('employee_id'); ?>: <?php echo h($emp['employee_id_number']); ?></span>
                        <span class="badge bg-warning text-dark"><?php echo __('pending'); ?></span>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3"><?php echo h($emp['full_name']); ?></h5>
                        <div class="row small">
                            <div class="col-6 mb-2">
                                <span class="text-muted"><?php echo __('department'); ?>:</span> <?php echo h(get_name(['name_ar' => $emp['dept_ar'], 'name_en' => $emp['dept_en']])); ?>
                            </div>
                            <div class="col-6 mb-2">
                                <span class="text-muted"><?php echo __('job_title'); ?>:</span> <?php echo h($emp['job_title']); ?>
                            </div>
                            <div class="col-6 mb-2">
                                <span class="text-muted"><?php echo __('phone'); ?>:</span> <?php echo h($emp['phone']); ?>
                            </div>
                            <div class="col-6 mb-2">
                                <span class="text-muted"><?php echo __('hire_date'); ?>:</span> <?php echo h($emp['hire_date']); ?>
                            </div>
                            <div class="col-12 mb-2">
                                <span class="text-primary fw-bold"><?php echo __('total_balance'); ?>:</span> 
                                <span class="badge bg-primary"><?php echo $emp['initial_leave_balance']; ?> <?php echo __('days'); ?></span>
                            </div>
                            <div class="col-12 mb-2 small">
                                <?php 
                                $stmtB = $pdo->prepare("SELECT e.balance, l.name_ar, l.name_en FROM employee_leave_balances e JOIN leave_types l ON e.leave_type_id = l.id WHERE e.employee_id = ?");
                                $stmtB->execute([$emp['id']]);
                                foreach($stmtB->fetchAll() as $db): ?>
                                    <span class="badge bg-info text-dark"><?php echo h(get_name(['name_ar'=>$db['name_ar'], 'name_en'=>$db['name_en']])); ?>: <?php echo $db['balance']; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <form action="approvals.php" method="POST" class="mt-3">
                            <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input border-primary" type="checkbox" name="verify_balance" id="verify<?php echo $emp['id']; ?>" required>
                                <label class="form-check-label fw-bold text-primary small" for="verify<?php echo $emp['id']; ?>">
                                    <?php echo __('verify_balance'); ?>
                                </label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold"><?php echo __('rejection_reason'); ?>:</label>
                                <textarea name="rejection_reason" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm flex-grow-1">
                                    <?php echo __('approve'); ?>
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm flex-grow-1">
                                    <?php echo __('reject'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
