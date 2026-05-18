<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$success = '';
$error = '';

// معالجة تحديث صلاحية طلب الإجازة للموظف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave_perm'])) {
    $emp_id = $_POST['emp_id'];
    $can_request = isset($_POST['can_request']) ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE employees SET can_request_leave = ? WHERE id = ?");
    if ($stmt->execute([$can_request, $emp_id])) {
        // جلب اسم الموظف للسجل
        $stmt_name = $pdo->prepare("SELECT full_name FROM employees WHERE id = ?");
        $stmt_name->execute([$emp_id]);
        $full_name = $stmt_name->fetchColumn();
        
        $status_text = $can_request ? "تفعيل" : "إيقاف";
        $status_en = $can_request ? "Enabled" : "Disabled";
        
        logActivity("$status_text صلاحية الإجازة للموظف", "$status_en Leave Permission for Employee", "Employee: $full_name (ID: $emp_id)");
        $success = __('success_updated');
    }
}


// Filters
$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(e.full_name LIKE ? OR e.system_id LIKE ? OR e.employee_id_number LIKE ?)";
    $search_val = '%' . $_GET['search'] . '%';
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
}
if (!empty($_GET['department_id'])) {
    $where[] = "e.department_id = ?";
    $params[] = $_GET['department_id'];
}
if (!empty($_GET['status'])) {
    $where[] = "e.status = ?";
    $params[] = $_GET['status'];
}

$whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$query = "SELECT e.*, d.name_ar as dept_ar, d.name_en as dept_en, u.email as user_email 
          FROM employees e 
          LEFT JOIN departments d ON e.department_id = d.id 
          LEFT JOIN users u ON e.user_id = u.id 
          $whereClause
          ORDER BY e.created_at DESC";
          
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Fetch departments for filter
$departments = $pdo->query("SELECT * FROM departments ORDER BY name_ar ASC")->fetchAll();

// Excel (CSV) Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Employees_List_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for proper Arabic rendering in Excel/Numbers
    fputs($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, ['المعرف الخاص', 'الرقم الوظيفي', 'الاسم الكامل', 'القسم', 'المسمى الوظيفي', 'تاريخ التوظيف', 'حالة الحساب']);
    
    // Data
    foreach ($employees as $emp) {
        $status_text = $emp['status'] == 'approved' ? 'مفعل' : ($emp['status'] == 'rejected' ? 'مرفوض' : 'معلق');
        fputcsv($output, [
            $emp['system_id'],
            $emp['employee_id_number'],
            $emp['full_name'],
            $emp['dept_ar'],
            $emp['job_title'],
            $emp['hire_date'],
            $status_text
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = __('employees');
include '../includes/header.php';
?>


<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
    <h1 class="h3 mb-3 mb-md-0"><?php echo __('employees'); ?></h1>
    <div>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success shadow-sm me-2">
            📊 <?php echo __('export_excel') ?? 'تصدير إكسل'; ?>
        </a>
        <a href="add.php" class="btn btn-primary shadow-sm">
            <?php echo __('add_new'); ?>
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" action="list.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-bold"><?php echo __('global_search'); ?></label>
                <input type="text" name="search" class="form-control" placeholder="<?php echo __('search_placeholder'); ?>" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold"><?php echo __('department'); ?></label>
                <select name="department_id" class="form-select">
                    <option value=""><?php echo __('all_departments'); ?></option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo (($_GET['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name_ar']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold"><?php echo __('account_status'); ?></label>
                <select name="status" class="form-select">
                    <option value=""><?php echo __('all'); ?></option>
                    <option value="pending" <?php echo (($_GET['status'] ?? '') == 'pending') ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                    <option value="approved" <?php echo (($_GET['status'] ?? '') == 'approved') ? 'selected' : ''; ?>><?php echo __('approved'); ?></option>
                    <option value="rejected" <?php echo (($_GET['status'] ?? '') == 'rejected') ? 'selected' : ''; ?>><?php echo __('rejected'); ?></option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 shadow-sm">🔍 <?php echo __('filter_btn'); ?></button>
            </div>
        </form>
    </div>
</div>


<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 mb-4"><?php echo $success; ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3"><?php echo __('system_id'); ?></th>
                        <th><?php echo __('full_name'); ?></th>
                        <th><?php echo __('department'); ?></th>
                        <th><?php echo __('job_title'); ?></th>
                        <th><?php echo __('allow_leave_requests'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th class="pe-3"><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary small"><?php echo h($emp['system_id']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo h($emp['full_name']); ?></div>
                                    <div class="x-small text-muted">#<?php echo h($emp['employee_id_number']); ?></div>
                                </td>
                                <td><?php echo h(get_name(['name_ar' => $emp['dept_ar'], 'name_en' => $emp['dept_en']])); ?></td>
                                <td><?php echo h($emp['job_title']); ?></td>
                                <td>
                                    <?php if ($emp['status'] == 'approved'): ?>
                                        <form action="list.php" method="POST" class="d-inline">
                                            <input type="hidden" name="emp_id" value="<?php echo $emp['id']; ?>">
                                            <input type="hidden" name="update_leave_perm" value="1">
                                            <div class="form-check form-switch fs-5">
                                                <input class="form-check-input" type="checkbox" name="can_request" onchange="this.form.submit()" <?php echo $emp['can_request_leave'] ? 'checked' : ''; ?> title="<?php echo __('allow_leave_requests'); ?>">
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['status'] == 'approved'): ?>
                                        <span class="badge bg-success-subtle text-success px-3"><?php echo __('approved'); ?></span>
                                    <?php elseif ($emp['status'] == 'pending'): ?>
                                        <span class="badge bg-warning-subtle text-warning px-3"><?php echo __('pending'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger px-3"><?php echo __('rejected'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3">
                                    <div class="btn-group shadow-sm rounded">
                                        <a href="view.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-white border" title="<?php echo __('view'); ?>">
                                            👁️
                                        </a>
                                        <a href="edit.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-white border" title="<?php echo __('edit'); ?>">
                                            ✏️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .bg-success-subtle { background-color: rgba(25, 135, 84, 0.1) !important; }
    .bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1) !important; }
    .bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1) !important; }
    .btn-white { background-color: #fff; color: #333; }
    .btn-white:hover { background-color: #f8f9fa; }
    .form-check-input:checked {
        background-color: #198754;
        border-color: #198754;
    }
</style>

<?php include '../includes/footer.php'; ?>
