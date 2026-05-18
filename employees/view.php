<?php
require_once '../includes/config.php';
checkAuth();

$emp_id = $_GET['id'] ?? $_SESSION['employee_id'] ?? 0;

// التحقق من الصلاحيات
if (hasRole('employee') && $emp_id != $_SESSION['employee_id']) {
    die("Access Denied.");
}

// جلب بيانات الموظف
$stmt = $pdo->prepare("SELECT e.*, d.name_ar as dept_ar, d.name_en as dept_en, u.email as user_email, u.username 
                       FROM employees e 
                       LEFT JOIN departments d ON e.department_id = d.id 
                       LEFT JOIN users u ON e.user_id = u.id 
                       WHERE e.id = ?");
$stmt->execute([$emp_id]);
$emp = $stmt->fetch();

$stmtBalances = $pdo->prepare("SELECT e.balance, l.name_ar, l.name_en FROM employee_leave_balances e JOIN leave_types l ON e.leave_type_id = l.id WHERE e.employee_id = ?");
$stmtBalances->execute([$emp_id]);
$detailed_balances = $stmtBalances->fetchAll();

if (!$emp) {
    die("Not Found.");
}

$pageTitle = __('details');
include '../includes/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 mb-0"><?php echo h($emp['full_name']); ?></h1>
        <p class="text-muted small"><?php echo __('employee_id'); ?>: <?php echo h($emp['employee_id_number']); ?></p>
    </div>
    <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="edit.php?id=<?php echo $emp['id']; ?>" class="btn btn-primary btn-sm shadow-sm">
            <?php echo __('edit'); ?>
        </a>
    <?php elseif (hasRole('employee')): ?>
        <a href="my_profile_edit.php" class="btn btn-primary btn-sm shadow-sm">
            <?php echo __('edit'); ?>
        </a>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4 text-center">
            <div class="card-body">
                <div class="mb-3">
                    <div class="fs-1 mb-3">👤</div>
                </div>
                <h5 class="fw-bold"><?php echo h($emp['full_name']); ?></h5>
                <p class="text-muted mb-3"><?php echo h($emp['job_title']); ?></p>
                <div class="mb-2">
                    <?php if ($emp['status'] == 'approved'): ?>
                        <span class="badge bg-success py-2 px-3"><?php echo __('approved'); ?></span>
                    <?php elseif ($emp['status'] == 'pending'): ?>
                        <span class="badge bg-warning text-dark py-2 px-3"><?php echo __('pending'); ?></span>
                    <?php else: ?>
                        <span class="badge bg-danger py-2 px-3"><?php echo __('rejected'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($emp['status'] == 'rejected' && $emp['rejection_reason']): ?>
            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white small fw-bold"><?php echo __('rejection_reason'); ?></div>
                <div class="card-body small text-danger">
                    <?php echo nl2br(h($emp['rejection_reason'])); ?>
                    <div class="mt-2 text-muted x-small"><?php echo __('decision_date'); ?>: <?php echo h($emp['decision_date']); ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold"><?php echo __('basic_info'); ?></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('full_name'); ?></div>
                    <div class="col-sm-8 fw-bold"><?php echo h($emp['full_name']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('email'); ?></div>
                    <div class="col-sm-8"><?php echo h($emp['user_email']); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('phone'); ?></div>
                    <div class="col-sm-8"><?php echo h($emp['phone'] ?: '-'); ?></div>
                </div>
                <hr>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('department'); ?></div>
                    <div class="col-sm-8"><?php echo h(get_name(['name_ar' => $emp['dept_ar'], 'name_en' => $emp['dept_en']])); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('job_title'); ?></div>
                    <div class="col-sm-8"><?php echo h($emp['job_title'] ?: '-'); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('hire_date'); ?></div>
                    <div class="col-sm-8"><?php echo h($emp['hire_date'] ?: '-'); ?></div>
                </div>
                <hr>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('balance_details'); ?></div>
                    <div class="col-sm-8">
                        <?php foreach($detailed_balances as $db): ?>
                            <span class="badge bg-info text-dark me-2 mb-1"><?php echo h(get_name(['name_ar'=>$db['name_ar'], 'name_en'=>$db['name_en']])); ?>: <?php echo $db['balance']; ?></span>
                        <?php endforeach; ?>
                        <?php if(empty($detailed_balances)) echo '-'; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted small"><?php echo __('total_balance'); ?></div>
                    <div class="col-sm-8 d-flex align-items-center">
                        <span class="fw-bold fs-5 text-primary me-2"><?php echo h($emp['initial_leave_balance'] ?: '0'); ?></span>
                        <span class="small text-muted me-3"><?php echo __('days'); ?></span>
                        <?php if ($emp['leave_balance_verified']): ?>
                            <span class="badge bg-success-subtle text-success border border-success small">
                                <?php echo __('balance_verified'); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning small">
                                <?php echo __('pending'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$emp['leave_balance_verified'] && hasRole('employee')): ?>
                    <div class="alert alert-light border small mt-2">
                        ℹ️
                        <?php echo __('can_edit_balance_before_verification'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-subtle { background-color: rgba(25, 135, 84, 0.1) !important; }
    .bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1) !important; }
</style>

<?php include '../includes/footer.php'; ?>
