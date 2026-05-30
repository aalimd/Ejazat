<?php
require_once '../includes/config.php';

// التحقق من صلاحيات المسؤول - يسمح فقط لـ Super Admin
if ($_SESSION['role'] !== 'super_admin') {
    redirect('index.php');
}

$error = '';
$success = '';
$message = '';

// معالجة تحديث حالة العام/الخاص
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $org_id = intval($_POST['org_id'] ?? 0);
    
    if ($action === 'toggle_visibility') {
        try {
            $stmt = $pdo->prepare("SELECT is_public FROM organizations WHERE id = ?");
            $stmt->execute([$org_id]);
            $org = $stmt->fetch();
            
            if ($org) {
                $new_visibility = $org['is_public'] ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE organizations SET is_public = ? WHERE id = ?");
                $stmt->execute([$new_visibility, $org_id]);
                
                $visibility_text = $new_visibility ? __('public') : __('private');
                logActivity(
                    "🔄 تحديث رؤية المؤسسة",
                    "🔄 Updated Organization Visibility",
                    "Organization ID: {$org_id}, New Status: {$visibility_text}"
                );
                
                $success = __('visibility_updated');
            } else {
                $error = __('org_not_found');
            }
        } catch (PDOException $e) {
            $error = __('error_generic') . ': ' . $e->getMessage();
        }
    } elseif ($action === 'regenerate_code') {
        $result = regenerateOrganizationCode($org_id, $_SESSION['user_id']);
        if ($result['success']) {
            $success = __('code_updated') . ': <strong>' . $result['new_code'] . '</strong>';
            if ($result['old_code']) {
                $success .= '<br><small class="text-muted">' . __('previous_code') . ': ' . $result['old_code'] . '</small>';
            }
        } else {
            $error = $result['message'];
        }
    }
}

// جلب جميع المؤسسات مع أكوادها
$orgs_query = $pdo->prepare("
    SELECT 
        o.id, 
        o.name_ar, 
        o.name_en, 
        o.is_public,
        oic.code,
        oic.code_regenerated_at,
        oic.is_active
    FROM organizations o
    LEFT JOIN organization_invitation_codes oic ON o.id = oic.organization_id AND oic.is_active = 1
    WHERE o.is_active = 1
    ORDER BY o.name_ar ASC
");
$orgs_query->execute();
$organizations = $orgs_query->fetchAll();

$pageTitle = __('org_codes_mgmt');
include '../includes/superadmin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">🔐 <?php echo __('org_codes_mgmt'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('org_codes_desc'); ?></p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 mb-4" role="alert">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0"><?php echo __('org_list'); ?></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light fw-bold">
                        <tr>
                            <th class="text-center"><?php echo __('status_label'); ?></th>
                            <th><?php echo __('org_name'); ?></th>
                            <th class="text-center"><?php echo __('invitation_code'); ?></th>
                            <th><?php echo __('last_code_update'); ?></th>
                            <th class="text-center"><?php echo __('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organizations as $org): ?>
                        <tr>
                            <td class="text-center">
                                <?php if ($org['is_public']): ?>
                                    <span class="badge bg-success"><?php echo __('public'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><?php echo __('private'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold">
                                <?php echo h($org['name_ar']); ?>
                                <br><small class="text-muted"><?php echo h($org['name_en']); ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ($org['code']): ?>
                                    <code class="bg-light p-2 rounded d-inline-block fw-bold" style="font-size: 14px;">
                                        <?php echo h($org['code']); ?>
                                    </code>
                                    <br>
                                    <button class="btn btn-sm btn-outline-secondary mt-2 copy-code" data-code="<?php echo h($org['code']); ?>" title="<?php echo __('copy_code'); ?>">
                                        <?php echo __('copy_code'); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?php echo $org['code_regenerated_at'] ? date('Y-m-d H:i', strtotime($org['code_regenerated_at'])) : __('not_updated'); ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_visibility">
                                        <input type="hidden" name="org_id" value="<?php echo $org['id']; ?>">
                                        <?php if ($org['is_public']): ?>
                                            <button type="submit" class="btn btn-sm btn-warning" title="<?php echo __('make_private'); ?>" onclick="return confirm('<?php echo __('confirm_make_private'); ?>')">
                                                <?php echo __('make_private'); ?>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-success" title="<?php echo __('make_public'); ?>" onclick="return confirm('<?php echo __('confirm_make_public'); ?>')">
                                                <?php echo __('make_public'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="regenerate_code">
                                        <input type="hidden" name="org_id" value="<?php echo $org['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-info" title="<?php echo __('new_code'); ?>" onclick="return confirm('<?php echo __('confirm_new_code'); ?>')">
                                            <?php echo __('new_code'); ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="row mt-5">
        <div class="col-md-6">
            <div class="card shadow-sm border-start border-primary border-5">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-primary"><?php echo __('public'); ?></h5>
                    <p class="card-text text-muted mb-3">
                        <?php echo __('public_orgs_desc'); ?>
                    </p>
                    <ul class="text-muted small">
                        <li><?php echo __('no_code_required'); ?></li>
                        <li><?php echo __('visible_to_all'); ?></li>
                        <li><?php echo __('default_option'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-start border-danger border-5">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-danger"><?php echo __('private'); ?></h5>
                    <p class="card-text text-muted mb-3">
                        <?php echo __('private_orgs_desc'); ?>
                    </p>
                    <ul class="text-muted small">
                        <li><?php echo __('requires_code'); ?></li>
                        <li><?php echo __('not_visible_in_list'); ?></li>
                        <li><?php echo __('more_secure'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Instructions Section -->
    <div class="card shadow-sm mt-5 bg-light border-0">
        <div class="card-body">
            <h5 class="card-title fw-bold"><?php echo __('usage_steps'); ?></h5>
            <div class="row text-muted small">
                <div class="col-md-4">
                    <h6 class="fw-bold text-primary"><?php echo __('step1_title'); ?></h6>
                    <p><?php echo __('step1_desc'); ?></p>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold text-success"><?php echo __('step2_title'); ?></h6>
                    <p><?php echo __('step2_desc'); ?></p>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold text-danger"><?php echo __('step3_title'); ?></h6>
                    <p><?php echo __('step3_desc'); ?></p>
                </div>
            </div>
        </div>
    </div>

<script>
document.querySelectorAll('.copy-code').forEach(button => {
    button.addEventListener('click', function() {
        const code = this.getAttribute('data-code');
        navigator.clipboard.writeText(code).then(() => {
            alert("<?php echo __('code_copied'); ?>: " + code);
        });
    });
});
</script>

<?php include '../includes/superadmin_footer.php'; ?>
