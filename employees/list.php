<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave_perm'])) {
    if (!verify_csrf()) {
        $error = __('csrf_token_invalid');
    } else {
    $emp_id = $_POST['emp_id'];
    $can_request = isset($_POST['can_request']) ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE employees SET can_request_leave = ? WHERE id = ? AND organization_id = ?");
    if ($stmt->execute([$can_request, $emp_id, CURRENT_ORG_ID])) {
        // جلب اسم الموظف للسجل للمؤسسة الحالية فقط
        $stmt_name = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND organization_id = ?");
        $stmt_name->execute([$emp_id, CURRENT_ORG_ID]);
        $full_name = $stmt_name->fetchColumn();
        
        if ($full_name) {
            $status_text = $can_request ? "تفعيل" : "إيقاف";
            $status_en = $can_request ? "Enabled" : "Disabled";
            
            logActivity("$status_text صلاحية الإجازة للموظف", "$status_en Leave Permission for Employee", "Employee: $full_name (ID: $emp_id)");
            $success = __('success_updated');
        }
    }
    } // end CSRF else
}


// Filters
$where = ["e.organization_id = ?"];
$params = [CURRENT_ORG_ID];

if (!empty($_GET['search'])) {
    $where[] = "(e.full_name LIKE ? OR e.system_id LIKE ? OR e.employee_id_number LIKE ?)";
    $search_val = '%' . $_GET['search'] . '%';
    $params[] = $search_val;
    $params[] = $search_val;
    $params[] = $search_val;
}
if (!empty($_GET['department_id'])) {
    $where[] = "e.department_id = ?";
    $params[] = (int)$_GET['department_id'];
}
if (!empty($_GET['status'])) {
    $allowed_statuses = ['pending', 'approved', 'rejected'];
    $status = $_GET['status'];
    if (in_array($status, $allowed_statuses)) {
        $where[] = "e.status = ?";
        $params[] = $status;
    }
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Pagination
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$countQuery = "SELECT COUNT(*) FROM employees e $whereClause";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$total_records = (int)$stmtCount->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));

$query = "SELECT e.*, d.name_ar as dept_ar, d.name_en as dept_en, u.email as user_email 
          FROM employees e 
          LEFT JOIN departments d ON e.department_id = d.id 
          LEFT JOIN users u ON e.user_id = u.id 
          $whereClause
          ORDER BY e.created_at DESC
          LIMIT $per_page OFFSET $offset";
          
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Fetch departments for filter
$stmtDept = $pdo->prepare("SELECT * FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
$stmtDept->execute([CURRENT_ORG_ID]);
$departments = $stmtDept->fetchAll();

// Excel (CSV) Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Employees_List_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for proper Arabic rendering in Excel/Numbers
    fputs($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, [__('export_system_id'), __('export_employee_id'), __('export_full_name'), __('export_department'), __('export_job_title'), __('export_hire_date'), __('export_account_status')]);
    
    // Data
    foreach ($employees as $emp) {
        $status_text = $emp['status'] == 'approved' ? __('status_active') : ($emp['status'] == 'rejected' ? __('status_rejected') : __('status_pending'));
        fputcsv($output, [
            $emp['system_id'],
            $emp['employee_id_number'],
            $emp['full_name'],
            get_name(['name_ar' => $emp['dept_ar'], 'name_en' => $emp['dept_en']]),
            $emp['job_title'],
            $emp['hire_date'],
            $status_text
        ]);
    }
    fclose($output);
    exit;
}

// Smart CSV Import — Two-Phase: Upload & Preview → Confirm
$import_success = '';
$import_error = '';
$import_preview = [];

