<?php
require_once '../includes/config.php';

$error = '';
$success = '';

// If already logged in, redirect
if (isLoggedIn()) {
    redirect('index.php');
}

// Handle forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (!verify_csrf()) {
        $error = __('enter_valid_email');
    } else {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('enter_valid_email');
    } else {
        // Find user by email (don't reveal if email exists or not for security)
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Send password reset email
            $res = sendPasswordResetEmailToUser($email, $user['username']);
            
            if ($res['success']) {
                $success = __('reset_link_sent');
                logActivity("📧 طلب إعادة تعيين كلمة المرور", "📧 Forgot Password Request", "Email: $email");
            } else {
                error_log('Password reset email failed: ' . ($res['message'] ?? 'unknown'));
                $success = __('reset_link_sent');
            }
        } else {
            // Don't reveal whether email exists (security best practice)
            $success = __('reset_link_sent');
        }
    }
    }
}

$pageTitle = __('forgot_password');
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="fs-1 mb-3"><span class="emoji-icon">📧</span></div>
                        <h1 class="h4 fw-bold"><?php echo __('forgot_password'); ?></h1>
                        <p class="text-muted small"><?php echo __('site_name'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small">
                            <span class="emoji-icon">⚠️</span> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success py-3 small">
                            <span class="emoji-icon">✅</span> <strong><?php echo $success; ?></strong>
                        </div>
                        <div class="text-center mt-4">
                            <p class="text-muted small mb-3"><?php echo __('check_email_instruction'); ?></p>
                            <a href="login.php" class="btn btn-primary px-4 shadow-sm">
                                <span class="emoji-icon">🔐</span> <?php echo __('login'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <form action="forgot_password.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold"><?php echo __('email'); ?></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><span class="emoji-icon">📧</span></span>
                                    <input type="email" name="email" class="form-control bg-light" required autofocus>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <?php echo __('enter_email_instruction'); ?>
                                </small>
                            </div>
                            <button type="submit" name="forgot_password" class="btn btn-primary w-100 py-2 fw-bold">
                                <?php echo __('send_reset_link'); ?>
                            </button>
                        </form>

                        <div class="text-center mt-4 border-top pt-3">
                            <span class="text-muted small"><?php echo __('remember_password'); ?></span>
                            <a href="login.php" class="small fw-bold text-decoration-none"><?php echo __('login'); ?></a>
                            <div class="mt-2">
                                <span class="text-muted small"><?php echo __('no_account'); ?></span>
                                <a href="register.php" class="small fw-bold text-decoration-none text-success"><?php echo __('register'); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
