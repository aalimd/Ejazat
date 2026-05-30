<?php
require_once '../includes/config.php';

// Require Super Admin authority
checkAuth('super_admin');

$error = '';
$success = '';

// Handle creating a new organization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    
    $manager_username = trim($_POST['manager_username'] ?? '');
    $manager_email = trim($_POST['manager_email'] ?? '');
    $manager_password = trim($_POST['manager_password'] ?? '');

    if (empty($name_ar) || empty($name_en) || empty($slug) || empty($manager_username) || empty($manager_email) || empty($manager_password)) {
        $error = __('fill_fields_error');
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $error = __('slug_format_error');
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insert Organization
            $stmt = $pdo->prepare("INSERT INTO organizations (name_ar, name_en, slug, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$name_ar, $name_en, $slug]);
            $org_id = $pdo->lastInsertId();

            // 2. Insert supervising manager
            $hashed_pass = password_hash($manager_password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, email, role, organization_id) VALUES (?, ?, ?, 'admin', ?)");
            $stmtUser->execute([$manager_username, $hashed_pass, $manager_email, $org_id]);
            $user_id = $pdo->lastInsertId();

            // 3. Create default settings for this organization
            $default_settings = [
                'site_name_ar' => '🏢 ' . $name_ar,
                'site_name_en' => '🏢 ' . $name_en,
                'primary_color' => '#0d6efd',
                'font_family_ar' => 'Cairo',
                'font_family_en' => 'Inter',
                'allow_registration' => '1',
                'allow_leave_requests' => '1',
                'prevent_overlapping_leaves' => '1',
                'allow_past_leaves' => '1',
                'footer_text_ar' => 'جميع الحقوق محفوظة - ' . $name_ar,
                'footer_text_en' => 'All Rights Reserved - ' . $name_en,
            ];
            
            $stmtSetting = $pdo->prepare("INSERT INTO settings (organization_id, setting_key, setting_value) VALUES (?, ?, ?)");
            foreach ($default_settings as $key => $val) {
                $stmtSetting->execute([$org_id, $key, $val]);
            }

            // 4. Create default departments for this organization
            $default_depts = [
                ['الموارد البشرية', 'Human Resources'],
                ['المحاسبة والمالية', 'Accounting & Finance'],
                ['تقنية المعلومات', 'Information Technology'],
            ];
            $stmtDept = $pdo->prepare("INSERT INTO departments (organization_id, name_ar, name_en) VALUES (?, ?, ?)");
            foreach ($default_depts as $dept) {
                $stmtDept->execute([$org_id, $dept[0], $dept[1]]);
            }

            // 5. Create default leave types for this organization
            $default_leaves = [
                ['إجازة سنوية', 'Annual Leave', 1, 30],
                ['إجازة مرضية', 'Sick Leave', 1, 15],
                ['إجازة اضطرارية', 'Emergency Leave', 1, 5],
            ];
            $stmtLeave = $pdo->prepare("INSERT INTO leave_types (organization_id, name_ar, name_en, deduct_from_balance, max_days_per_year) VALUES (?, ?, ?, ?, ?)");
            foreach ($default_leaves as $lv) {
                $stmtLeave->execute([$org_id, $lv[0], $lv[1], $lv[2], $lv[3]]);
            }

            $pdo->commit();
            logActivity("🏢 إضافة جهة عمل", "🏢 Added Organization", "Created organization: $name_en", $org_id);
            $success = __('success_org_created');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = __('db_error') . ': ' . $e->getMessage();
        }
    }
    }
}

