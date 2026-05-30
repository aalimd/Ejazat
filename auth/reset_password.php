<?php
require_once '../includes/config.php';

$error = '';
$success = '';
$show_form = false;
$user_id = null;

// If already logged in, redirect
if (isLoggedIn()) {
    redirect('index.php');
}

// Verify reset token and display form
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $user_id = verifyPasswordResetToken($token);
    
    if (!$user_id) {
        $error = __('invalid_or_expired_token');
    } else {
        $show_form = true;
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!verify_csrf()) {
        $error = __('fill_fields_error');
    } else {
    $token = trim($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        $error = __('fill_fields_error');
    } elseif ($new_password !== $confirm_password) {
        $error = __('passwords_do_not_match');
    } elseif (strlen($new_password) < 8) {
        $error = __('password_min_length');
    } else {
        // Validate token and get user
        $user_id = verifyPasswordResetToken($token);
        
        if (!$user_id) {
            $error = __('invalid_or_expired_token');
        } else {
            try {
                $pdo->beginTransaction();
                
                // Hash and update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Mark token as used
                markPasswordResetTokenAsUsed($token);
                
                // Clear any login attempts
                $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt_user->execute([$user_id]);
                $user = $stmt_user->fetch();
                if ($user) {
                    clearLoginAttempts($user['username']);
                }
                
                $pdo->commit();
                
                $success = __('password_reset_success');
                $show_form = false;
                
                logActivity("🔐 إعادة تعيين كلمة المرور", "🔐 Password Reset", "User ID: $user_id");
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Error resetting password: ' . $e->getMessage();
            }
        }
    }
    }
}

$pageTitle = __('reset_password');
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="fs-1 mb-3">🔑</div>
                        <h4 class="fw-bold"><?php echo __('reset_password'); ?></h4>
                        <p class="text-muted small"><?php echo __('site_name'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small">
                            ⚠️ <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success py-3 small text-center">
                            <strong><?php echo $success; ?></strong>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary px-4 shadow-sm">
                                🔐 <?php echo __('login'); ?>
                            </a>
                        </div>
                    <?php elseif ($show_form): ?>
                        <form action="reset_password.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="token" value="<?php echo h($_GET['token']); ?>">
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label small fw-bold"><?php echo __('new_password'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">🔒</span>
                                    <input type="password" name="new_password" id="new_password" class="form-control bg-light" required>
                                </div>
                                <small class="text-muted d-block mt-1"><?php echo __('password_reset_instruction'); ?></small>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label small fw-bold"><?php echo __('confirm_password'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">🔒</span>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control bg-light" required>
                                </div>
                            </div>

                            <button type="submit" name="reset_password" class="btn btn-primary w-100 py-2 fw-bold">
                                ✅ <?php echo __('reset_password'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning text-center py-4">
                            <?php echo __('request_reset_link'); ?>
                        </div>
                        <div class="text-center">
                            <a href="forgot_password.php" class="btn btn-primary px-4 shadow-sm">
                                📧 <?php echo __('forgot_password'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <div class="text-center mt-4 border-top pt-3">
                        <span class="text-muted small"><?php echo __('remember_password'); ?></span>
                        <a href="login.php" class="small fw-bold text-decoration-none"><?php echo __('login'); ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
