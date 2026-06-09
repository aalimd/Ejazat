<?php
require_once 'includes/config.php';
checkAuth();

$user_id = $_SESSION['user_id'];

// Pagination
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$countStmt->execute([$user_id]);
$total_records = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_records / $per_page));

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Mark all as read
$stmtMark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmtMark->execute([$user_id]);

$pageTitle = __('notifications_page');
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo __('notifications_page'); ?></h1>
    <span class="text-muted small"><?php echo __('total_records'); ?>: <?php echo $total_records; ?></span>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <div class="fs-1 mb-3 opacity-50"><span class="emoji-icon">🔔</span></div>
                <p><?php echo __('no_data'); ?></p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3 px-4 <?php echo !$notif['is_read'] ? 'bg-light' : ''; ?>">
                        <div class="flex-shrink-0 mt-1">
                            <span class="emoji-icon fs-5"><?php echo $notif['is_read'] ? '🔔' : '🔴'; ?></span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium"><?php echo h(get_name(['name_ar' => $notif['message_ar'], 'name_en' => $notif['message_en']])); ?></div>
                            <div class="small text-muted mt-1"><?php echo h($notif['created_at']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<nav aria-label="Notifications pagination" class="mt-4">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?p=<?php echo $page - 1; ?>"><?php echo __('prev'); ?></a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?p=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?p=<?php echo $page + 1; ?>"><?php echo __('next'); ?></a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