// Phase 1: Upload CSV and build preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_csv'])) {
    if (!verify_csrf()) {
        $import_error = __('csrf_token_invalid');
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_err = $_FILES['csv_file']['error'] ?? -1;
        $import_error = __('import_invalid_file') . ' (' . __('import_error_upload') . ' code: ' . $upload_err . ')';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if ($handle) {
            $header = fgetcsv($handle);
            // Strip BOM from first header column
            if ($header && !empty($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header && in_array('system_id', $header) && in_array('full_name', $header)) {
                $idx_system_id = array_search('system_id', $header);
                $idx_full_name = array_search('full_name', $header);
                $idx_email = array_search('email', $header);
                $idx_job_title = array_search('job_title', $header);
                $idx_employee_id = array_search('employee_id_number', $header);
                $idx_department = array_search('department_name_ar', $header);
                $idx_phone = array_search('phone', $header);

                // Pre-fetch all departments for matching
                $stmtAllDepts = $pdo->query("SELECT id, name_ar, name_en FROM departments WHERE organization_id = " . CURRENT_ORG_ID);
                $all_depts = $stmtAllDepts->fetchAll(PDO::FETCH_ASSOC);

                // Pre-fetch existing employees to check for duplicates
                $stmtExisting = $pdo->prepare("SELECT system_id, full_name FROM employees WHERE organization_id = ?");
                $stmtExisting->execute([CURRENT_ORG_ID]);
                $existing_map = [];
                foreach ($stmtExisting->fetchAll() as $e) {
                    $existing_map[$e['system_id']] = $e['full_name'];
                }

                $line = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $line++;
                    $system_id = trim($row[$idx_system_id] ?? '');
                    $full_name = trim($row[$idx_full_name] ?? '');
                    if (empty($system_id) && empty($full_name)) continue;

                    $issues = [];
                    $status = 'ok';

                    if (empty($system_id)) { $issues[] = 'missing system_id'; $status = 'error'; }
                    if (empty($full_name)) { $issues[] = 'missing full_name'; $status = 'error'; }

                    // Check for existing employee
                    $exists = isset($existing_map[$system_id]);
                    if ($exists) {
                        $issues[] = 'already exists: ' . $existing_map[$system_id];
                        $status = 'duplicate';
                    }

                    // Match department
                    $dept_id = null;
                    $dept_name = '';
                    if ($idx_department !== false && !empty(trim($row[$idx_department] ?? ''))) {
                        $dept_name = trim($row[$idx_department]);
                        foreach ($all_depts as $d) {
                            if ($d['name_ar'] === $dept_name || $d['name_en'] === $dept_name) {
                                $dept_id = $d['id'];
                                break;
                            }
                        }
                        if (!$dept_id) {
                            $issues[] = "department '{$dept_name}' not found";
                            $status = 'error';
                        }
                    }

                    $email = $idx_email !== false ? trim($row[$idx_email] ?? '') : '';
                    $job_title = $idx_job_title !== false ? trim($row[$idx_job_title] ?? '') : '';
                    $employee_id_number = $idx_employee_id !== false ? trim($row[$idx_employee_id] ?? '') : $system_id;
                    $phone = $idx_phone !== false ? trim($row[$idx_phone] ?? '') : '';

                    $import_preview[] = [
                        'system_id' => $system_id,
                        'full_name' => $full_name,
                        'email' => $email,
                        'employee_id_number' => $employee_id_number,
                        'job_title' => $job_title,
                        'phone' => $phone,
                        'dept_id' => $dept_id,
                        'dept_name' => $dept_name,
                        'issues' => $issues,
                        'status' => $status,
                        'selected' => ($status === 'ok'),
                    ];
                }
                fclose($handle);

                if (empty($import_preview)) {
                    $import_error = __('import_invalid_file') . ' (' . __('import_error_empty') . ')';
                    error_log('CSV Import: parsed 0 rows from file. Header: ' . json_encode($header));
                } else {
                    $_SESSION['import_preview'] = $import_preview;
                }
            } else {
                $import_error = __('import_invalid_file') . ' (' . __('import_error_header') . ')';
            }
        } else {
            $import_error = __('import_invalid_file') . ' (' . __('import_error_read') . ')';
        }
    }
}

