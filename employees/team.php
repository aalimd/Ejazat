<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$org_id = CURRENT_ORG_ID;
$manager_dept_id = null;

// If manager (not admin), find their department
if (hasRole('manager') && !hasRole('admin')) {
    $emp_id = $_SESSION['employee_id'] ?? 0;
    $stmtDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ? AND organization_id = ?");
    $stmtDept->execute([$emp_id, $org_id]);
    $manager_dept_id = $stmtDept->fetchColumn();
}

// Filters
$where = ["e.organization_id = ?"];
$params = [$org_id];

if ($manager_dept_id) {
    $where[] = "e.department_id = ?";
    $params[] = $manager_dept_id;
}

if (!empty($_GET['department_id'])) {
    $allowed = (int)$_GET['department_id'];
    if (!$manager_dept_id || $allowed == $manager_dept_id) {
        $where[] = "e.department_id = ?";
        $params[] = $allowed;
    }
}

if (!empty($_GET['search'])) {
    $where[] = "(e.full_name LIKE ? OR e.employee_id_number LIKE ?)";
    $s = '%' . $_GET['search'] . '%';
    $params[] = $s;
    $params[] = $s;
}

$whereClause = "WHERE " . implode(" AND ", $where);

$query = "SELECT e.*, d.name_ar as dept_ar, d.name_en as dept_en 
          FROM employees e 
          LEFT JOIN departments d ON e.department_id = d.id 
          $whereClause
          ORDER BY e.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$team = $stmt->fetchAll();

// Fetch departments for filter
$stmtDepts = $pdo->prepare("SELECT * FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtDepts->execute([$org_id]);
$departments = $stmtDepts->fetchAll();

$pageTitle = __('my_team');
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
    <h1 class="h3 mb-3 mb-md-0"><?php echo __('my_team'); ?></h1>
    <div>
        <a href="list.php" class="btn btn-outline-primary shadow-sm">
            <?php echo __('all_employees'); ?>
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-bold"><?php echo __('global_search'); ?></label>
                <input type="text" name="search" class="form-control" placeholder="<?php echo __('employee_name_or_id'); ?>" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <?php if (!$manager_dept_id): ?>
            <div class="col-md-4">
                <label class="form-label small fw-bold"><?php echo __('department'); ?></label>
                <select name="department_id" class="form-select">
                    <option value=""><?php echo __('all'); ?></option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo (($_GET['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>><?php echo h(get_name($dept)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 shadow-sm"><?php echo __('filter_btn'); ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3"><?php echo __('full_name'); ?></th>
                        <th><?php echo __('department'); ?></th>
                        <th><?php echo __('job_title'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th class="pe-3"><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($team)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($team as $emp): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold"><?php echo h($emp['full_name']); ?></div>
                                    <div class="x-small text-muted">#<?php echo h($emp['employee_id_number']); ?></div>
                                </td>
                                <td><?php echo h(get_name(['name_ar' => $emp['dept_ar'], 'name_en' => $emp['dept_en']])); ?></td>
                                <td><?php echo h($emp['job_title']); ?></td>
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
                                    <a href="view.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-outline-primary"><?php echo __('view'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
