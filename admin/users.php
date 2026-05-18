<?php
require_once '../includes/config.php';
checkAuth('admin'); // للأدمن فقط

$success = '';
$error = '';

// إضافة مستخدم جديد (Admin or Manager)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'manager';

    if (empty($username) || empty($password) || empty($email)) {
        $error = 'All fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($stmt->execute([$username, $hash, $email, $role])) {
                logActivity("➕ إضافة مستخدم نظام جديد", "➕ Add New System User", "Username: $username, Role: $role");
                $success = __('success_added');
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Username or email already exists.';
            } else {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// جلب قائمة المستخدمين
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = __('system_users');
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo __('system_users'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <?php echo __('add_user'); ?>
    </button>
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
                        <th>ID</th>
                        <th><?php echo __('username'); ?></th>
                        <th><?php echo __('email'); ?></th>
                        <th><?php echo __('role'); ?></th>
                        <th><?php echo __('hire_date'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td class="fw-bold"><?php echo h($user['username']); ?></td>
                            <td><?php echo h($user['email']); ?></td>
                            <td>
                                <?php if ($user['role'] == 'admin'): ?>
                                    <span class="badge bg-dark"><?php echo __('admin'); ?></span>
                                <?php elseif ($user['role'] == 'manager'): ?>
                                    <span class="badge bg-primary"><?php echo __('manager'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><?php echo __('employee'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?php echo $user['created_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal إضافة مستخدم -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('add_user'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body">
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
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('role'); ?> *</label>
                        <select name="role" class="form-select" required>
                            <option value="manager"><?php echo __('manager'); ?></option>
                            <option value="admin"><?php echo __('admin'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="add_user" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