// Phase 2: Confirm import — reads user-edited POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    if (!verify_csrf()) {
        $import_error = __('csrf_token_invalid');
    } elseif (empty($_SESSION['import_preview'])) {
        $import_error = __('import_invalid_file');
    } else {
        $selected = $_POST['sel'] ?? [];
        $edit_system_id = $_POST['edit_system_id'] ?? [];
        $edit_full_name = $_POST['edit_full_name'] ?? [];
        $edit_email = $_POST['edit_email'] ?? [];
        $edit_job_title = $_POST['edit_job_title'] ?? [];
        $edit_phone = $_POST['edit_phone'] ?? [];
        $edit_dept_id = $_POST['edit_dept_id'] ?? [];
        $edit_emp_id = $_POST['edit_emp_id'] ?? [];

        $imported = 0;
        $errors = [];

        foreach ($selected as $i) {
            $i = (int)$i;
            $system_id = trim($edit_system_id[$i] ?? '');
            $full_name = trim($edit_full_name[$i] ?? '');
            if (empty($system_id) || empty($full_name)) {
                $errors[] = "Row $i: missing system_id or full_name";
                continue;
            }

            // Check for existing employee (including this session's imports)
            $stmtChk = $pdo->prepare("SELECT id FROM employees WHERE system_id = ? AND organization_id = ?");
            $stmtChk->execute([$system_id, CURRENT_ORG_ID]);
            if ($stmtChk->fetch()) {
                $errors[] = "$system_id: " . __('import_duplicate');
                continue;
            }

            $email = trim($edit_email[$i] ?? '');
            $job_title = trim($edit_job_title[$i] ?? '');
            $phone = trim($edit_phone[$i] ?? '');
            $employee_id_number = trim($edit_emp_id[$i] ?? '') ?: $system_id;
            $dept_id = !empty($edit_dept_id[$i]) ? (int)$edit_dept_id[$i] : null;

            // Check for existing username
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $system_id));
            $stmtUsr = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtUsr->execute([$username]);
            if ($stmtUsr->fetch()) {
                $errors[] = "$system_id: username '$username' already exists";
                continue;
            }

            // Check for existing email (if provided)
            if (!empty($email)) {
                $stmtEm = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmtEm->execute([$email]);
                if ($stmtEm->fetch()) {
                    $errors[] = "$system_id: email '$email' already in use";
                    continue;
                }
            }

            try {
                $pdo->beginTransaction();
                $hash = password_hash(bin2hex(random_bytes(4)), PASSWORD_BCRYPT);
                $stmtU = $pdo->prepare("INSERT INTO users (username, email, password, role, organization_id) VALUES (?, ?, ?, 'employee', ?)");
                $stmtU->execute([$username, $email ?: "{$username}@imported.local", $hash, CURRENT_ORG_ID]);
                $user_id = $pdo->lastInsertId();

                $stmtE = $pdo->prepare("INSERT INTO employees (organization_id, user_id, full_name, system_id, employee_id_number, phone, job_title, department_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
                $stmtE->execute([CURRENT_ORG_ID, $user_id, $full_name, $system_id, $employee_id_number, $phone, $job_title, $dept_id]);
                $pdo->commit();
                $imported++;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_code = $e->getCode();
                error_log('Import error for ' . $system_id . ': ' . $e->getMessage());
                $errors[] = "$system_id (err: $error_code)";
            }
        }

        unset($_SESSION['import_preview']);

        if ($imported > 0) {
            $import_success = __('import_success') . " ($imported " . __('employees') . ")";
        }
        if (!empty($errors)) {
            $import_error = __('import_error') . ': ' . implode(', ', $errors);
        }
        if ($imported === 0 && empty($errors)) {
            $import_error = __('import_invalid_file');
        }
    }
}

// Clear preview
if (isset($_POST['cancel_import'])) {
    unset($_SESSION['import_preview']);
}

$import_preview = $_SESSION['import_preview'] ?? [];
$all_depts = [];
if (!empty($import_preview)) {
    $stmtD = $pdo->prepare("SELECT id, name_ar, name_en FROM departments WHERE organization_id = ? ORDER BY name_ar ASC");
    $stmtD->execute([CURRENT_ORG_ID]);
    $all_depts = $stmtD->fetchAll();
}

$pageTitle = __('employees');
include '../includes/header.php';
?>


