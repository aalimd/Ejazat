<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$success = '';
$error = '';

// معالجة القرار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action']; // approve or reject
    $note = trim($_POST['manager_note'] ?? '');

    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    try {
        $pdo->beginTransaction();

        // جلب بيانات الطلب ونوع الإجازة مع التأكد من ملكية المؤسسة
        $stmt_req = $pdo->prepare("SELECT lr.*, lt.deduct_from_balance 
                                   FROM leave_requests lr 
                                   JOIN leave_types lt ON lr.leave_type_id = lt.id 
                                   WHERE lr.id = ? AND lr.organization_id = ?");
        $stmt_req->execute([$request_id, CURRENT_ORG_ID]);
        $req_data = $stmt_req->fetch();

        if ($req_data) {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, manager_note = ?, action_at = NOW() WHERE id = ? AND organization_id = ?");
            $stmt->execute([$status, $note, $request_id, CURRENT_ORG_ID]);

            // خصم الرصيد إذا تمت الموافقة وكان النوع يتطلب ذلك
            if ($status === 'approved' && $req_data['deduct_from_balance']) {
                $days = calculateLeaveDays($req_data['start_date'], $req_data['end_date'], CURRENT_ORG_ID);
                $stmt_deduct = $pdo->prepare("UPDATE employees SET initial_leave_balance = initial_leave_balance - ? WHERE id = ? AND organization_id = ?");
                $stmt_deduct->execute([$days, $req_data['employee_id'], CURRENT_ORG_ID]);
            }

            // جلب بيانات الموظف لإرسال إشعار
            $stmt_user = $pdo->prepare("SELECT user_id FROM employees WHERE id = ? AND organization_id = ?");
            $stmt_user->execute([$req_data['employee_id'], CURRENT_ORG_ID]);
            $user_id = $stmt_user->fetchColumn();

            if ($status === 'approved') {
                logActivity("✅ الموافقة على إجازة", "✅ Approve Leave Request", "Request ID: $request_id, Deducted: " . ($req_data['deduct_from_balance'] ? 'Yes' : 'No'), CURRENT_ORG_ID);
                addNotification($user_id, __('leave_request_approved'), __('leave_request_approved'), CURRENT_ORG_ID);
            } else {
                logActivity("❌ رفض طلب إجازة", "❌ Reject Leave Request", "Request ID: $request_id, Note: $note", CURRENT_ORG_ID);
                addNotification($user_id, __('leave_request_rejected') . ": " . $note, __('leave_request_rejected') . ": " . $note, CURRENT_ORG_ID);
            }

            $pdo->commit();
            $success = __('success_updated');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}


// Filters
$where = ["lr.organization_id = ?"];
$params = [CURRENT_ORG_ID];

if (!empty($_GET['status'])) {
    $where[] = "lr.status = ?";
    $params[] = $_GET['status'];
}
if (!empty($_GET['employee'])) {
    $where[] = "(e.full_name LIKE ? OR e.employee_id_number LIKE ?)";
    $params[] = '%' . $_GET['employee'] . '%';
    $params[] = '%' . $_GET['employee'] . '%';
}
if (!empty($_GET['type'])) {
    $where[] = "lr.leave_type_id = ?";
    $params[] = $_GET['type'];
}

$whereClause = "WHERE " . implode(" AND ", $where);

$query = "SELECT lr.*, lt.name_ar as type_ar, lt.name_en as type_en, e.full_name, e.employee_id_number 
          FROM leave_requests lr 
          JOIN leave_types lt ON lr.leave_type_id = lt.id 
          JOIN employees e ON lr.employee_id = e.id 
          $whereClause
          ORDER BY CASE WHEN lr.status = 'pending' THEN 1 ELSE 2 END, lr.created_at DESC";
          
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// جلب أنواع الإجازات للفلاتر
$stmt_types = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
$stmt_types->execute([CURRENT_ORG_ID]);
$leave_types = $stmt_types->fetchAll();

// Excel (CSV) Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Leave_Requests_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for proper Arabic rendering in Excel/Numbers
    fputs($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, ['الموظف', 'الرقم الوظيفي', 'نوع الإجازة', 'من تاريخ', 'إلى تاريخ', 'تاريخ التقديم', 'الحالة', 'تاريخ القرار', 'السبب', 'رابط الإثبات']);
    
    // Data
    foreach ($requests as $req) {
        $status_text = $req['status'] == 'approved' ? 'مقبول' : ($req['status'] == 'rejected' ? 'مرفوض' : 'معلق');
        fputcsv($output, [
            $req['full_name'],
            $req['employee_id_number'],
            $req['type_ar'],
            $req['start_date'],
            $req['end_date'],
            date('Y-m-d', strtotime($req['created_at'])),
            $status_text,
            $req['action_at'] ? date('Y-m-d', strtotime($req['action_at'])) : '-',
            $req['reason'],
            $req['attachment_url'] ? $req['attachment_url'] : 'لا يوجد'
        ]);
    }
    fclose($output);
    exit;
}


$pageTitle = __('leaves');
include '../includes/header.php';
?>


<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
    <h1 class="h3 mb-3 mb-md-0"><?php echo __('leaves'); ?></h1>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success shadow-sm">
        📊 <?php echo __('export_excel') ?? 'تصدير إكسل'; ?>
    </a>
</div>

<!-- Filters Card -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" action="manage.php" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold"><?php echo __('search_by_employee'); ?></label>
                <input type="text" name="employee" class="form-control" placeholder="<?php echo __('employee_name_or_id'); ?>" value="<?php echo htmlspecialchars($_GET['employee'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold"><?php echo __('leave_status'); ?></label>
                <select name="status" class="form-select">
                    <option value=""><?php echo __('all'); ?></option>
                    <option value="pending" <?php echo (($_GET['status'] ?? '') == 'pending') ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                    <option value="approved" <?php echo (($_GET['status'] ?? '') == 'approved') ? 'selected' : ''; ?>><?php echo __('approved'); ?></option>
                    <option value="rejected" <?php echo (($_GET['status'] ?? '') == 'rejected') ? 'selected' : ''; ?>><?php echo __('rejected'); ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold"><?php echo __('leave_type_label'); ?></label>
                <select name="type" class="form-select">
                    <option value=""><?php echo __('all'); ?></option>
                    <?php foreach ($leave_types as $lt): ?>
                        <option value="<?php echo $lt['id']; ?>" <?php echo (($_GET['type'] ?? '') == $lt['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lt['name_ar']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 shadow-sm">🔍 <?php echo __('filter_btn'); ?></button>
            </div>
        </form>
    </div>
</div>


<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?php echo __('employees'); ?></th>
                        <th><?php echo __('leave_type_label'); ?></th>
                        <th><?php echo __('duration'); ?></th>
                        <th><?php echo __('request_dates'); ?></th>
                        <th><?php echo __('reason'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): 
                            $days = calculateLeaveDays($req['start_date'], $req['end_date'], CURRENT_ORG_ID);
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo h($req['full_name']); ?></div>
                                    <div class="small text-muted">#<?php echo h($req['employee_id_number']); ?></div>
                                </td>
                                <td><?php echo h(get_name(['name_ar' => $req['type_ar'], 'name_en' => $req['type_en']])); ?></td>
                                <td>
                                    <div class="small"><?php echo h($req['start_date']); ?> to <?php echo h($req['end_date']); ?></div>
                                    <div class="badge bg-light text-dark border"><?php echo $days; ?> <?php echo __('days'); ?></div>
                                </td>
                                <td>
                                    <div class="small text-muted mb-1">
                                        <span class="fs-6 opacity-75">📅</span> <?php echo __('submitted_on'); ?>:<br>
                                        <span class="fw-bold text-dark"><?php echo date('Y-m-d h:i A', strtotime($req['created_at'])); ?></span>
                                    </div>
                                </td>
                                <td class="small" style="max-width: 200px;">
                                    <?php echo h($req['reason'] ?: '-'); ?>
                                    <?php if (!empty($req['attachment_url'])): ?>
                                        <div class="mt-2">
                                            <a href="<?php echo htmlspecialchars($req['attachment_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                                <i class="fas fa-file-alt"></i> عرض الإثبات
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req['status'] == 'approved'): ?>
                                        <div class="mb-1"><span class="badge bg-success"><?php echo __('approved'); ?></span></div>
                                    <?php elseif ($req['status'] == 'pending'): ?>
                                        <div class="mb-1"><span class="badge bg-warning text-dark"><?php echo __('pending'); ?></span></div>
                                    <?php else: ?>
                                        <div class="mb-1"><span class="badge bg-danger"><?php echo __('rejected'); ?></span></div>
                                    <?php endif; ?>

                                    <?php if ($req['status'] != 'pending'): ?>
                                        <div class="small text-muted" style="font-size: 0.75rem;">
                                            <span class="opacity-75">📌</span> <?php echo $req['action_at'] ? date('Y-m-d h:i A', strtotime($req['action_at'])) : date('Y-m-d h:i A', strtotime($req['created_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req['status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#actionModal<?php echo $req['id']; ?>">
                                            <?php echo __('actions'); ?>
                                        </button>
                                        
                                        <!-- Modal اتخاذ قرار -->
                                        <div class="modal fade" id="actionModal<?php echo $req['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo __('leave_details'); ?>: <?php echo h($req['full_name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="manage.php" method="POST">
                                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold"><?php echo __('manager_note'); ?></label>
                                                                <textarea name="manager_note" class="form-control" rows="3"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" name="action" value="reject" class="btn btn-danger"><?php echo __('reject'); ?></button>
                                                            <button type="submit" name="action" value="approve" class="btn btn-success"><?php echo __('approve'); ?></button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="small text-muted"><?php echo h($req['manager_note'] ?: '-'); ?></span>
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
