<?php
require_once '../includes/config.php';

// Require Super Admin authority
checkAuth('super_admin');

$error = '';
$success = '';

// Handle creating a new organization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    
    $manager_username = trim($_POST['manager_username'] ?? '');
    $manager_email = trim($_POST['manager_email'] ?? '');
    $manager_password = trim($_POST['manager_password'] ?? '');

    if (empty($name_ar) || empty($name_en) || empty($slug) || empty($manager_username) || empty($manager_email) || empty($manager_password)) {
        $error = __('fill_fields_error');
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $error = __('Slug must contain lowercase letters, numbers, and dashes only (no spaces).');
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
            logActivity("🏢 إضافة جهة عمل", "🏢 Added Organization", "Created organization: $name_en");
            $success = __('success_org_created');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = __('db_error') . ': ' . $e->getMessage();
        }
    }
}

// Handle updating organization status
if (isset($_GET['toggle_status']) && isset($_GET['org_id'])) {
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
        $error = "🚫 Cannot suspend the default system organization.";
    }
}

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

$pageTitle = __('organizations_mgmt');
include '../includes/header.php';
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
    <div class="alert alert-success shadow-sm border-0 mb-4"><?php echo h($success); ?></div>
<?php endif; ?>

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
                            
                            <h4 class="fw-bold mb-1"><?php echo $lang === 'en' ? h($org['name_en']) : h($org['name_ar']); ?></h4>
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

                        <div class="d-flex gap-2">
                            <a href="../index.php?switch_org=<?php echo $org['id']; ?>" class="btn btn-primary btn-sm w-100 fw-bold py-2">
                                ⚙️ دخول للجهة
                            </a>
                            <?php if ($org['id'] > 1): ?>
                                <?php if ($org['status'] === 'active'): ?>
                                    <a href="organizations.php?toggle_status=suspended&org_id=<?php echo $org['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm w-100 fw-bold py-2"
                                       onclick="return confirm('<?php echo __('confirm_delete'); ?>')">
                                        🚫 <?php echo __('suspended'); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="organizations.php?toggle_status=active&org_id=<?php echo $org['id']; ?>" 
                                       class="btn btn-outline-success btn-sm w-100 fw-bold py-2">
                                        🟢 <?php echo __('active'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm w-100 fw-bold py-2 disabled" disabled>
                                    🔒 System Core
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Organization Modal -->
<div class="modal fade" id="addOrgModal" tabindex="-1" aria-labelledby="addOrgModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="addOrgModalLabel">🏢 <?php echo __('add_organization'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="organizations.php" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body p-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">📋 <?php echo __('basic_info'); ?></h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold"><?php echo __('org_name_ar'); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="name_ar" class="form-control" placeholder="مثال: وزارة التعليم" required>
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
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-toggle="modal" data-bs-target="#addOrgModal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary fw-bold px-4"><?php echo __('submit'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bg-success-soft {
    background-color: rgba(40, 167, 69, 0.15) !important;
}
.bg-danger-soft {
    background-color: rgba(220, 53, 69, 0.15) !important;
}
.transition-hover {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.transition-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08)!important;
}
.bg-gradient-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;
}
.x-small {
    font-size: 0.75rem;
}
</style>

<?php include '../includes/footer.php'; ?>