<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
    <h1 class="h3 mb-3 mb-md-0"><?php echo __('employees'); ?></h1>
    <div>
        <button type="button" class="btn btn-outline-secondary shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#importModal">
            <?php echo __('import_csv'); ?>
        </button>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success shadow-sm me-2">
            <i class="bi bi-bar-chart-line"></i> <?php echo __('export_excel'); ?>
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
                        <option value="<?php echo $dept['id']; ?>" <?php echo (($_GET['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>><?php echo h(get_name($dept)); ?></option>
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
                <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="bi bi-search"></i> <?php echo __('filter_btn'); ?></button>
            </div>
        </form>
    </div>
</div>


<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 mb-4"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($import_success): ?>
    <div class="alert alert-success shadow-sm border-0 mb-4"><?php echo $import_success; ?></div>
<?php endif; ?>
<?php if ($import_error): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4"><?php echo $import_error; ?></div>
<?php endif; ?>


<!-- Import CSV Modal (Upload Phase) -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="list.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('import_employees_title'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">CSV <?php echo __('file'); ?></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="small text-muted">
                        <strong><?php echo __('required_columns'); ?>:</strong> system_id, full_name<br>
                        <strong><?php echo __('optional_columns'); ?>:</strong> email, employee_id_number, job_title, phone, department_name_ar<br>
                        <a href="#" onclick="downloadTemplate(); return false;"><?php echo __('download_template'); ?></a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="preview_csv" class="btn btn-primary"><?php echo __('preview'); ?> &amp; <?php echo __('import_csv'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function downloadTemplate() {
    const bom = "\uFEFF";
    const csv = 'system_id,full_name,email,employee_id_number,job_title,phone,department_name_ar\nEMP001,John Doe,john@example.com,ID001,Developer,+966500000000,Engineering\n';
    const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'import_employees_template.csv';
    link.click();
}
</script>


<?php if (!empty($import_preview)): ?>
<?php
$ok_count = count(array_filter($import_preview, fn($r) => $r['status'] === 'ok'));
$err_count = count(array_filter($import_preview, fn($r) => $r['status'] === 'error'));
$dup_count = count(array_filter($import_preview, fn($r) => $r['status'] === 'duplicate'));
?>
<!-- Import Preview — Editable -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold"><?php echo __('import_preview_title'); ?> (<?php echo count($import_preview); ?> <?php echo __('employees'); ?>)</span>
        <div class="d-flex align-items-center gap-3 small">
            <span class="text-success"><?php echo $ok_count; ?> <?php echo __('import_ready'); ?></span>
            <span class="text-danger"><?php echo $err_count; ?> <?php echo __('import_error_short'); ?></span>
            <span class="text-warning"><?php echo $dup_count; ?> <?php echo __('import_duplicate'); ?></span>
            <form action="list.php" method="POST" class="d-inline">
                <?php echo csrf_field(); ?>
                <button type="submit" name="cancel_import" class="btn btn-sm btn-outline-secondary"><?php echo __('cancel'); ?></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <form action="list.php" method="POST" id="confirmImportForm">
            <?php echo csrf_field(); ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width:800px">
                    <thead class="table-light small">
                        <tr>
                            <th style="width:36px">
                                <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)">
                            </th>
                            <th style="min-width:110px"><?php echo __('system_id'); ?> *</th>
                            <th style="min-width:140px"><?php echo __('full_name'); ?> *</th>
                            <th style="min-width:150px"><?php echo __('email'); ?></th>
                            <th style="min-width:140px"><?php echo __('employee_id_number'); ?></th>
                            <th style="min-width:120px"><?php echo __('department'); ?></th>
                            <th style="min-width:120px"><?php echo __('job_title'); ?></th>
                            <th style="min-width:110px"><?php echo __('phone'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($import_preview as $i => $row): ?>
                            <?php
                            $is_dup = $row['status'] === 'duplicate';
                            $tr_class = $is_dup ? 'table-warning' : ($row['status'] === 'error' ? 'table-danger' : '');
                            ?>
                            <tr class="<?php echo $tr_class; ?>">
                                <td>
                                    <input type="checkbox" name="sel[]" value="<?php echo $i; ?>" <?php echo $row['selected'] ? 'checked' : ''; ?> <?php echo $is_dup ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" name="edit_system_id[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo h($row['system_id']); ?>" placeholder="*required" <?php echo $is_dup ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" name="edit_full_name[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo h($row['full_name']); ?>" placeholder="*required" <?php echo $is_dup ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="email" name="edit_email[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo h($row['email']); ?>" placeholder="optional" <?php echo $is_dup ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" name="edit_emp_id[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo h($row['employee_id_number']); ?>" placeholder="defaults to system_id" <?php echo $is_dup ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <select name="edit_dept_id[<?php echo $i; ?>]" class="form-select form-select-sm" <?php echo $is_dup ? 'disabled' : ''; ?>>
                                        <option value="">—</option>
                                        <?php foreach ($all_depts as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo $d['id'] == $row['dept_id'] ? 'selected' : ''; ?>><?php echo h(get_name($d)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="edit_job_title[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo h($row['job_title']); ?>" placeholder="optional" <?php echo $is_dup ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" name="edit_phone[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo h($row['phone']); ?>" placeholder="optional" <?php echo $is_dup ? 'disabled' : ''; ?>>
                                </td>
                            </tr>
                            <?php if (!empty($row['issues'])): ?>
                            <tr class="<?php echo $tr_class; ?>">
                                <td colspan="8" class="small text-muted py-1 px-3" style="font-size:0.75rem">
                                    ⚠️ <?php echo h(implode(' | ', $row['issues'])); ?>
                                    <?php if ($is_dup): ?>
                                        — <span class="text-warning"><?php echo __('import_duplicate_skip'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted"><?php echo __('import_select_hint'); ?></span>
                <button type="submit" name="confirm_import" class="btn btn-primary">
                    <?php echo __('import_selected'); ?> (<span id="selectedCount"><?php echo count(array_filter($import_preview, fn($r) => $r['selected'])); ?></span>)
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAll(source) {
    document.querySelectorAll('#confirmImportForm input[name="sel[]"]:not(:disabled)').forEach(cb => {
        cb.checked = source.checked;
    });
    updateCount();
}
document.querySelectorAll('#confirmImportForm input[name="sel[]"]').forEach(cb => {
    cb.addEventListener('change', updateCount);
});
function updateCount() {
    const count = document.querySelectorAll('#confirmImportForm input[name="sel[]"]:checked').length;
    document.getElementById('selectedCount').textContent = count;
}
</script>
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
                                            <?php echo csrf_field(); ?>
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
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-white border" title="<?php echo __('edit'); ?>">
                                            <i class="bi bi-pencil"></i>
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

<?php if ($total_pages > 1): ?>
<nav aria-label="Employees pagination" class="mt-4">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page - 1])); ?>"><?php echo __('prev'); ?></a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $i])); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page + 1])); ?>"><?php echo __('next'); ?></a>
        </li>
    </ul>
</nav>
<?php endif; ?>

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
