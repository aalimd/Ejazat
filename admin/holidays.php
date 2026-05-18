<?php
require_once '../includes/config.php';
checkAuth(['admin']); // فقط مدير المنشأة

$org_id = $_SESSION['organization_id'] ?? 1;
$success = '';
$error = '';

// إضافة عطلة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    $name_ar = trim($_POST['name_ar']);
    $name_en = trim($_POST['name_en']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if (empty($name_ar) || empty($name_en) || empty($start_date) || empty($end_date)) {
        $error = 'جميع الحقول مطلوبة.';
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error = 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO holidays (organization_id, name_ar, name_en, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$org_id, $name_ar, $name_en, $start_date, $end_date]);
            logActivity("📅 إضافة عطلة رسمية", "📅 Add Official Holiday", "Holiday: $name_ar ($start_date to $end_date)");
            $success = __('registration_success');
        } catch (PDOException $e) {
            $error = $e->getMessage();
        }
    }
}

// حذف عطلة
if (isset($_GET['delete'])) {
    $holiday_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = ? AND organization_id = ?");
        $stmt->execute([$holiday_id, $org_id]);
        logActivity("🗑️ حذف عطلة رسمية", "🗑️ Delete Official Holiday", "Holiday ID: $holiday_id deleted");
        $success = __('success_deleted');
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// جلب العطلات الرسمية للمؤسسة
$stmt = $pdo->prepare("SELECT * FROM holidays WHERE organization_id = ? ORDER BY start_date DESC");
$stmt->execute([$org_id]);
$holidays = $stmt->fetchAll();

$pageTitle = __('holidays');
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <div class="bg-primary text-white rounded p-2 me-3">
            📅
        </div>
        <h1 class="h3 mb-0"><?php echo __('holidays'); ?></h1>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#holidayModal">
        ➕ <?php echo __('add_holiday'); ?>
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0">
        ✅ <?php echo $success; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-0">
        ⚠️ <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4"><?php echo __('holiday_name'); ?> (AR)</th>
                        <th><?php echo __('holiday_name'); ?> (EN)</th>
                        <th><?php echo __('start_date'); ?></th>
                        <th><?php echo __('end_date'); ?></th>
                        <th><?php echo __('duration'); ?></th>
                        <th class="pe-4 text-end"><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($holidays)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($holidays as $h): 
                            $days = (strtotime($h['end_date']) - strtotime($h['start_date'])) / (60 * 60 * 24) + 1;
                        ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo h($h['name_ar']); ?></td>
                                <td><?php echo h($h['name_en']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo h($h['start_date']); ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?php echo h($h['end_date']); ?></span></td>
                                <td><strong class="text-primary"><?php echo $days; ?></strong> <?php echo __('days'); ?></td>
                                <td class="pe-4 text-end">
                                    <a href="holidays.php?delete=<?php echo $h['id']; ?>" class="btn btn-sm btn-outline-danger shadow-sm" onclick="return confirm('<?php echo __('confirm_delete'); ?>')">
                                        🗑️ <?php echo __('delete'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal إضافة عطلة -->
<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">📅 <?php echo __('add_holiday'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="holidays.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted"><?php echo __('holiday_name'); ?> (AR) *</label>
                        <input type="text" name="name_ar" class="form-control" placeholder="مثال: اليوم الوطني" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted"><?php echo __('holiday_name'); ?> (EN) *</label>
                        <input type="text" name="name_en" class="form-control" placeholder="e.g. National Day" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted"><?php echo __('start_date'); ?> *</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted"><?php echo __('end_date'); ?> *</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
                    <button type="submit" name="add_holiday" class="btn btn-primary px-4"><?php echo __('save_btn'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
