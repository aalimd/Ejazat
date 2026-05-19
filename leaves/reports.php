<?php
require_once '../includes/config.php';
checkAuth(['admin']); // فقط مدراء الجهات

$org_id = CURRENT_ORG_ID ?? 1;
$success = '';
$error = '';

// 1. معالجة الفلاتر والبحث
$where = ["e.organization_id = ?"];
$params = [$org_id];

if (!empty($_GET['search'])) {
    $where[] = "(e.full_name LIKE ? OR e.employee_id_number LIKE ? OR lr.request_code LIKE ?)";
    $search_val = '%' . $_GET['search'] . '%';
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
}

if (!empty($_GET['department_id'])) {
    $where[] = "e.department_id = ?";
    $params[] = (int)$_GET['department_id'];
}

if (!empty($_GET['leave_type_id'])) {
    $where[] = "lr.leave_type_id = ?";
    $params[] = (int)$_GET['leave_type_id'];
}

if (!empty($_GET['status'])) {
    $where[] = "lr.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['from_date'])) {
    $where[] = "lr.start_date >= ?";
    $params[] = $_GET['from_date'];
}

if (!empty($_GET['to_date'])) {
    $where[] = "lr.end_date <= ?";
    $params[] = $_GET['to_date'];
}

$whereClause = "WHERE " . implode(" AND ", $where);

// استعلام جلب تقارير الإجازات
$query = "SELECT lr.*, e.full_name, e.employee_id_number, d.name_ar as dept_ar, d.name_en as dept_en,
                 lt.name_ar as type_ar, lt.name_en as type_en
          FROM leave_requests lr
          JOIN employees e ON lr.employee_id = e.id
          LEFT JOIN departments d ON e.department_id = d.id
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          $whereClause
          ORDER BY lr.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// حساب الإحصائيات للطلبات المفلترة
$stats = [
    'total_requests' => count($requests),
    'total_days' => 0,
    'approved_count' => 0,
    'pending_count' => 0,
];

foreach ($requests as $r) {
    $days = calculateLeaveDays($r['start_date'], $r['end_date'], $org_id);
    $stats['total_days'] += $days;
    if ($r['status'] === 'approved') {
        $stats['approved_count']++;
    } elseif ($r['status'] === 'pending') {
        $stats['pending_count']++;
    }
}

