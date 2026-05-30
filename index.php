<?php
require_once 'includes/config.php';
checkAuth();

$success_msg = '';
$error_msg = '';

// معالجة القرار السريع للإجازات من لوحة التحكم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_dashboard'])) {
    if (!verify_csrf()) {
        $error_msg = __('csrf_token_invalid');
    } else {
    $active_org = CURRENT_ORG_ID;
    
    // Require admin/manager role — prevent employee privilege escalation
    if (!hasRole(['admin', 'manager'])) {
        $error_msg = __('access_denied');
    } elseif (!$active_org) {
        $error_msg = __('select_org_first');
    } else {
    $request_id = $_POST['request_id'];
    $action = $_POST['action']; // approve or reject
    $note = trim($_POST['manager_note'] ?? '');
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    try {
        $pdo->beginTransaction();
        if ($active_org) {
            $stmt_req = $pdo->prepare("SELECT lr.*, lt.deduct_from_balance 
                                       FROM leave_requests lr 
                                       JOIN leave_types lt ON lr.leave_type_id = lt.id 
                                       JOIN employees e ON lr.employee_id = e.id
                                       WHERE lr.id = ? AND e.organization_id = ? AND lr.status = 'pending'");
            $stmt_req->execute([$request_id, $active_org]);
        } else {
            $stmt_req = $pdo->prepare("SELECT lr.*, lt.deduct_from_balance 
                                       FROM leave_requests lr 
                                       JOIN leave_types lt ON lr.leave_type_id = lt.id 
                                       WHERE lr.id = ? AND lr.status = 'pending'");
            $stmt_req->execute([$request_id]);
        }
        $req_data = $stmt_req->fetch();

        if ($req_data) {
            if ($active_org) {
                $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, manager_note = ?, action_at = NOW() WHERE id = ? AND organization_id = ? AND status = 'pending'");
                $stmt->execute([$status, $note, $request_id, $active_org]);
            } else {
                $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, manager_note = ?, action_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$status, $note, $request_id]);
            }

            // خصم الرصيد إذا تمت الموافقة وكان النوع يتطلب ذلك
            if ($status === 'approved' && $req_data['deduct_from_balance']) {
                $days = calculateLeaveDays($req_data['start_date'], $req_data['end_date'], $active_org);
                if ($active_org) {
                    $stmt_deduct = $pdo->prepare("UPDATE employees SET initial_leave_balance = initial_leave_balance - ? WHERE id = ? AND organization_id = ?");
                    $stmt_deduct->execute([$days, $req_data['employee_id'], $active_org]);
                } else {
                    $stmt_deduct = $pdo->prepare("UPDATE employees SET initial_leave_balance = initial_leave_balance - ? WHERE id = ?");
                    $stmt_deduct->execute([$days, $req_data['employee_id']]);
                }
            }

            if ($active_org) {
                $stmt_user = $pdo->prepare("SELECT user_id FROM employees WHERE id = ? AND organization_id = ?");
                $stmt_user->execute([$req_data['employee_id'], $active_org]);
            } else {
                $stmt_user = $pdo->prepare("SELECT user_id FROM employees WHERE id = ?");
                $stmt_user->execute([$req_data['employee_id']]);
            }
            $user_id = $stmt_user->fetchColumn();

            $approved_ar = $translations['ar']['leave_request_approved'] ?? __('leave_request_approved');
            $approved_en = $translations['en']['leave_request_approved'] ?? __('leave_request_approved');
            $rejected_ar = $translations['ar']['leave_request_rejected'] ?? __('leave_request_rejected');
            $rejected_en = $translations['en']['leave_request_rejected'] ?? __('leave_request_rejected');

            if ($status === 'approved') {
                logActivity("✅ الموافقة على إجازة (سريع)", "✅ Approve Leave Request (Quick)", "Request ID: $request_id");
                addNotification($user_id, $approved_ar, $approved_en);
            } else {
                logActivity("❌ رفض طلب إجازة (سريع)", "❌ Reject Leave Request (Quick)", "Request ID: $request_id, Note: $note");
                addNotification($user_id, $rejected_ar . ": " . $note, $rejected_en . ": " . $note);
            }

            $pdo->commit();
            $success_msg = __('success_updated');
        } else {
            $pdo->rollBack();
            $error_msg = __('access_denied');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = 'Error: ' . $e->getMessage();
    }
    }
    } // end CSRF else
}

