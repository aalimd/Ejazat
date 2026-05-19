<?php
require_once '../includes/config.php';
checkAuth(['admin']); // فقط مدير النظام يمكنه رؤية السجل

$org_id = CURRENT_ORG_ID;

if ($_SESSION['role'] === 'super_admin' && empty($org_id)) {
    // الـ Super Admin يرى كل السجلات إذا لم يحدد منشأة
    $query = "SELECT al.*, u.username 
              FROM activity_log al 
              LEFT JOIN users u ON al.user_id = u.id 
              ORDER BY al.created_at DESC 
              LIMIT 500";
    $logs = $pdo->query($query)->fetchAll();
} else {
    // مدير المنشأة أو الـ Super Admin المتقمص لجهة معينة يرى فقط سجلات هذه المنشأة
    $query = "SELECT al.*, u.username 
              FROM activity_log al 
              LEFT JOIN users u ON al.user_id = u.id 
              WHERE al.organization_id = ?
              ORDER BY al.created_at DESC 
              LIMIT 500";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$org_id]);
    $logs = $stmt->fetchAll();
}

$pageTitle = __('activity_log');
include '../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3"><?php echo __('activity_log'); ?></h1>
    <p class="text-muted small"><?php echo __('Showing last 500 actions'); ?></p>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?php echo __('username'); ?></th>
                        <th><?php echo __('action'); ?></th>
                        <th><?php echo __('details'); ?></th>
                        <th><?php echo __('ip_address'); ?></th>
                        <th><?php echo __('time'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted"><?php echo __('no_data'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <span class="badge bg-light text-dark border"><?php echo h($log['username']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small"><?php echo __('Guest'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo h(get_name(['name_ar' => $log['action_ar'], 'name_en' => $log['action_en']])); ?></span>
                                </td>
                                <td>
                                    <div class="small text-muted text-truncate" style="max-width: 300px;" title="<?php echo h($log['details']); ?>">
                                        <?php echo h($log['details']); ?>
                                    </div>
                                </td>
                                <td><code class="small text-primary"><?php echo h($log['ip_address']); ?></code></td>
                                <td class="small text-muted"><?php echo h($log['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
