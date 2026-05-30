<?php
require_once '../includes/config.php';
checkAuth();

// التحقق من أن الموظف معتمد ومن صلاحية طلب الإجازة وتأكيد الرصيد
$emp_id = $_SESSION['employee_id'] ?? 0;
$stmtCheck = $pdo->prepare("SELECT status, can_request_leave, leave_balance_verified, initial_leave_balance FROM employees WHERE id = ?");
$stmtCheck->execute([$emp_id]);
$emp_data = $stmtCheck->fetch() ?: [];
$emp_status = $emp_data['status'] ?? 'pending';
$can_request_leave = $emp_data['can_request_leave'] ?? 0;
$balance_verified = $emp_data['leave_balance_verified'] ?? 0;
$has_balance = array_key_exists('initial_leave_balance', $emp_data) && $emp_data['initial_leave_balance'] !== null;

$global_allow_leaves = getSetting('allow_leave_requests') === '1';

// منع الطلب إذا لم يتم تأكيد الرصيد
$is_restricted = ($emp_status !== 'approved' || !$balance_verified || !$has_balance);

$error = '';
$success = '';
$op_code = '';
$op_time = '';

// جلب أنواع الإجازات للجهة الحالية
$org_id = CURRENT_ORG_ID ?? 1;
$stmtTypes = $pdo->prepare("SELECT * FROM leave_types WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtTypes->execute([$org_id]);
$leave_types = $stmtTypes->fetchAll();

// جلب إعدادات حقول طلب الإجازة
$leave_reason_visible = getSetting('leave_field_reason_visible', '1') == '1';
$leave_reason_required = getSetting('leave_field_reason_required', '0') == '1';
$leave_attachment_visible = getSetting('leave_field_attachment_visible', '1') == '1';
$leave_attachment_required = getSetting('leave_field_attachment_required', '0') == '1';

// تقديم طلب جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    if (!verify_csrf()) {
        $error = __('csrf_token_invalid');
    } elseif ($is_restricted) {
        $error = __('balance_not_verified_error');
    } elseif (!$global_allow_leaves) {
        $error = __('leave_requests_disabled');
    } elseif (!$can_request_leave) {
        $error = __('leave_requests_disabled_for_you');
    } else {
        $type_id = $_POST['leave_type_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = $leave_reason_visible ? trim($_POST['reason'] ?? '') : '';
        $attachment_url = $leave_attachment_visible ? trim($_POST['attachment_url'] ?? '') : '';

        if (empty($type_id) || empty($start_date) || empty($end_date)) {
            $error = __('fill_fields_error');
        } elseif ($leave_reason_visible && $leave_reason_required && empty($reason)) {
            $error = __('reason_required');
        } elseif ($leave_attachment_visible && $leave_attachment_required && empty($attachment_url)) {
            $error = __('attachment_required');
        } elseif (strtotime($start_date) > strtotime($end_date)) {
            $error = __('end_after_start');
        } else {
            // التحقق من تاريخ الإجازة بأثر رجعي
            $allow_past = getSetting('allow_past_leaves', '0') === '1';
            $today = date('Y-m-d');
            if (!$allow_past && $start_date < $today) {
                $error = __('past_leaves_error');
            }

            if (empty($error)) {
                // التحقق من تداخل الإجازات
                $prevent_overlap = getSetting('prevent_overlapping_leaves', '1') === '1';
                if ($prevent_overlap) {
                    $stmtOverlap = $pdo->prepare("SELECT id FROM leave_requests 
                                                  WHERE employee_id = ? 
                                                  AND status IN ('pending', 'approved') 
                                                  AND start_date <= ? 
                                                  AND end_date >= ?");
                    $stmtOverlap->execute([$emp_id, $end_date, $start_date]);
                    if ($stmtOverlap->rowCount() > 0) {
                        $error = __('overlapping_leaves_error');
                    }
                }
            }

            if (empty($error)) {
                // التحقق من توفر الرصيد والحد الأقصى (نظام أوراكل)
                $days_requested = calculateLeaveDays($start_date, $end_date, CURRENT_ORG_ID);
                
                $stmt_type = $pdo->prepare("SELECT id, deduct_from_balance, max_days_per_year FROM leave_types WHERE id = ? AND organization_id = ?");
                $stmt_type->execute([$type_id, $org_id]);
                $type_info = $stmt_type->fetch();

                if (!$type_info) {
                    $error = __('access_denied');
                } elseif ($type_info['deduct_from_balance'] && $days_requested > ($emp_data['initial_leave_balance'] ?? 0)) {
                    $error = __('insufficient_balance');
                } elseif ($days_requested > $type_info['max_days_per_year']) {
                    $error = __('max_days_per_year_error') . ": " . $type_info['max_days_per_year'];
                } else {
                    $request_code = generateOperationCode('LV');
                    $stmt = $pdo->prepare("INSERT INTO leave_requests (organization_id, employee_id, leave_type_id, start_date, end_date, reason, attachment_url, status, request_code) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                    if ($stmt->execute([$org_id, $emp_id, $type_id, $start_date, $end_date, $reason, $attachment_url, $request_code])) {
                        $success = __('success_added'); 
                        $op_code = $request_code;
                        $op_time = date('Y-m-d H:i:s');
                    } else {
                        $error = __('db_error');
                    }
                }
            }
        }
    }
}

// جلب طلباتي السابقة
$stmtMyRequests = $pdo->prepare("SELECT lr.*, lt.name_ar as type_ar, lt.name_en as type_en 
                                 FROM leave_requests lr 
                                 JOIN leave_types lt ON lr.leave_type_id = lt.id 
                                 WHERE lr.employee_id = ? 
                                 ORDER BY lr.created_at DESC");
$stmtMyRequests->execute([$emp_id]);
$my_requests = $stmtMyRequests->fetchAll();

$pageTitle = __('my_requests');
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo __('my_requests'); ?></h1>
    <?php if ($global_allow_leaves && $can_request_leave && !$is_restricted): ?>
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#requestModal">
            <?php echo __('new_request'); ?>
        </button>
    <?php else: ?>
        <button type="button" class="btn btn-secondary shadow-sm" disabled>
            <?php echo __('new_request'); ?>
        </button>
    <?php endif; ?>
</div>

<?php if ($is_restricted): ?>
    <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center mb-4">
        <i class="bi bi-lock"></i>
        <div>
            <h6 class="fw-bold mb-1"><?php echo __('balance_not_verified_error'); ?></h6>
            <p class="small mb-0 opacity-75"><?php echo __('ensure_balance_verified'); ?></p>
        </div>
    </div>
<?php elseif (!$global_allow_leaves): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4">
        <?php echo __('leave_requests_disabled'); ?>
    </div>
<?php elseif (!$can_request_leave): ?>
    <div class="alert alert-warning shadow-sm border-0 mb-4">
        <?php echo __('leave_requests_disabled_for_you'); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <h5 class="fw-bold"><?php echo __('success_added'); ?></h5>
        <hr>
        <div class="row text-start small">
            <div class="col-6 mb-2"><strong><?php echo __('operation_code'); ?>:</strong></div>
            <div class="col-6 mb-2 text-primary fw-bold"><?php echo $op_code; ?></div>
            <div class="col-6"><strong><?php echo __('operation_time'); ?>:</strong></div>
            <div class="col-6 text-muted"><?php echo $op_time; ?> (Makkah)</div>
        </div>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold"><?php echo __('my_requests'); ?></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light small">
                    <tr>
                        <th><?php echo __('leave_type_label'); ?></th>
                        <th><?php echo __('duration'); ?></th>
                        <th><?php echo __('request_dates'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('manager_note'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_requests)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted small"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($my_requests as $req): 
                            $days = calculateLeaveDays($req['start_date'], $req['end_date'], CURRENT_ORG_ID);
                        ?>
                            <tr>
                                <td><?php echo h(get_name(['name_ar' => $req['type_ar'], 'name_en' => $req['type_en']])); ?></td>
                                <td>
                                    <div class="small"><?php echo h($req['start_date']); ?> to <?php echo h($req['end_date']); ?></div>
                                    <div class="badge bg-light text-dark border mt-1 shadow-sm"><?php echo $days; ?> <?php echo __('days'); ?></div>
                                </td>
                                <td>
                                    <div class="small text-muted mb-1">
                                        <span class="fs-6 opacity-75"><i class="bi bi-calendar"></i></span> <?php echo __('submitted_on'); ?>:<br>
                                        <span class="fw-bold text-dark"><?php echo date('Y-m-d h:i A', strtotime($req['created_at'])); ?></span>
                                    </div>
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
                                            <span class="opacity-75"><i class="bi bi-pin"></i></span> <?php echo $req['action_at'] ? date('Y-m-d h:i A', strtotime($req['action_at'])) : date('Y-m-d h:i A', strtotime($req['created_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?php echo h($req['manager_note'] ?: '-'); ?>
                                    <?php if (!empty($req['attachment_url'])): ?>
                                        <div class="mt-2">
                                            <a href="<?php echo htmlspecialchars($req['attachment_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                                <i class="fas fa-file-alt"></i> <?php echo __('view_attachment'); ?>
                                            </a>
                                        </div>
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

<!-- Modal طلب إجازة -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('new_request'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="my_requests.php" method="POST">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('type_name'); ?> *</label>
                        <select name="leave_type_id" class="form-select" required>
                            <option value="">-- <?php echo __('type_name'); ?> --</option>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo h(get_name($type)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <?php $min_date_attr = getSetting('allow_past_leaves', '0') === '1' ? '' : 'min="' . date('Y-m-d') . '"'; ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('start_date'); ?> *</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" required <?php echo $min_date_attr; ?>>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('end_date'); ?> *</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" required <?php echo $min_date_attr; ?>>
                        </div>
                    </div>
                    <div id="days_display" class="alert alert-info py-2 small d-none shadow-sm">
                        <?php echo __('leave_days_count'); ?>: <span id="days_count" class="fw-bold">0</span>
                    </div>
                    <?php if ($leave_attachment_visible): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('attachment_field'); ?> <?php echo $leave_attachment_required ? '*' : '(اختياري)'; ?></label>
                        <div class="d-grid">
                            <button type="button" id="upload_widget" class="btn btn-outline-secondary border-dashed text-primary shadow-sm" style="border-style: dashed; border-width: 2px;">
                                <i class="fas fa-cloud-upload-alt me-2"></i> <?php echo __('upload_attachment'); ?>
                            </button>
                        </div>
                        <input type="hidden" name="attachment_url" id="attachment_url" <?php echo $leave_attachment_required ? 'required' : ''; ?>>
                        <small id="upload_success_msg" class="text-success d-none mt-1 fw-bold"><i class="fas fa-check-circle"></i> <?php echo __('file_uploaded'); ?></small>
                    </div>
                    <?php endif; ?>

                    <?php if ($leave_reason_visible): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('reason'); ?> <?php echo $leave_reason_required ? '*' : ''; ?></label>
                        <textarea name="reason" class="form-control" rows="3" <?php echo $leave_reason_required ? 'required' : ''; ?>></textarea>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="submit_request" class="btn btn-primary px-4 shadow-sm"><?php echo __('submit'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const daysDisplay = document.getElementById('days_display');
    const daysCount = document.getElementById('days_count');

    function calculateDays() {
        const start = startDateInput.value;
        const end = endDateInput.value;

        if (start && end) {
            const startDate = new Date(start);
            const endDate = new Date(end);

            if (endDate >= startDate) {
                // حسبة النظام السعودي (<?php echo __('calendar_days'); ?> تشمل الويكند)
                const diffTime = Math.abs(endDate - startDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                
                daysCount.textContent = diffDays;
                daysDisplay.classList.remove('d-none');
                
                // إضافة لمسة جمالية للإشارة أن الحسبة تقويمية
                const suffix = <?php echo json_encode(__('calendar_days_suffix')); ?>;
                daysCount.textContent += ' ' + suffix;
            } else {
                daysDisplay.classList.add('d-none');
            }
        } else {
            daysDisplay.classList.add('d-none');
        }
    }

    startDateInput.addEventListener('change', calculateDays);
    endDateInput.addEventListener('change', calculateDays);
});
</script>

<script src="https://upload-widget.cloudinary.com/global/all.js" type="text/javascript"></script>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var myWidget = cloudinary.createUploadWidget({
        cloudName: 'dbvx6lbko', 
        uploadPreset: 'ml_default',
        sources: ['local', 'camera'],
        showAdvancedOptions: false,
        cropping: false,
        multiple: false,
        defaultSource: 'local',
        styles: {
            palette: {
                window: "#FFFFFF",
                windowBorder: "#90A0B3",
                tabIcon: "#0078FF",
                menuIcons: "#5A616A",
                textDark: "#000000",
                textLight: "#FFFFFF",
                link: "#0078FF",
                action: "#FF620C",
                inactiveTabIcon: "#0E2F5A",
                error: "#F44235",
                inProgress: "#0078FF",
                complete: "#20B832",
                sourceBg: "#E4EBF1"
            }
        }
    }, (error, result) => { 
        if (!error && result && result.event === "success") { 
            console.log('Done! Here is the image info: ', result.info); 
            document.getElementById('attachment_url').value = result.info.secure_url;
            
            // تحديث زر الرفع للنجاح
            const uploadBtn = document.getElementById('upload_widget');
            uploadBtn.classList.remove('btn-outline-secondary', 'text-primary');
            uploadBtn.classList.add('btn-success', 'text-white');
            uploadBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> <?php echo __('file_uploaded_btn'); ?>';
            
            document.getElementById('upload_success_msg').classList.remove('d-none');
        }
    });

    document.getElementById("upload_widget").addEventListener("click", function(){
        myWidget.open();
    }, false);
});
</script>

<?php include '../includes/footer.php'; ?>
