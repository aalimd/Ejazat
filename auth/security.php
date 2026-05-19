<?php
require_once '../includes/config.php';
checkAuth(); // All logged-in users can access

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user security settings
$stmt = $pdo->prepare("SELECT id, username, email, two_factor_enabled, two_factor_secret, organization_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Error: User not found.");
}

// Fetch current organization settings if needed
$org_id = $user['organization_id'] ?? 1;

// Check if user is trying to confirm setup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enable_2fa'])) {
        $secret = $_POST['temp_secret'] ?? '';
        $code = trim($_POST['code'] ?? '');
        
        if (empty($secret) || empty($code)) {
            $error = __('fill_fields_error');
        } else {
            // Verify code
            if (TotpHelper::verifyCode($secret, $code)) {
                // Save and enable
                $stmtSave = $pdo->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?");
                if ($stmtSave->execute([$secret, $user_id])) {
                    $success = __('2fa_enabled_success');
                    logActivity("🔒 تفعيل التحقق الثنائي", "🔒 Enable 2FA", "User enabled Two-Factor Authentication");
                    // Refresh data
                    $user['two_factor_enabled'] = 1;
                    $user['two_factor_secret'] = $secret;
                } else {
                    $error = 'Error saving database settings.';
                }
            } else {
                $error = __('2fa_invalid_code');
            }
        }
    } elseif (isset($_POST['disable_2fa'])) {
        $code = trim($_POST['code'] ?? '');
        
        if (empty($code)) {
            $error = __('fill_fields_error');
        } else {
            // Verify code to disable (for security)
            if (TotpHelper::verifyCode($user['two_factor_secret'], $code)) {
                $stmtDisable = $pdo->prepare("UPDATE users SET two_factor_secret = NULL, two_factor_enabled = 0 WHERE id = ?");
                if ($stmtDisable->execute([$user_id])) {
                    $success = __('2fa_disabled_success');
                    logActivity("🔓 إلغاء تفعيل التحقق الثنائي", "🔓 Disable 2FA", "User disabled Two-Factor Authentication");
                    // Refresh data
                    $user['two_factor_enabled'] = 0;
                    $user['two_factor_secret'] = null;
                } else {
                    $error = 'Error updating database settings.';
                }
            } else {
                $error = __('2fa_invalid_code');
            }
        }
    }
}

// Generate temp secret if not enabled yet
$temp_secret = '';
$qr_url = '';
if ($user && !$user['two_factor_enabled']) {
    $temp_secret = TotpHelper::generateSecret();
    $qr_url = TotpHelper::getQrUrl($user['username'], $temp_secret, SITE_NAME);
}

$pageTitle = __('security_settings');
include '../includes/header.php';
?>

<div class="mb-4 d-flex align-items-center">
    <div class="bg-primary text-white rounded p-2 me-3 fs-4">
        🔒
    </div>
    <h1 class="h3 mb-0"><?php echo __('security_settings'); ?></h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success shadow-sm border-0 d-flex align-items-center">
        ✅ <?php echo $success; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
        ⚠️ <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <div class="fw-bold text-dark fs-5">
                    🛡️ <?php echo __('2fa_title'); ?>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if ($user['two_factor_enabled']): ?>
                    <!-- تم التفعيل بالفعل -->
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
                        <span class="fs-3 me-3">🛡️</span>
                        <div>
                            <h6 class="fw-bold mb-1"><?php echo __('2fa_active_title'); ?></h6>
                            <p class="small mb-0 opacity-75"><?php echo __('2fa_active_desc'); ?></p>
                        </div>
                    </div>
                    
                    <form action="security.php" method="POST" class="border-top pt-4">
                        <h6 class="text-danger fw-bold mb-3"><?php echo __('2fa_disable_title'); ?></h6>
                        <p class="text-muted small mb-3"><?php echo __('2fa_disable_desc'); ?></p>
                        
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted"><?php echo __('2fa_code_label'); ?></label>
                                <input type="text" name="code" class="form-control text-center fs-4 letter-spacing-lg" placeholder="000000" maxlength="6" pattern="\d{6}" required>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" name="disable_2fa" class="btn btn-danger w-100 py-2 fw-bold shadow-sm">
                                    <?php echo __('2fa_disable_btn'); ?>
                                </button>
                            </div>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- لم يتم التفعيل، مرحلة الإعداد -->
                    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
                        <span class="fs-3 me-3">📲</span>
                        <div>
                            <h6 class="fw-bold mb-1"><?php echo __('2fa_setup_title'); ?></h6>
                            <p class="small mb-0 opacity-75"><?php echo __('2fa_setup_desc'); ?></p>
                        </div>
                    </div>

                    <div class="row g-4 align-items-center mb-4">
                        <div class="col-md-4 text-center">
                            <div class="p-2 border rounded bg-white shadow-sm d-inline-block">
                                <img src="<?php echo $qr_url; ?>" alt="Scan QR" class="img-fluid" style="width: 180px; height: 180px;">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="fw-bold mb-2"><?php echo __('2fa_step_1'); ?></h6>
                            <p class="text-muted small mb-3"><?php echo __('2fa_step_1_desc'); ?></p>
                            
                            <h6 class="fw-bold mb-2"><?php echo __('2fa_step_2'); ?></h6>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control text-center font-monospace bg-light" value="<?php echo $temp_secret; ?>" readonly id="secretKey">
                                <button class="btn btn-outline-secondary" type="button" onclick="copySecret()"><?php echo __('copy'); ?></button>
                            </div>
                        </div>
                    </div>

                    <form action="security.php" method="POST" class="border-top pt-4">
                        <input type="hidden" name="temp_secret" value="<?php echo $temp_secret; ?>">
                        <h6 class="fw-bold mb-3"><?php echo __('2fa_step_3'); ?></h6>
                        
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted"><?php echo __('2fa_code_label'); ?> *</label>
                                <input type="text" name="code" class="form-control text-center fs-4 letter-spacing-lg" placeholder="000000" maxlength="6" pattern="\d{6}" required autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <button type="submit" name="enable_2fa" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                                    <?php echo __('2fa_enable_btn'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copySecret() {
    var copyText = document.getElementById("secretKey");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    alert("<?php echo __('copied'); ?>: " + copyText.value);
}
</script>

<style>
.letter-spacing-lg {
    letter-spacing: 0.5rem;
}
</style>

<?php include '../includes/footer.php'; ?>
