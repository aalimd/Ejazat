<?php
require_once '../includes/config.php';
checkAuth(['admin', 'manager']);

$success = '';
$error = '';
$org_id = CURRENT_ORG_ID ?? 1;

// إضافة نوع إجازة جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave_type'])) {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $deduct = isset($_POST['deduct_from_balance']) ? 1 : 0;
    $max_days = !empty($_POST['max_days_per_year']) ? (int)$_POST['max_days_per_year'] : 30;

    if (empty($name_ar) || empty($name_en)) {
        $error = __('fill_fields_error');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO leave_types (organization_id, name_ar, name_en, deduct_from_balance, max_days_per_year) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$org_id, $name_ar, $name_en, $deduct, $max_days])) {
                logActivity("➕ إضافة نوع إجازة", "➕ Add Leave Type", "Name: $name_ar / $name_en, Deduct: $deduct, Max: $max_days");
                $success = __('success_added');
            }
        } catch (PDOException $e) {
            $error = __('db_error') . ': ' . $e->getMessage();
        }
    }
}

// تعديل نوع إجازة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_leave_type'])) {
    $id = $_POST['type_id'];
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $deduct = isset($_POST['deduct_from_balance']) ? 1 : 0;
    $max_days = !empty($_POST['max_days_per_year']) ? (int)$_POST['max_days_per_year'] : 30;

    if (empty($name_ar) || empty($name_en)) {
        $error = __('fill_fields_error');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE leave_types SET name_ar = ?, name_en = ?, deduct_from_balance = ?, max_days_per_year = ? WHERE id = ? AND organization_id = ?");
            if ($stmt->execute([$name_ar, $name_en, $deduct, $max_days, $id, $org_id])) {
                logActivity("✏️ تعديل نوع إجازة", "✏️ Edit Leave Type", "ID: $id, New Name: $name_ar / $name_en, Deduct: $deduct, Max: $max_days");
                $success = __('success_updated');
            }
        } catch (PDOException $e) {
            $error = __('db_error') . ': ' . $e->getMessage();
        }
    }
}

// حذف نوع إجازة
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ? AND organization_id = ?");
        if ($stmt->execute([$id, $org_id])) {
            logActivity("🗑️ حذف نوع إجازة", "🗑️ Delete Leave Type", "ID: $id");
            $success = __('success_updated');
        }
    } catch (PDOException $e) {
        $error = 'Cannot delete this type with existing requests.';
    }
}

$stmt = $pdo->prepare("SELECT lt.*, (SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = lt.id) as request_count FROM leave_types lt WHERE lt.organization_id = ? ORDER BY lt.name_ar ASC");
$stmt->execute([$org_id]);
$leave_types = $stmt->fetchAll();

$pageTitle = __('leave_types');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo __('leave_types'); ?></h1>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addTypeModal">
        <?php echo __('add_leave_type'); ?>
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
                        <th><?php echo __('type_name'); ?></th>
                        <th><?php echo __('type_name_en'); ?></th>
                        <th><?php echo __('deduct_from_balance'); ?></th>
                        <th><?php echo __('max_days_per_year'); ?></th>
                        <th><?php echo __('my_requests'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_types as $type): ?>
                        <tr>
                            <td><?php echo h($type['name_ar']); ?></td>
                            <td><?php echo h($type['name_en']); ?></td>
                            <td>
                                <?php if ($type['deduct_from_balance']): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger small">
                                        <?php echo __('yes'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success small">
                                        <?php echo __('no'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="fw-bold"><?php echo $type['max_days_per_year']; ?></span> <?php echo __('days'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo $type['request_count']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTypeModal<?php echo $type['id']; ?>">
                                    ✏️
                                </button>
                                <a href="?delete=<?php echo $type['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?php echo __('confirm_delete'); ?>')">
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
<div class="modal fade" id="addTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('add_leave_type'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="leave_types.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('type_name'); ?> *</label>
                        <input type="text" name="name_ar" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('type_name_en'); ?> *</label>
                        <input type="text" name="name_en" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('max_days_per_year'); ?></label>
                        <input type="number" name="max_days_per_year" class="form-control" value="30" required min="1">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="deduct_from_balance" id="deductAdd" checked>
                        <label class="form-check-label fw-bold" for="deductAdd"><?php echo __('deduct_from_balance'); ?></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="add_leave_type" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modals (Moved Outside Loop for Stability) -->
<?php foreach ($leave_types as $type): ?>
<div class="modal fade" id="editTypeModal<?php echo $type['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('edit_leave_type'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="leave_types.php" method="POST">
                <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('type_name'); ?> *</label>
                        <input type="text" name="name_ar" class="form-control" value="<?php echo h($type['name_ar']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('type_name_en'); ?> *</label>
                        <input type="text" name="name_en" class="form-control" value="<?php echo h($type['name_en']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><?php echo __('max_days_per_year'); ?></label>
                        <input type="number" name="max_days_per_year" class="form-control" value="<?php echo h($type['max_days_per_year']); ?>" required min="1">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="deduct_from_balance" id="deductEdit<?php echo $type['id']; ?>" <?php echo $type['deduct_from_balance'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="deductEdit<?php echo $type['id']; ?>"><?php echo __('deduct_from_balance'); ?></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" name="edit_leave_type" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>
