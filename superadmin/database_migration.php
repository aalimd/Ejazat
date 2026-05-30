<?php
require_once '../includes/config.php';
require_once '../includes/MigrationService.php';
checkSuperAdmin();

$pageTitle = __('db_migration_center');
$show_org_switcher = false;
$error = '';
$success = '';
$action_result = null;

$ms = new MigrationService($pdo, (int)$_SESSION['user_id']);

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'run_preflight' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
        $_SESSION['migration_preflight'] = $ms->preflight();
        $_SESSION['migration_preflight_time'] = time();
        $preflight = $_SESSION['migration_preflight'];
        if ($preflight['all_pass']) {
            $success = __('preflight_success');
        } elseif ($preflight['has_warnings']) {
            $success = __('preflight_warn');
        } else {
            $error = __('preflight_fail');
        }
    }
}

if ($action === 'apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
        $version = $_POST['version'] ?? '';
        if (empty($version)) {
            $error = 'No version specified';
        } else {
            // Require recent preflight
            $preflight_time = $_SESSION['migration_preflight_time'] ?? 0;
            if (time() - $preflight_time > 300) {
                $error = __('preflight_required');
            } else {
                $action_result = $ms->apply($version);
                if ($action_result['success']) {
                    $success = __('migration_success') . ' — ' . $action_result['name'] . ' (' . $action_result['duration_ms'] . 'ms)';
                } else {
                    $error = __('migration_failed') . ': ' . ($action_result['error'] ?? $action_result['output'] ?? 'Unknown error');
                }
            }
        }
    }
}

if ($action === 'apply_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
        $preflight_time = $_SESSION['migration_preflight_time'] ?? 0;
        if (time() - $preflight_time > 300) {
            $error = __('preflight_required');
        } else {
            $action_result = $ms->applyAll();
            $sc = $action_result['success_count'];
            $tc = $action_result['total'];
            if ($sc > 0) {
                $success = sprintf(__('migration_applied_all'), $sc, $tc);
            }
            if ($action_result['fail_count'] > 0) {
                $failures = [];
                foreach ($action_result['results'] as $v => $r) {
                    if (!$r['success']) $failures[] = $v . ': ' . ($r['error'] ?? 'Unknown');
                }
                $error = __('migration_failed') . ': ' . implode('; ', $failures);
            }
        }
    }
}

if ($action === 'rollback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = __('access_denied');
    } else {
        $version = $_POST['version'] ?? '';
        if (empty($version)) {
            $error = 'No version specified';
        } else {
            $action_result = $ms->rollback($version);
            if ($action_result['success']) {
                $success = __('rollback_success') . ' — ' . $action_result['name'] . ' (' . $action_result['duration_ms'] . 'ms)';
            } else {
                $error = __('rollback_failed') . ': ' . ($action_result['error'] ?? 'Unknown error');
            }
        }
    }
}

// Load state
$status = $ms->getStatus();
$migrations = $ms->getAllMigrations();
$pending = array_filter($migrations, fn($m) => $m['status'] === 'pending' || $m['status'] === 'failed');
$applied_history = $ms->getAppliedMigrations();
$preflight = $_SESSION['migration_preflight'] ?? null;
$preflight_time = $_SESSION['migration_preflight_time'] ?? 0;

