<?php
require_once '../includes/config.php';

// إذا كان المستخدم مسجلاً بالفعل، يتم توجيهه للرئيسية
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = __('error_login');
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = __('error_login');
    } else {
        // Check login rate limiting
        $attempt_check = checkLoginAttempts($username);
        
        if ($attempt_check['locked']) {
            $error = $attempt_check['message'];
            logActivity("🔒 محاولة دخول من حساب مقفل", "🔒 Locked Account Login Attempt", "Username: $username");
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password, role, two_factor_enabled, organization_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Clear login attempts on successful login
                clearLoginAttempts($username);
                
                if ($user['two_factor_enabled']) {
                    session_regenerate_id(true);
                    $_SESSION['temp_2fa_user_id'] = $user['id'];
                    redirect('auth/verify_2fa.php');
                }

                session_regenerate_id(true);
                // نجاح تسجيل الدخول التقليدي (في حال عدم تفعيل التحقق الثنائي)
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['organization_id'] = $user['organization_id'];
                $_SESSION['2fa_verified'] = true;

                // جلب ID واسم الموظف الخاص به (إن وُجد) لجميع الرتب
                $stmtEmp = $pdo->prepare("SELECT id, full_name FROM employees WHERE user_id = ?");
                $stmtEmp->execute([$user['id']]);
                $emp = $stmtEmp->fetch();
                $_SESSION['employee_id'] = $emp ? $emp['id'] : null;
                $_SESSION['full_name'] = $emp && !empty($emp['full_name']) ? $emp['full_name'] : $user['username'];

                logActivity("🔐 تسجيل الدخول", "🔐 Login", "User logged in successfully", $user['organization_id']);
                redirect('index.php');
            } else {
                // Record failed login attempt
                recordFailedLogin($username);
                
                // لمحاولات الدخول الفاشلة، نحاول تسجيل النشاط بدون مؤسسة إذا كان اليوزر غير معروف
                logActivity("⚠️ محاولة دخول فاشلة", "⚠️ Failed Login Attempt", "Username: " . $username, null);
                $error = __('error_login');
            }
        }
    }
    }
}

$pageTitle = __('login');
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-4">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <div class="fs-1 mb-3"><span class="emoji-icon">🔐</span></div>
                        <h1 class="h4 fw-bold"><?php echo __('login'); ?></h1>
                        <p class="text-muted small"><?php echo __('site_name'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="username" class="form-label small fw-bold"><?php echo __('username'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><span class="emoji-icon">👤</span></span>
                                <input type="text" name="username" id="username" class="form-control bg-light border-start-0" required autofocus>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label small fw-bold"><?php echo __('password'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><span class="emoji-icon">🔒</span></span>
                                <input type="password" name="password" id="password" class="form-control bg-light border-start-0" required>
                            </div>
                            <div class="text-end mt-2">
                                <a href="forgot_password.php" class="btn btn-link btn-sm text-primary fw-bold text-decoration-none p-0" style="font-size: 0.95rem;"><span class="emoji-icon">📋</span> <?php echo __('forgot_password'); ?></a>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><?php echo __('login'); ?></button>
                    </form>

                    <div class="text-center mt-4 border-top pt-3">
                        <span class="text-muted small"><?php echo __('dont_have_account'); ?></span>
                        <a href="register.php" class="small fw-bold text-decoration-none"><?php echo __('register'); ?></a>
                        <div class="mt-2">
                            <span class="text-muted small"><?php echo __('want_new_org'); ?></span>
                            <a href="request_org.php" class="small fw-bold text-decoration-none text-success"><?php echo __('request_org'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