// 2. تصدير التقرير المفلتر إلى Excel (CSV)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Leave_Report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // إضافة UTF-8 BOM لحماية اللغة العربية والرموز
    fputs($output, "\xEF\xBB\xBF");
    
    // العناوين
    fputcsv($output, ['كود الطلب', 'الرقم الوظيفي', 'اسم الموظف', 'القسم', 'نوع الإجازة', 'تاريخ البدء', 'تاريخ الانتهاء', 'أيام العمل الفعلية', 'الحالة', 'تاريخ التقديم']);
    
    // البيانات
    foreach ($requests as $r) {
        $days = calculateLeaveDays($r['start_date'], $r['end_date'], $org_id);
        $status_text = $r['status'] == 'approved' ? 'مقبولة' : ($r['status'] == 'rejected' ? 'مرفوضة' : 'معلقة');
        fputcsv($output, [
            $r['request_code'],
            $r['employee_id_number'],
            $r['full_name'],
            $r['dept_ar'],
            $r['type_ar'],
            $r['start_date'],
            $r['end_date'],
            $days,
            $status_text,
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// جلب بيانات الفلترة للمنشأة النشطة
$stmt_depts = $pdo->prepare("SELECT * FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
$stmt_depts->execute([$org_id]);
$departments = $stmt_depts->fetchAll();

$stmt_types = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
$stmt_types->execute([$org_id]);
$leave_types = $stmt_types->fetchAll();

$pageTitle = __('leave_reports');
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <div class="d-flex align-items-center">
        <div class="bg-primary text-white rounded p-2 me-3">
            📊
        </div>
        <h1 class="h3 mb-0"><?php echo __('leave_reports'); ?></h1>
    </div>
    
    <div class="mt-2 mt-md-0">
        <!-- رابط التصدير بالاعتماد على معايير الفلترة الحالية -->
        <?php 
            $query_params = $_GET;
            $query_params['export'] = 'excel';
            $export_url = 'reports.php?' . http_build_query($query_params);
        ?>
        <a href="<?php echo $export_url; ?>" class="btn btn-success shadow-sm">
            📥 تصدير التقرير الحالي إلى Excel
        </a>
    </div>
</div>

<!-- بطاقات الإحصائيات للبحث الحالي -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100 bg-light text-dark">
            <div class="card-body py-3 d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted small mb-1"><?php echo __('total_requests'); ?></h6>
                    <h3 class="fw-bold mb-0 text-primary"><?php echo $stats['total_requests']; ?></h3>
                </div>
                <div class="fs-2 opacity-50">📂</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100 bg-light text-dark">
            <div class="card-body py-3 d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted small mb-1">إجمالي أيام العمل الفعلية</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $stats['total_days']; ?></h3>
                </div>
                <div class="fs-2 opacity-50">📆</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100 bg-light text-dark">
            <div class="card-body py-3 d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted small mb-1">الطلبات المعتمدة</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $stats['approved_count']; ?></h3>
                </div>
                <div class="fs-2 opacity-50">✅</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100 bg-light text-dark">
            <div class="card-body py-3 d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted small mb-1">الطلبات المعلقة</h6>
                    <h3 class="fw-bold mb-0 text-warning"><?php echo $stats['pending_count']; ?></h3>
                </div>
                <div class="fs-2 opacity-50">⏳</div>
            </div>
        </div>
    </div>
</div>

<!-- فلترة التقرير -->
<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-body p-4">
        <h5 class="fw-bold text-dark mb-3">🔍 تصفية وبحث متقدم</h5>
        <form action="reports.php" method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">اسم الموظف أو كود الطلب</label>
                <input type="text" name="search" class="form-control" placeholder="البحث..." value="<?php echo h($_GET['search'] ?? ''); ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">القسم</label>
                <select name="department_id" class="form-select">
                    <option value=""><?php echo __('all'); ?></option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo (($_GET['department_id'] ?? '') == $d['id']) ? 'selected' : ''; ?>>
                            <?php echo h($d['name_ar']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">نوع الإجازة</label>
                <select name="leave_type_id" class="form-select">
                    <option value=""><?php echo __('all'); ?></option>
                    <?php foreach ($leave_types as $lt): ?>
                        <option value="<?php echo $lt['id']; ?>" <?php echo (($_GET['leave_type_id'] ?? '') == $lt['id']) ? 'selected' : ''; ?>>
                            <?php echo h($lt['name_ar']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">الحالة</label>
                <select name="status" class="form-select">
                    <option value=""><?php echo __('all'); ?></option>
                    <option value="pending" <?php echo (($_GET['status'] ?? '') == 'pending') ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                    <option value="approved" <?php echo (($_GET['status'] ?? '') == 'approved') ? 'selected' : ''; ?>><?php echo __('approved'); ?></option>
                    <option value="rejected" <?php echo (($_GET['status'] ?? '') == 'rejected') ? 'selected' : ''; ?>><?php echo __('rejected'); ?></option>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">من تاريخ البدء</label>
                <input type="date" name="from_date" class="form-control" value="<?php echo h($_GET['from_date'] ?? ''); ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">إلى تاريخ الانتهاء</label>
                <input type="date" name="to_date" class="form-control" value="<?php echo h($_GET['to_date'] ?? ''); ?>">
            </div>
            
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                    🔍 تحديث وتصفية التقرير
                </button>
            </div>
        </form>
    </div>
</div>

<!-- جدول التقارير -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">الموظف</th>
                        <th>القسم</th>
                        <th>كود الطلب</th>
                        <th>نوع الإجازة</th>
                        <th>التاريخ والمدة</th>
                        <th>أيام العمل الفعلية</th>
                        <th>حالة الطلب</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): 
                            $days = calculateLeaveDays($r['start_date'], $r['end_date'], $org_id);
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?php echo h($r['full_name']); ?></div>
                                    <div class="small text-muted">#<?php echo h($r['employee_id_number']); ?></div>
                                </td>
                                <td><?php echo h(get_name(['name_ar' => $r['dept_ar'], 'name_en' => $r['dept_en']])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo h($r['request_code']); ?></span></td>
                                <td><?php echo h(get_name(['name_ar' => $r['type_ar'], 'name_en' => $r['type_en']])); ?></td>
                                <td>
                                    <div class="small text-dark"><?php echo h($r['start_date']); ?> إلى <?php echo h($r['end_date']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary rounded-pill px-3"><?php echo $days; ?> يوم</span>
                                </td>
                                <td>
                                    <?php if ($r['status'] == 'approved'): ?>
                                        <span class="badge bg-success"><?php echo __('approved'); ?></span>
                                    <?php elseif ($r['status'] == 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><?php echo __('pending'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?php echo __('rejected'); ?></span>
                                    <?php endif; ?>
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