include '../includes/superadmin_header.php';
?>
<style>
    .mm-badge { font-size: 0.7rem; font-weight: 700; padding: 0.2em 0.6em; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.03em; }
    .mm-badge-applied { background: rgba(34,197,94,0.12); color: #22c55e; }
    .mm-badge-pending { background: rgba(245,158,11,0.12); color: #f59e0b; }
    .mm-badge-failed { background: rgba(239,68,68,0.12); color: #ef4444; }
    .mm-badge-rolled_back { background: rgba(107,114,128,0.12); color: #6b7280; }
    .mm-stat { background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border); border-radius: 10px; padding: 1.25rem; }
    .mm-stat-value { font-size: 1.5rem; font-weight: 800; line-height: 1.1; color: var(--text-main); }
    .mm-stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }
    .mm-preflight-pass { border-left: 3px solid #22c55e; padding-left: 0.75rem; }
    .mm-preflight-warn { border-left: 3px solid #f59e0b; padding-left: 0.75rem; }
    .mm-preflight-fail { border-left: 3px solid #ef4444; padding-left: 0.75rem; }
    .mm-migration-row { transition: background 0.15s; }
    .mm-migration-row:hover { background: rgba(0,0,0,0.02); }
    [data-theme="dark"] .mm-migration-row:hover { background: rgba(255,255,255,0.03); }
    .mm-timestamp { font-size: 0.72rem; color: var(--text-muted); }
    .mm-code-block { background: #1e293b; color: #e2e8f0; border-radius: 6px; padding: 0.75rem; font-size: 0.75rem; font-family: 'SF Mono', 'Cascadia Code', monospace; overflow-x: auto; max-height: 200px; white-space: pre-wrap; word-break: break-all; }
    .mm-check-label { font-size: 0.8rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--text-main);"><i class="bi bi-database"></i> <?php echo __('db_migration_center'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('db_migration_desc'); ?></p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert"><?php echo h($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Status Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="mm-stat">
            <div class="mm-stat-value"><?php echo h($status['current_version']); ?></div>
            <div class="mm-stat-label"><?php echo __('current_db_version'); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mm-stat">
            <div class="mm-stat-value" style="color: <?php echo $status['pending'] > 0 ? '#f59e0b' : '#22c55e'; ?>;"><?php echo $status['pending']; ?></div>
            <div class="mm-stat-label"><?php echo __('pending_migrations'); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mm-stat">
            <div class="mm-stat-value" style="color: #22c55e;"><?php echo $status['applied']; ?></div>
            <div class="mm-stat-label"><?php echo __('applied_migrations'); ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mm-stat">
            <div class="mm-stat-value" style="color: <?php echo $status['failed'] > 0 ? '#ef4444' : '#22c55e'; ?>;"><?php echo $status['failed']; ?></div>
            <div class="mm-stat-label"><?php echo __('failed_migrations'); ?></div>
        </div>
    </div>
</div>

<!-- Last Update -->
<?php if ($status['last_applied']): ?>
<div class="mb-4 mm-timestamp"><?php echo __('last_update'); ?>: <?php echo h($status['last_applied']['name']); ?> — <?php echo h($status['last_applied']['completed_at']); ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Migration Queue -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" style="background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border) !important;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center" style="border-color: var(--sa-topbar-border) !important;">
                <h6 class="fw-bold mb-0" style="color: var(--text-main);"><i class="bi bi-clipboard"></i> <?php echo __('pending_migrations'); ?></h6>
            </div>
            <div class="card-body p-3">
                <?php if (empty($pending)): ?>
                    <div class="text-center py-4">
                        <div class="fs-1 mb-2" style="opacity:0.3;"><i class="bi bi-check-circle"></i></div>
                        <p class="text-muted small mb-0"><?php echo __('no_pending_migrations'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-muted">
                                    <th><?php echo __('migration_version'); ?></th>
                                    <th><?php echo __('migration_name'); ?></th>
                                    <th><?php echo __('migration_status'); ?></th>
                                    <th class="text-end"><?php echo __('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending as $v => $m): ?>
                                <tr class="mm-migration-row">
                                    <td><code class="small fw-bold"><?php echo h($v); ?></code></td>
                                    <td>
                                        <div class="fw-semibold small" style="color: var(--text-main);"><?php echo h($m['name']); ?></div>
                                        <?php if ($m['description']): ?>
                                        <div class="text-muted" style="font-size:0.72rem;"><?php echo h($m['description']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="mm-badge mm-badge-<?php echo $m['status'] === 'failed' ? 'failed' : 'pending'; ?>">
                                            <?php echo $m['status'] === 'failed' ? __('failed_migrations') : __('pending_migrations'); ?>
                                        </span>
                                        <?php if ($m['status'] === 'failed' && $m['output']): ?>
                                        <div class="mm-timestamp mt-1"><a href="#" onclick="return toggleOutput(<?php echo "'output-{$v}'"; ?>)">View output</a></div>
                                        <div id="output-<?php echo $v; ?>" class="mm-code-block mt-1" style="display:none;"><?php echo h(substr($m['output'], 0, 2000)); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($preflight && $preflight['all_pass'] && (time() - $preflight_time) <= 300): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo __('confirm_migration_text'); ?>')">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="apply">
                                            <input type="hidden" name="version" value="<?php echo h($v); ?>">
                                            <button type="submit" class="btn btn-sm btn-success mm-badge"><?php echo __('apply'); ?></button>
                                        </form>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary mm-badge" disabled><?php echo __('apply'); ?></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($preflight && $preflight['all_pass'] && (time() - $preflight_time) <= 300): ?>
                    <div class="mt-3 text-end">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo __('confirm_migration_text'); ?>')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="apply_all">
                            <button type="submit" class="btn btn-primary fw-bold btn-sm px-4"><i class="bi bi-rocket"></i> <?php echo __('apply_all'); ?></button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Safety & Preflight Panel -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4" style="background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border) !important;">
            <div class="card-header bg-transparent border-bottom py-3" style="border-color: var(--sa-topbar-border) !important;">
                <h6 class="fw-bold mb-0" style="color: var(--text-main);"><i class="bi bi-shield-check"></i> <?php echo __('migration_safety'); ?></h6>
            </div>
            <div class="card-body p-3">
                <!-- Backup reminder -->
                <div class="d-flex align-items-start gap-2 mb-3 p-2 rounded" style="background: rgba(245,158,11,0.08);">
                    <span style="font-size:1.1rem;"><i class="bi bi-exclamation-triangle"></i></span>
                    <div>
                        <div class="fw-semibold small" style="color: var(--text-main);"><?php echo __('safety_backup'); ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?php echo __('safety_backup_desc'); ?></div>
                        <div class="mt-1">
                            <a href="#" onclick="return confirmBackup()" class="small fw-semibold" style="color: var(--sa-sidebar-active);"><i class="bi bi-check-circle"></i> <?php echo __('confirm_migration'); ?></a>
                        </div>
                    </div>
                </div>

                <script>
                function confirmBackup() {
                    let msg = document.documentElement.getAttribute('dir') === 'rtl'
                        ? 'هل قمت بأخذ نسخة احتياطية من قاعدة البيانات؟'
                        : 'Have you taken a database backup?';
                    if (confirm(msg)) {
                        document.getElementById('backupConfirmed').value = '1';
                        document.getElementById('backupStatus').innerHTML = '<span style="color:#22c55e;font-weight:600;"><i class="bi bi-check-circle"></i> <?php echo __('confirm_migration'); ?></span>';
                    }
                    return false;
                }
                </script>
                <div id="backupStatus" class="small text-muted"><i class="bi bi-x-circle"></i> <?php echo __('migration_not_applied'); ?></div>
                <input type="hidden" id="backupConfirmed" value="0">

                <hr style="border-color: var(--sa-topbar-border);">

                <!-- Preflight Check -->
                <form method="POST" class="d-flex justify-content-between align-items-center">
                    <?php echo csrf_field(); ?>
                    <span class="fw-semibold small" style="color: var(--text-main);"><i class="bi bi-search"></i> <?php echo __('preflight_check'); ?></span>
                    <button type="submit" name="action" value="run_preflight" class="btn btn-sm btn-outline-primary fw-bold"><?php echo __('run_preflight'); ?></button>
                </form>

                <?php if ($preflight): ?>
                <div class="mt-3">
                    <?php if ($preflight['all_pass'] && !$preflight['has_warnings']): ?>
                        <div class="mm-preflight-pass mb-2">
                            <span class="fw-semibold small" style="color:#22c55e;"><i class="bi bi-check-circle"></i> <?php echo __('preflight_pass'); ?></span>
                        </div>
                    <?php elseif ($preflight['all_pass']): ?>
                        <div class="mm-preflight-warn mb-2">
                            <span class="fw-semibold small" style="color:#f59e0b;"><i class="bi bi-exclamation-triangle"></i> <?php echo __('preflight_warn'); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="mm-preflight-fail mb-2">
                            <span class="fw-semibold small" style="color:#ef4444;"><i class="bi bi-x-circle"></i> <?php echo __('preflight_fail'); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex flex-column gap-1 mt-2">
                        <?php foreach ($preflight['checks'] as $check): ?>
                        <div class="d-flex justify-content-between align-items-center mm-check-label">
                            <span class="text-muted"><?php echo h($check['name']); ?></span>
                            <span class="fw-semibold" style="color: <?php echo $check['status'] === 'pass' ? '#22c55e' : ($check['status'] === 'warn' ? '#f59e0b' : '#ef4444'); ?>;">
                                <?php echo $check['status'] === 'pass' ? '<i class="bi bi-check-circle"></i>' : ($check['status'] === 'warn' ? '<i class="bi bi-exclamation-triangle"></i>' : '<i class="bi bi-x-circle"></i>'); ?>
                                <?php echo h($check['message']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Migration History -->
<div class="card border-0 shadow-sm mt-4" style="background: var(--sa-topbar-bg); border: 1px solid var(--sa-topbar-border) !important;">
    <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center" style="border-color: var(--sa-topbar-border) !important;">
        <h6 class="fw-bold mb-0" style="color: var(--text-main);"><i class="bi bi-journal-text"></i> <?php echo __('migration_history'); ?></h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($applied_history)): ?>
            <div class="text-center py-4">
                <p class="text-muted small mb-0"><?php echo __('no_data'); ?></p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="small text-muted">
                    <tr>
                        <th><?php echo __('migration_version'); ?></th>
                        <th><?php echo __('migration_name'); ?></th>
                        <th><?php echo __('migration_status'); ?></th>
                        <th><?php echo __('migration_checksum'); ?></th>
                        <th><?php echo __('migration_duration'); ?></th>
                        <th><?php echo __('migration_executed_by'); ?></th>
                        <th><?php echo __('migration_executed_at'); ?></th>
                        <th class="text-end"><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applied_history as $v => $m): ?>
                    <tr class="mm-migration-row">
                        <td><code class="small fw-bold"><?php echo h($v); ?></code></td>
                        <td>
                            <div class="fw-semibold small" style="color: var(--text-main);"><?php echo h($m['name']); ?></div>
                            <?php if ($m['description']): ?>
                            <div class="text-muted" style="font-size:0.72rem;"><?php echo h($m['description']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="mm-badge mm-badge-<?php echo $m['status']; ?>">
                                <?php echo $m['status'] === 'applied' ? __('applied_migrations') : ($m['status'] === 'rolled_back' ? 'Rolled Back' : __('failed_migrations')); ?>
                            </span>
                            <?php if ($m['status'] === 'rolled_back'): ?>
                            <div class="mm-timestamp"><?php echo h($m['completed_at']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code class="small text-muted" style="font-size:0.65rem;"><?php echo h(substr($m['checksum'], 0, 12)); ?>...</code>
                            <?php if ($m['integrity_pass'] === true): ?>
                                <span class="mm-timestamp" style="color:#22c55e;"><i class="bi bi-check-circle"></i></span>
                            <?php elseif ($m['integrity_pass'] === false): ?>
                                <span class="mm-timestamp" style="color:#ef4444;"><i class="bi bi-x-circle"></i></span>
                            <?php else: ?>
                                <span class="mm-timestamp">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?php echo $m['duration_ms'] ? $m['duration_ms'] . 'ms' : '—'; ?></td>
                        <td class="small text-muted"><?php echo h($m['executed_by'] ?? '—'); ?></td>
                        <td class="small text-muted"><?php echo h($m['completed_at'] ?? $m['executed_at'] ?? '—'); ?></td>
                        <td class="text-end">
                            <?php if ($m['status'] === 'applied' && $m['has_down'] && $preflight && $preflight['all_pass'] && (time() - $preflight_time) <= 300): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo __('confirm_rollback_text'); ?>')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="rollback">
                                <input type="hidden" name="version" value="<?php echo h($v); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger mm-badge"><i class="bi bi-arrow-return-left"></i> <?php echo __('rollback'); ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($m['output']): ?>
                            <button class="btn btn-sm btn-outline-secondary mm-badge" onclick="toggleOutput('hist-<?php echo $v; ?>')"><i class="bi bi-eye"></i></button>
                            <div id="hist-<?php echo $v; ?>" class="mm-code-block mt-1" style="display:none;"><?php echo h(substr($m['output'], 0, 2000)); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleOutput(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = el.style.display === 'none' ? 'block' : 'none'; }
    return false;
}
</script>

<?php include '../includes/superadmin_footer.php'; ?>
