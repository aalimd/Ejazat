<?php
require_once '../includes/config.php';
checkAuth('admin'); // للأدمن فقط

$success = '';
$error = '';

// إضافة مستخدم جديد (Admin or Manager)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'manager';
        $allowed_roles = ($_SESSION['role'] === 'super_admin') ? ['super_admin', 'admin', 'manager'] : ['admin', 'manager'];

        if (!in_array($role, $allowed_roles, true)) {
            $error = __('access_denied');
        } elseif (empty($username) || empty($password) || empty($email)) {
            $error = __('fill_fields_error');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, organization_id) VALUES (?, ?, ?, ?, ?)");
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $org_id = ($role === 'super_admin') ? null : CURRENT_ORG_ID;
                if ($stmt->execute([$username, $hash, $email, $role, $org_id])) {
                    logActivity("➕ إضافة مستخدم نظام جديد", "➕ Add New System User", "Username: $username, Role: $role");
                    $success = __('success_added');
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = __('user_exists_error');
                } else {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// جلب قائمة المستخدمين الخاصة بالمؤسسة الحالية فقط
$stmt = $pdo->prepare("SELECT * FROM users WHERE organization_id = ? ORDER BY created_at DESC");
$stmt->execute([CURRENT_ORG_ID]);
$users = $stmt->fetchAll();

$pageTitle = __('system_users');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">👥 <?php echo __('system_users'); ?></h1>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <?php echo __('add_user'); ?>
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success shadow-sm"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th><?php echo __('username'); ?></th>
                        <th><?php echo __('email'); ?></th>
                        <th><?php echo __('role'); ?></th>
                        <th class="pe-3"><?php echo __('hire_date'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="ps-3 small text-muted">#<?php echo $user['id']; ?></td>
                                <td class="fw-bold text-primary"><?php echo h($user['username']); ?></td>
                                <td><?php echo h($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role'] == 'admin'): ?>
                                        <span class="badge bg-dark px-3"><?php echo __('admin'); ?></span>
                                    <?php elseif ($user['role'] == 'manager'): ?>
                                        <span class="badge bg-primary px-3"><?php echo __('manager'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark px-3"><?php echo __('employee'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 small text-muted"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal إضافة مستخدم -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><?php echo __('add_user'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('username'); ?> *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('email'); ?> *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('password'); ?> *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('role'); ?> *</label>
                        <select name="role" class="form-select" required>
                            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                            <option value="super_admin"><?php echo __('super_admin'); ?></option>
                            <?php endif; ?>
                            <option value="manager"><?php echo __('manager'); ?></option>
                            <option value="admin"><?php echo __('admin'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="add_user" class="btn btn-primary px-4 fw-bold"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>
