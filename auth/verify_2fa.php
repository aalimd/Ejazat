<?php
require_once '../includes/config.php';

// If user is already logged in officially, redirect
if (isLoggedIn()) {
    redirect('index.php');
}

// Ensure there is a temporary user ID set from login
if (!isset($_SESSION['temp_2fa_user_id'])) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['temp_2fa_user_id'];
$error = '';

// Fetch the user's secret
$stmt = $pdo->prepare("SELECT id, username, password, role, two_factor_secret, organization_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || empty($user['two_factor_secret'])) {
    // Safety check: if no secret exists, clear temp and send back
    unset($_SESSION['temp_2fa_user_id']);
    redirect('auth/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error = __('fill_fields_error');
    } else {
        if (TotpHelper::verifyCode($user['two_factor_secret'], $code)) {
            // Success: log the user in officially
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['organization_id'] = $user['organization_id'];

            // Fetch employee info if available
            $stmtEmp = $pdo->prepare("SELECT id, full_name FROM employees WHERE user_id = ?");
            $stmtEmp->execute([$user['id']]);
            $emp = $stmtEmp->fetch();
            $_SESSION['employee_id'] = $emp ? $emp['id'] : null;
            $_SESSION['full_name'] = $emp && !empty($emp['full_name']) ? $emp['full_name'] : $user['username'];

            // Clear temporary variable
            unset($_SESSION['temp_2fa_user_id']);

            logActivity("🔐 تسجيل الدخول (ثنائي)", "🔐 Login (2FA)", "User logged in successfully via 2FA");
            redirect('index.php');
        } else {
            $error = __('2fa_invalid_code');
            logActivity("⚠️ فشل التحقق الثنائي", "⚠️ Failed 2FA Attempt", "Username: " . $user['username']);
        }
    }
}

$pageTitle = __('2fa_title');
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="fs-1 mb-3">🛡️</div>
                        <h4 class="fw-bold"><?php echo __('2fa_title'); ?></h4>
                        <p class="text-muted small"><?php echo __('2fa_active_desc'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small text-center shadow-sm border-0 mb-4"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="verify_2fa.php" method="POST" autocomplete="off">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-center d-block mb-3"><?php echo __('2fa_code_label'); ?></label>
                            <input type="text" name="code" class="form-control text-center fs-3 letter-spacing-lg fw-bold" placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus autocomplete="one-time-code">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm"><?php echo __('submit'); ?></button>
                    </form>

                    <div class="text-center mt-4 border-top pt-3">
                        <a href="logout.php" class="small text-danger text-decoration-none fw-bold">↩️ <?php echo __('cancel'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.letter-spacing-lg {
    letter-spacing: 0.5rem;
}
</style>

<?php include '../includes/footer.php'; ?>