$pageTitle = __('dashboard');
include 'includes/header.php';

// --- جلب الإحصائيات العامة ---
$stats = [
    'total_employees' => 0,
    'pending_approvals' => 0,
    'pending_leaves' => 0,
    'approved_leaves' => 0
];

$top_leave_types = [];
$top_employees = [];
$months_labels = [];
$months_data = [];

$org_id = CURRENT_ORG_ID;

if (hasRole('super_admin') && $org_id === null) {
    // إحصائيات عامة للنظام ككل للمدير العام
    $stats['total_organizations'] = $pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    $stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $stats['total_leaves'] = $pdo->query("SELECT COUNT(*) FROM leave_requests")->fetchColumn();
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} elseif (hasRole(['admin', 'manager']) || (hasRole('super_admin') && $org_id !== null)) {
    $current_org = $org_id;
    
    // فلترة الإحصائيات حسب جهة العمل النشطة
    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE organization_id = ?");
    $stmt1->execute([$current_org]);
    $stats['total_employees'] = $stmt1->fetchColumn();

    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE status = 'pending' AND organization_id = ?");
    $stmt2->execute([$current_org]);
    $stats['pending_approvals'] = $stmt2->fetchColumn();

    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status = 'pending' AND e.organization_id = ?");
    $stmt3->execute([$current_org]);
    $stats['pending_leaves'] = $stmt3->fetchColumn();

    $stmt4 = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status = 'approved' AND e.organization_id = ?");
    $stmt4->execute([$current_org]);
    $stats['approved_leaves'] = $stmt4->fetchColumn();

    // --- 1. أكثر أنواع الإجازات طلباً للمؤسسة ---
    $stmtTopLeaves = $pdo->prepare("
        SELECT lt.name_ar, lt.name_en, COUNT(lr.id) as count 
        FROM leave_types lt 
        LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id 
        WHERE lt.organization_id = ?
        GROUP BY lt.id 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $stmtTopLeaves->execute([$current_org]);
    $top_leave_types = $stmtTopLeaves->fetchAll();

    // --- 2. الموظفين الأكثر طلباً للإجازات للمؤسسة ---
    $stmtTopEmps = $pdo->prepare("
        SELECT e.full_name, 
               COUNT(lr.id) as total_requests,
               SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
               SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM employees e
        JOIN leave_requests lr ON e.id = lr.employee_id
        WHERE e.organization_id = ?
        GROUP BY e.id
        ORDER BY total_requests DESC
        LIMIT 5
    ");
    $stmtTopEmps->execute([$current_org]);
    $top_employees = $stmtTopEmps->fetchAll();

    // --- 3. إحصائيات الطلبات الشهرية للمؤسسة ---
    $stmtMonthly = $pdo->prepare("
        SELECT MONTH(lr.created_at) as month, COUNT(*) as count 
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND e.organization_id = ?
        GROUP BY month 
        ORDER BY month ASC
    ");
    $stmtMonthly->execute([$current_org]);
    $monthly_stats = $stmtMonthly->fetchAll();
    
    $months_labels = [];
    $months_data = [];
    foreach ($monthly_stats as $ms) {
        $months_labels[] = date("F", mktime(0, 0, 0, $ms['month'], 10));
        $months_data[] = $ms['count'];
    }

    // --- 4. جلب طلبات الإجازات المعلقة لمركز الإجراءات السريعة ---
    $stmtPending = $pdo->prepare("
        SELECT lr.*, lt.name_ar as type_ar, lt.name_en as type_en, e.full_name, e.employee_id_number, d.name_ar as dept_ar, d.name_en as dept_en
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE lr.status = 'pending' AND e.organization_id = ?
        ORDER BY lr.created_at ASC
    ");
    $stmtPending->execute([$current_org]);
    $pending_requests = $stmtPending->fetchAll();

} else {
    $emp_id = $_SESSION['employee_id'] ?? 0;
    $stmt_pending = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
    $stmt_pending->execute([$emp_id]);
    $stats['my_pending_leaves'] = $stmt_pending->fetchColumn();
    
    $stmt_approved = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'approved'");
    $stmt_approved->execute([$emp_id]);
    $stats['my_approved_leaves'] = $stmt_approved->fetchColumn();

    // جلب أرصدة الإجازات التفصيلية للمؤسسة
    $stmtBalances = $pdo->prepare("
        SELECT lt.name_ar, lt.name_en, COALESCE(elb.balance, 0) as balance 
        FROM leave_types lt
        LEFT JOIN employee_leave_balances elb ON lt.id = elb.leave_type_id AND elb.employee_id = ?
        WHERE lt.organization_id = ?
        ORDER BY lt.name_ar ASC
    ");
    $stmtBalances->execute([$emp_id, CURRENT_ORG_ID]);
    $emp_balances = $stmtBalances->fetchAll();

    $total_balance = 0;
    foreach ($emp_balances as $b) {
        $total_balance += $b['balance'];
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo __('dashboard'); ?></h1>
</div>

<?php if (hasRole('super_admin') && $org_id === null): ?>
<!-- واجهة المدير العام (Super Admin) -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('all_organizations'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['total_organizations']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-building"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('total_employees'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['total_employees']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('leaves'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['total_leaves']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-calendar"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('system_users'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['total_users']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-person"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4 text-center">
        <h4 class="fw-bold text-primary mb-2"><i class="bi bi-lightbulb"></i> <?php echo __('switch_org'); ?></h4>
        <p class="text-muted mb-0"><?php echo __('switch_org_desc'); ?></p>
    </div>
</div>

<?php elseif (hasRole(['admin', 'manager']) || (hasRole('super_admin') && $org_id !== null)): ?>
<!-- واجهة مدير النظام للجهة النشطة -->
<!-- الكروت العلوية -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('total_employees'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['total_employees']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('pending_approvals'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['pending_approvals']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-clock-history"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('pending_leaves'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['pending_leaves']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-calendar"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-uppercase small opacity-75"><?php echo __('approved_leaves'); ?></h6>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['approved_leaves']; ?></h2>
                </div>
                <div class="fs-1 opacity-50"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success shadow-sm border-0 mb-4">
        <i class="bi bi-check-circle"></i> <?php echo $success_msg; ?>
    </div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error_msg; ?>
    </div>
<?php endif; ?>

<!-- ⚡ مركز الإجراءات السريعة (طلبات الإجازة المعلقة) -->
<?php if (!empty($pending_requests)): ?>
<div class="card shadow-sm border-0 mb-4 bg-white overflow-hidden">
    <div class="card-header bg-primary text-white py-3 fw-bold d-flex align-items-center justify-content-between">
        <span><?php echo __('quick_actions_center'); ?></span>
        <span class="badge bg-light text-primary rounded-pill"><?php echo count($pending_requests); ?> <?php echo __('needs_decision'); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4"><?php echo __('employee_label'); ?></th>
                        <th><?php echo __('leave_type_label'); ?></th>
                        <th><?php echo __('leave_dates'); ?></th>
                        <th><?php echo __('work_days'); ?></th>
                        <th><?php echo __('reason'); ?></th>
                        <th><?php echo __('manager_note_optional'); ?></th>
                        <th class="pe-4 text-end"><?php echo __('quick_decision'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_requests as $req): 
                        $days = calculateLeaveDays($req['start_date'], $req['end_date'], $current_org);
                    ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?php echo h($req['full_name']); ?></div>
                                <div class="small text-muted">#<?php echo h($req['employee_id_number']); ?></div>
                            </td>
                            <td><?php echo h(get_name(['name_ar' => $req['type_ar'], 'name_en' => $req['type_en']])); ?></td>
                            <td>
                                <div class="small text-dark"><?php echo h($req['start_date']); ?> إلى <?php echo h($req['end_date']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo $days; ?> <?php echo __('days'); ?></span>
                            </td>
                            <td class="small" style="max-width: 150px;"><?php echo h($req['reason'] ?: '-'); ?></td>
                            <td>
                                <form action="index.php" method="POST" class="d-flex align-items-center">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="text" name="manager_note" class="form-control form-control-sm" placeholder="<?php echo __('write_note'); ?>">
                            </td>
                            <td class="pe-4 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="submit" name="action" value="approve" class="btn btn-success">
                                            <i class="bi bi-check-circle"></i> <?php echo __('approve'); ?>
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger">
                                            <i class="bi bi-x-circle"></i> <?php echo __('reject'); ?>
                                        </button>
                                    </div>
                                    <input type="hidden" name="action_dashboard" value="1">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- الرسم البياني للطلبات الشهرية -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold py-3">
                <i class="bi bi-bar-chart-line"></i> <?php echo __('monthly_requests'); ?>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <!-- أكثر أنواع الإجازات طلباً -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold py-3">
                <i class="bi bi-pie-chart"></i> <?php echo __('top_leave_types'); ?>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($top_leave_types as $type): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo h(get_name($type)); ?></span>
                            <span class="badge bg-primary rounded-pill"><?php echo $type['count']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- جدول أكثر الموظفين طلباً للإجازات -->
    <div class="col-lg-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3">
                <i class="bi bi-trophy"></i> <?php echo __('top_employees_leaves'); ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4"><?php echo __('full_name'); ?></th>
                                <th><?php echo __('requests'); ?></th>
                                <th><?php echo __('approved'); ?></th>
                                <th><?php echo __('rejected'); ?></th>
                                <th class="pe-4" style="width: 200px;"><?php echo __('status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_employees as $emp): 
                                $percent = ($emp['total_requests'] > 0) ? ($emp['approved_count'] / $emp['total_requests']) * 100 : 0;
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?php echo h($emp['full_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $emp['total_requests']; ?></span></td>
                                    <td><span class="text-success fw-bold"><?php echo $emp['approved_count']; ?></span></td>
                                    <td><span class="text-danger fw-bold"><?php echo $emp['rejected_count']; ?></span></td>
                                    <td class="pe-4">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round($percent); ?>% <?php echo __('approved'); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // رسم بياني للطلبات الشهرية
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months_labels); ?>,
            datasets: [{
                label: '<?php echo __('requests'); ?>',
                data: <?php echo json_encode($months_data); ?>,
                borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#0d6efd',
                backgroundColor: (getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#0d6efd').replace(/^#/, 'rgba(') + ', 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });
</script>
<?php else: ?>
<!-- واجهة الموظف البسيطة -->

<?php if (isset($emp_balances)): ?>
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-3 text-primary"><i class="bi bi-hand-wave"></i> <?php echo __('welcome'); ?>: <?php echo h($_SESSION['full_name'] ?? $_SESSION['username']); ?></h4>
        <p class="mb-3 fs-6 text-muted">
            <?php echo __('you_have_total'); ?> 
            <span class="badge bg-primary fs-6 mx-1 shadow-sm"><?php echo $total_balance; ?></span> 
            <?php echo __('days_of_leave'); ?>، <?php echo __('distributed_as_follows'); ?>:
        </p>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($emp_balances as $b): ?>
                <div class="border rounded px-3 py-2 bg-white shadow-sm d-flex align-items-center">
                    <span class="text-muted small me-2"><?php echo h(get_name($b)); ?>:</span>
                    <strong class="text-dark fs-5 mb-0 ms-auto ms-rtl-auto"><?php echo $b['balance']; ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card bg-info text-white shadow-sm border-0">
            <div class="card-body text-center py-4">
                <div class="fs-1 opacity-50 mb-3"><i class="bi bi-calendar"></i></div>
                <h5><?php echo __('my_pending_leaves'); ?></h5>
                <h2 class="fw-bold"><?php echo $stats['my_pending_leaves']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body text-center py-4">
                <div class="fs-1 opacity-50 mb-3"><i class="bi bi-check-circle"></i></div>
                <h5><?php echo __('my_approved_leaves'); ?></h5>
                <h2 class="fw-bold"><?php echo $stats['my_approved_leaves']; ?></h2>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