// Handle approving a request
if (isset($_GET['approve_req_id']) && isset($_GET['csrf_token'])) {
    if (!verify_csrf($_GET['csrf_token'])) {
        $error = __('access_denied');
    } else {
    $req_id = intval($_GET['approve_req_id']);
    try {
        // Fetch request details
        $stmtReq = $pdo->prepare("SELECT * FROM organization_requests WHERE id = ? AND status = 'pending'");
        $stmtReq->execute([$req_id]);
        $req = $stmtReq->fetch();
        
        if ($req) {
            $pdo->beginTransaction();
            
            // 1. Insert Organization
            $stmt = $pdo->prepare("INSERT INTO organizations (name_ar, name_en, slug, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$req['name_ar'], $req['name_en'], $req['slug']]);
            $org_id = $pdo->lastInsertId();
            
            // 2. Insert supervising manager
            // Generate a random temporary password
            $temp_password = "Pass@" . rand(1000, 9999);
            $hashed_pass = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // The username can be derived from the manager_email or slug
            $username = strstr($req['manager_email'], '@', true);
            // Verify username uniqueness
            $stmtCheckUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmtCheckUser->execute([$username]);
            if ($stmtCheckUser->fetchColumn() > 0) {
                $username = $username . rand(10, 99);
            }
            
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, email, role, organization_id) VALUES (?, ?, ?, 'admin', ?)");
            $stmtUser->execute([$username, $hashed_pass, $req['manager_email'], $org_id]);
            
            // 3. Create default settings for this organization
            $default_settings = [
                'site_name_ar' => '🏢 ' . $req['name_ar'],
                'site_name_en' => '🏢 ' . $req['name_en'],
                'primary_color' => '#0d6efd',
                'font_family_ar' => 'Cairo',
                'font_family_en' => 'Inter',
                'allow_registration' => '1',
                'allow_leave_requests' => '1',
                'prevent_overlapping_leaves' => '1',
                'allow_past_leaves' => '1',
                'footer_text_ar' => 'جميع الحقوق محفوظة - ' . $req['name_ar'],
                'footer_text_en' => 'All Rights Reserved - ' . $req['name_en'],
            ];
            
            $stmtSetting = $pdo->prepare("INSERT INTO settings (organization_id, setting_key, setting_value) VALUES (?, ?, ?)");
            foreach ($default_settings as $key => $val) {
                $stmtSetting->execute([$org_id, $key, $val]);
            }
            
            // 4. Create default departments for this organization
            $default_depts = [
                ['الموارد البشرية', 'Human Resources'],
                ['المحاسبة والمالية', 'Accounting & Finance'],
                ['تقنية المعلومات', 'Information Technology'],
            ];
            $stmtDept = $pdo->prepare("INSERT INTO departments (organization_id, name_ar, name_en) VALUES (?, ?, ?)");
            foreach ($default_depts as $dept) {
                $stmtDept->execute([$org_id, $dept[0], $dept[1]]);
            }
            
            // 5. Create default leave types for this organization
            $default_leaves = [
                ['إجازة سنوية', 'Annual Leave', 1, 30],
                ['إجازة مرضية', 'Sick Leave', 1, 15],
                ['إجازة اضطرارية', 'Emergency Leave', 1, 5],
            ];
            $stmtLeave = $pdo->prepare("INSERT INTO leave_types (organization_id, name_ar, name_en, deduct_from_balance, max_days_per_year) VALUES (?, ?, ?, ?, ?)");
            foreach ($default_leaves as $lv) {
                $stmtLeave->execute([$org_id, $lv[0], $lv[1], $lv[2], $lv[3]]);
            }
            
            // Update request status
            $stmtUpdateReq = $pdo->prepare("UPDATE organization_requests SET status = 'approved' WHERE id = ?");
            $stmtUpdateReq->execute([$req_id]);
            
            $pdo->commit();
            
            logActivity("🏢 قبول طلب جهة عمل", "🏢 Approved Organization Request", "Approved request for: " . $req['name_en'], $org_id);
            
             $success = __('org_approved_success') . "\n\n" . 
                        __('manager_account_info') . ":\n" . 
                        __('username_label') . ": " . $username . "\n" . 
                        __('temp_password_label') . ": " . $temp_password . "\n\n" . 
                        __('save_credentials');
        } else {
            $error = __('request_not_found');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = __('db_error') . ': ' . $e->getMessage();
    }
    }
}

// Handle rejecting a request
if (isset($_GET['reject_req_id']) && isset($_GET['csrf_token'])) {
    if (!verify_csrf($_GET['csrf_token'])) {
        $error = __('access_denied');
    } else {
    $req_id = intval($_GET['reject_req_id']);
    try {
        $stmt = $pdo->prepare("UPDATE organization_requests SET status = 'rejected' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$req_id]);
        logActivity("🏢 رفض طلب جهة عمل", "🏢 Rejected Organization Request", "Rejected request ID $req_id");
        $success = __('rejected_success');
    } catch (Exception $e) {
        $error = __('db_error') . ': ' . $e->getMessage();
    }
    }
}

// Handle toggling email_enabled
if (isset($_GET['toggle_email']) && isset($_GET['org_id']) && isset($_GET['csrf_token'])) {
    if (!verify_csrf($_GET['csrf_token'])) {
        $error = __('access_denied');
    } else {
    $org_id = intval($_GET['org_id']);
    $new_val = $_GET['toggle_email'] === '1' ? '1' : '0';
    try {
        $stmt = $pdo->prepare("UPDATE organizations SET email_enabled = ? WHERE id = ?");
        $stmt->execute([$new_val, $org_id]);
        logActivity("📧 تغيير حالة البريد", "📧 Toggle Org Email", "Organization ID $org_id email_enabled=$new_val");
        $success = __('success_updated');
    } catch (Exception $e) {
        $error = __('db_error') . ': ' . $e->getMessage();
    }
    }
}

// Handle updating organization status
if (isset($_GET['toggle_status']) && isset($_GET['org_id']) && isset($_GET['csrf_token'])) {
    if (!verify_csrf($_GET['csrf_token'])) {
        $error = __('access_denied');
    } else {
    $org_id = intval($_GET['org_id']);
    $new_status = $_GET['toggle_status'] === 'suspended' ? 'suspended' : 'active';
    
    if ($org_id > 1) { // Prevent toggling the default system organization
        try {
            $stmt = $pdo->prepare("UPDATE organizations SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $org_id]);
            logActivity("🔄 تعديل حالة جهة عمل", "🔄 Toggled Organization Status", "Organization ID $org_id status updated to $new_status");
            $success = __('success_updated');
        } catch (Exception $e) {
            $error = __('db_error') . ': ' . $e->getMessage();
        }
    } else {
        $error = __('cannot_suspend_default');
    }
    }
}

$lang = $_SESSION['lang'] ?? 'ar';

// Fetch all organizations with their department, employee, and user counts
$orgs = [];
try {
    $query = "
        SELECT o.*, 
            (SELECT COUNT(*) FROM departments WHERE organization_id = o.id) AS dept_count,
            (SELECT COUNT(*) FROM employees WHERE organization_id = o.id) AS emp_count,
            (SELECT COUNT(*) FROM users WHERE organization_id = o.id) AS user_count
        FROM organizations o
        ORDER BY o.id ASC
    ";
    $orgs = $pdo->query($query)->fetchAll();
} catch (Exception $e) {
    $error = __('db_error') . ': ' . $e->getMessage();
}

// Fetch pending organization requests
$pending_requests = [];
try {
    $pending_requests = $pdo->query("SELECT * FROM organization_requests WHERE status = 'pending' ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {
    //
}

$pageTitle = __('organizations_mgmt');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800 fw-bold">🏢 <?php echo __('organizations_mgmt'); ?></h2>
    <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addOrgModal">
        ➕ <?php echo __('add_organization'); ?>
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 mb-4" style="white-space: pre-wrap;"><?php echo nl2br(h($success)); ?></div>
<?php endif; ?>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4 border-bottom-0" id="orgTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold text-gray-800" id="orgs-tab" data-bs-toggle="tab" data-bs-target="#orgs-content" type="button" role="tab" aria-controls="orgs-content" aria-selected="true" style="border-radius: 8px 8px 0 0;">
            <?php echo __('organizations_mgmt'); ?> (<?php echo count($orgs); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold text-gray-800 position-relative" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests-content" type="button" role="tab" aria-controls="requests-content" aria-selected="false" style="border-radius: 8px 8px 0 0;">
            <?php echo __('pending_requests'); ?>
            <?php if (count($pending_requests) > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.75rem; padding: 0.25em 0.6em;">
                    <?php echo count($pending_requests); ?>
                </span>
            <?php endif; ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="orgTabsContent">
    <!-- Tab 1: Current Organizations -->
    <div class="tab-pane fade show active" id="orgs-content" role="tabpanel" aria-labelledby="orgs-tab">
        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm bg-gradient-primary text-white">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase small mb-2 opacity-75 fw-bold"><?php echo __('org_count'); ?></h6>
                                <h2 class="display-5 fw-bold mb-0"><?php echo count($orgs); ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">🏢</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Organizations Grid -->
        <div class="row">
            <?php if (empty($orgs)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm text-center p-5">
                        <p class="text-muted fs-5 mb-0"><?php echo __('no_data'); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($orgs as $org): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100 transition-hover">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="badge py-2 px-3 fw-bold <?php echo $org['status'] === 'active' ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'; ?>">
                                            <?php echo $org['status'] === 'active' ? __('active') : __('suspended'); ?>
                                        </span>
                                        <span class="text-muted small">ID: #<?php echo $org['id']; ?></span>
                                    </div>
                                    
                                    <h4 class="fw-bold mb-1"><?php echo h(get_name($org)); ?></h4>
                                    <p class="text-muted small mb-3">Slug: <code class="bg-light px-2 py-1 rounded small"><?php echo h($org['slug']); ?></code></p>
                                    
                                    <!-- Counts info -->
                                    <div class="row text-center bg-light rounded py-3 mb-4">
                                        <div class="col-4 border-end">
                                            <div class="fw-bold fs-5 text-primary"><?php echo $org['dept_count']; ?></div>
                                            <div class="x-small text-muted"><?php echo __('departments'); ?></div>
                                        </div>
                                        <div class="col-4 border-end">
                                            <div class="fw-bold fs-5 text-success"><?php echo $org['emp_count']; ?></div>
                                            <div class="x-small text-muted"><?php echo __('employees'); ?></div>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold fs-5 text-info"><?php echo $org['user_count']; ?></div>
                                            <div class="x-small text-muted"><?php echo __('system_users'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <a href="../index.php?switch_org=<?php echo $org['id']; ?>" class="btn btn-primary btn-sm fw-bold py-2 flex-fill">
                                        <?php echo __('enter_org'); ?>
                                    </a>
                                    <?php $email_enabled = $org['email_enabled'] ?? 1; ?>
                                    <a href="organizations.php?toggle_email=<?php echo $email_enabled ? '0' : '1'; ?>&org_id=<?php echo $org['id']; ?>&csrf_token=<?php echo csrf_token(); ?>"
                                       class="btn btn-sm fw-bold py-2 <?php echo $email_enabled ? 'btn-outline-success' : 'btn-outline-secondary'; ?>"
                                       onclick="return confirm('<?php echo $email_enabled ? __('confirm_disable_email') : __('confirm_enable_email'); ?>')">
                                        📧 <?php echo $email_enabled ? __('email_on') : __('email_off'); ?>
                                    </a>
                                    <?php if ($org['id'] > 1): ?>
                                        <?php if ($org['status'] === 'active'): ?>
                                            <a href="organizations.php?toggle_status=suspended&org_id=<?php echo $org['id']; ?>&csrf_token=<?php echo csrf_token(); ?>" 
                                               class="btn btn-outline-danger btn-sm w-100 fw-bold py-2"
                                               onclick="return confirm('<?php echo __('confirm_delete'); ?>')">
                                                🚫 <?php echo __('suspended'); ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="organizations.php?toggle_status=active&org_id=<?php echo $org['id']; ?>&csrf_token=<?php echo csrf_token(); ?>" 
                                               class="btn btn-outline-success btn-sm w-100 fw-bold py-2">
                                                🟢 <?php echo __('active'); ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm w-100 fw-bold py-2 disabled" disabled>
                                            <?php echo __('system_core'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 2: Pending Requests -->
    <div class="tab-pane fade" id="requests-content" role="tabpanel" aria-labelledby="requests-tab">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4 text-gray-800"><?php echo __('pending_requests'); ?></h5>
                <?php if (empty($pending_requests)): ?>
                    <div class="text-center py-5">
                        <div class="fs-1 text-muted mb-3">📥</div>
                        <p class="text-muted mb-0 fs-5"><?php echo __('no_pending_requests'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><?php echo __('org_name'); ?></th>
                                    <th><?php echo __('org_slug'); ?></th>
                                    <th><?php echo __('contact_info'); ?></th>
                                    <th><?php echo __('notes'); ?></th>
                                    <th><?php echo __('request_date'); ?></th>
                                    <th class="text-end"><?php echo __('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $req): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-800"><?php echo h($req['name_ar']); ?></div>
                                            <div class="small text-muted"><?php echo h($req['name_en']); ?></div>
                                        </td>
                                        <td>
                                            <code class="bg-light px-2 py-1 rounded small"><?php echo h($req['slug']); ?></code>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo h($req['manager_name']); ?></div>
                                            <div class="small text-muted">✉️ <?php echo h($req['manager_email']); ?></div>
                                            <div class="small text-muted">📞 <?php echo h($req['manager_phone']); ?></div>
                                        </td>
                                        <td>
                                            <div class="small text-wrap" style="max-width: 250px;"><?php echo h($req['notes'] ?: __('no_notes')); ?></div>
                                        </td>
                                        <td>
                                            <span class="small text-muted"><?php echo h($req['created_at']); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="organizations.php?approve_req_id=<?php echo $req['id']; ?>&csrf_token=<?php echo csrf_token(); ?>" 
                                                   class="btn btn-success btn-sm fw-bold px-3"
                                                   onclick="return confirm('<?php echo __('confirm_approve_org'); ?>')">
                                                    <?php echo __('approve_activate'); ?>
                                                </a>
                                                <a href="organizations.php?reject_req_id=<?php echo $req['id']; ?>&csrf_token=<?php echo csrf_token(); ?>" 
                                                   class="btn btn-outline-danger btn-sm fw-bold px-3"
                                                   onclick="return confirm('<?php echo __('confirm_reject_request'); ?>')">
                                                    <?php echo __('reject_request'); ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>

<!-- Add Organization Modal -->
<div class="modal fade" id="addOrgModal" tabindex="-1" aria-labelledby="addOrgModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="addOrgModalLabel">🏢 <?php echo __('add_organization'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="organizations.php" method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">📋 <?php echo __('basic_info'); ?></h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('org_name_ar'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name_ar" class="form-control" placeholder="<?php echo __('org_name_ar_example');?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('org_name_en'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name_en" class="form-control" placeholder="e.g. Ministry of Education" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold"><?php echo __('org_slug'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="slug" class="form-control" placeholder="e.g. ministry-edu" required pattern="^[a-z0-9\-]+$">
                            <div class="form-text small opacity-75">Used for tenant URL identification. Letters, numbers, and dashes only.</div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-success">👤 <?php echo __('account_info'); ?> (Supervising Manager)</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('org_manager_username'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="manager_username" class="form-control" required autocomplete="off">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('org_manager_email'); ?> <span class="text-danger">*</span></label>
                            <input type="email" name="manager_email" class="form-control" required autocomplete="off">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('org_manager_password'); ?> <span class="text-danger">*</span></label>
                            <input type="password" name="manager_password" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary fw-bold px-4"><?php echo __('submit'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
