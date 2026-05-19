<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$success = '';
$error = '';

// إضافة قسم جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $org_id = $_SESSION['organization_id'] ?? 1; // استخدام المعرف من الجلسة
    
    if (empty($name_ar) || empty($name_en)) {
        $error = 'All fields are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO departments (name_ar, name_en, organization_id) VALUES (?, ?, ?)");
            if ($stmt->execute([$name_ar, $name_en, $org_id])) {
                logActivity("➕ إضافة قسم جديد", "➕ Add New Department", "Name: $name_ar / $name_en");
                $success = __('success_added');
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// تعديل قسم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_department'])) {
    $id = $_POST['dept_id'];
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $org_id = $_SESSION['organization_id'] ?? 1;

    if (empty($name_ar) || empty($name_en)) {
        $error = 'All fields are required.';
    } else {
        try {
            // التحقق من ملكية القسم للمؤسسة للأمان
            $stmt = $pdo->prepare("UPDATE departments SET name_ar = ?, name_en = ? WHERE id = ? AND organization_id = ?");
            if ($stmt->execute([$name_ar, $name_en, $id, $org_id])) {
                logActivity("✏️ تعديل قسم", "✏️ Edit Department", "ID: $id, New Name: $name_ar / $name_en");
                $success = __('success_updated');
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// حذف قسم
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $org_id = $_SESSION['organization_id'] ?? 1;
    try {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ? AND organization_id = ?");
        if ($stmt->execute([$id, $org_id])) {
            logActivity("🗑️ حذف قسم", "🗑️ Delete Department", "ID: $id");
            $success = __('success_updated');
        }
    } catch (PDOException $e) {
        $error = 'Cannot delete department with existing employees.';
    }
}

$org_id = $_SESSION['organization_id'] ?? 1;
$stmt = $pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM employees WHERE department_id = d.id) as emp_count FROM departments d WHERE d.organization_id = ? ORDER BY d.name_ar ASC");
$stmt->execute([$org_id]);
$departments = $stmt->fetchAll();

$pageTitle = __('departments');
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo __('departments'); ?></h1>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
        <?php echo __('add_department'); ?>
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
                        <th><?php echo __('dept_name'); ?></th>
                        <th><?php echo __('dept_name_en'); ?></th>
                        <th><?php echo __('total_employees'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><?php echo h($dept['name_ar']); ?></td>
                            <td><?php echo h($dept['name_en']); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo $dept['emp_count']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDeptModal<?php echo $dept['id']; ?>">
                                    ✏️
                                </button>
                                <a href="?delete=<?php echo $dept['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?php echo __('confirm_delete'); ?>')">
                                    🗑️
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('add_department'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="departments.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('dept_name'); ?> *</label>
                        <input type="text" name="name_ar" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('dept_name_en'); ?> *</label>
                        <input type="text" name="name_en" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="add_department" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modals (Moved Outside Table Loop for Better Performance and Mobile Stability) -->
<?php foreach ($departments as $dept): ?>
<div class="modal fade" id="editDeptModal<?php echo $dept['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('edit_department'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="departments.php" method="POST">
                <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('dept_name'); ?> *</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo h($dept['name_ar']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('dept_name_en'); ?> *</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo h($dept['name_en']); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="edit_department" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>
