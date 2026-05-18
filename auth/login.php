<?php
require_once '../includes/config.php';

// إذا كان المستخدم مسجلاً بالفعل، يتم توجيهه للرئيسية
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = __('error_login');
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role, two_factor_enabled, organization_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['two_factor_enabled']) {
                // توجيه لصفحة التحقق الثنائي
                $_SESSION['temp_2fa_user_id'] = $user['id'];
                redirect('auth/verify_2fa.php');
            }

            // نجاح تسجيل الدخول التقليدي (في حال عدم تفعيل التحقق الثنائي)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['organization_id'] = $user['organization_id'];

            // جلب ID واسم الموظف الخاص به (إن وُجد) لجميع الرتب
            $stmtEmp = $pdo->prepare("SELECT id, full_name FROM employees WHERE user_id = ?");
            $stmtEmp->execute([$user['id']]);
            $emp = $stmtEmp->fetch();
            $_SESSION['employee_id'] = $emp ? $emp['id'] : null;
            $_SESSION['full_name'] = $emp && !empty($emp['full_name']) ? $emp['full_name'] : $user['username'];

            logActivity("🔐 تسجيل الدخول", "🔐 Login", "User logged in successfully");
            redirect('index.php');
        } else {
            logActivity("⚠️ محاولة دخول فاشلة", "⚠️ Failed Login Attempt", "Username: " . $username);
            $error = __('error_login');
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
                        <div class="fs-1 mb-3">🔐</div>
                        <h4 class="fw-bold"><?php echo __('login'); ?></h4>
                        <p class="text-muted small"><?php echo __('site_name'); ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label small fw-bold"><?php echo __('username'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light <?php echo __('dir') == 'rtl' ? 'border-end-0' : 'border-start-0'; ?>">👤</span>
                                <input type="text" name="username" id="username" class="form-control bg-light <?php echo __('dir') == 'rtl' ? 'border-start-0' : 'border-end-0'; ?>" required autofocus>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label small fw-bold"><?php echo __('password'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light <?php echo __('dir') == 'rtl' ? 'border-end-0' : 'border-start-0'; ?>">🔒</span>
                                <input type="password" name="password" id="password" class="form-control bg-light <?php echo __('dir') == 'rtl' ? 'border-start-0' : 'border-end-0'; ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><?php echo __('login'); ?></button>
                    </form>

                    <div class="text-center mt-4 border-top pt-3">
                        <span class="text-muted small"><?php echo __('dont_have_account'); ?></span>
                        <a href="register.php" class="small fw-bold text-decoration-none"><?php echo __('register'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
