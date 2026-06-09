<?php
require_once '../includes/config.php';
checkAuth(['admin']);

$org_id = CURRENT_ORG_ID;

// Pagination
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 100;
$offset = ($page - 1) * $per_page;

if ($_SESSION['role'] === 'super_admin' && empty($org_id)) {
    $countQuery = "SELECT COUNT(*) FROM activity_log al";
    $total_records = (int)$pdo->query($countQuery)->fetchColumn();
    $total_pages = max(1, (int)ceil($total_records / $per_page));

    $query = "SELECT al.*, u.username 
              FROM activity_log al 
              LEFT JOIN users u ON al.user_id = u.id 
              ORDER BY al.created_at DESC 
              LIMIT $per_page OFFSET $offset";
    $logs = $pdo->query($query)->fetchAll();
} else {
    $countQuery = "SELECT COUNT(*) FROM activity_log al WHERE al.organization_id = ?";
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute([$org_id]);
    $total_records = (int)$stmtCount->fetchColumn();
    $total_pages = max(1, (int)ceil($total_records / $per_page));

    $query = "SELECT al.*, u.username 
              FROM activity_log al 
              LEFT JOIN users u ON al.user_id = u.id 
              WHERE al.organization_id = ?
              ORDER BY al.created_at DESC 
              LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$org_id]);
    $logs = $stmt->fetchAll();
}

$pageTitle = __('activity_log');
if ($_SESSION['role'] === 'super_admin') {
    include '../includes/superadmin_header.php';
} else {
    include '../includes/header.php';
}
?>

<div class="mb-4">
    <h1 class="h3"><i class="bi bi-journal-text"></i> <?php echo __('activity_log'); ?></h1>
    <p class="text-muted small"><?php echo __('total_records'); ?>: <?php echo $total_records; ?></p>
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
                                        <span class="text-muted small"><?php echo __('guest'); ?></span>
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

<?php if ($total_pages > 1): ?>
<nav aria-label="Activity log pagination" class="mt-4">
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

<?php if ($_SESSION['role'] === 'super_admin') { include '../includes/superadmin_footer.php'; } else { include '../includes/footer.php'; } ?>
