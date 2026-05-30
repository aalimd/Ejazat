<?php
require_once '../includes/config.php';

$error = '';
$success = '';
$email_to_verify = '';

// If already logged in, redirect
if (isLoggedIn()) {
    redirect('index.php');
}

// Handle email verification via token
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    $result = verifyEmailToken($token);
    
    if ($result['success']) {
        $success = __('email_verified_success');
        $user_id = $result['user_id'];
        
        // Log this action
        logActivity("✅ تأكيد البريد الإلكتروني", "✅ Email Verified", "User ID: $user_id");
    } else {
        $error = $result['message'] ?? 'Invalid or expired token.';
    }
}

// Handle resend verification email request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('enter_valid_email');
    } else {
        // Find user by email
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND id NOT IN (SELECT user_id FROM email_verifications WHERE verified_at IS NOT NULL)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = __('email_not_found_or_verified');
        } else {
            // Send verification email again
            $res = sendVerificationEmail($user['id'], $email, $user['username']);
            
            if ($res['success']) {
                $success = __('verification_email_sent') . ' ' . h($email) . __('check_inbox');
                $email_to_verify = $email;
                logActivity("🔄 إعادة إرسال بريد التحقق", "🔄 Resend Verification Email", "Email: $email");
            } else {
                $error = __('verification_failed') . ': ' . ($res['message'] ?? 'Unknown error');
            }
        }
    }
}

$pageTitle = __('email_verification');
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <?php if ($success): ?>
                            <div class="fs-1 mb-3">✅</div>
                        <?php else: ?>
                            <div class="fs-1 mb-3">✉️</div>
                        <?php endif; ?>
                        <h4 class="fw-bold"><?php echo __('email_verification'); ?></h4>
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
                            <p class="mb-3 text-muted small"><?php echo __('login_now'); ?></p>
                            <a href="login.php" class="btn btn-primary px-4 shadow-sm">
                                🔐 <?php echo __('login'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Resend verification email form -->
                        <form action="verify_email.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold"><?php echo __('email'); ?></label>
                                <input type="email" name="email" class="form-control bg-light" 
                                       value="<?php echo h($email_to_verify); ?>" required>
                                <small class="text-muted d-block mt-1">
                                    <?php echo __('enter_email_for_verification'); ?>
                                </small>
                            </div>
                            <button type="submit" name="resend_verification" class="btn btn-primary w-100 py-2 fw-bold">
                                📧 <?php echo __('send_verification'); ?>
                            </button>
                        </form>

                        <div class="text-center mt-4 border-top pt-3">
                            <p class="text-muted small mb-2"><?php echo __('didnt_receive_email'); ?></p>
                            <a href="register.php" class="small fw-bold text-decoration-none"><?php echo __('register'); ?></a>
                            <span class="text-muted small"> | </span>
                            <a href="login.php" class="small fw-bold text-decoration-none"><?php echo __('login'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
