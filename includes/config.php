<?php
/**
 * إعدادات قاعدة البيانات والاتصال
 * تم تجهيز هذا الملف ليعمل على استضافة Hostinger بنظام Multi-Tenant احترافي
 */

// إعداد المنطقة الزمنية لمكة المكرمة
date_default_timezone_set('Asia/Riyadh');

// Load environment variables from .env (outside web root in production)
require_once __DIR__ . '/dotenv.php';
loadDotEnv();

// قراءة إعدادات قاعدة البيانات من متغيرات البيئة مع خيار احتياطي
$is_local_environment = !isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1';
ini_set('display_errors', $is_local_environment ? '1' : '0');
ini_set('display_startup_errors', $is_local_environment ? '1' : '0');
error_reporting(E_ALL);

if ($is_local_environment) {
    define('DB_HOST', env('DB_HOST_LOCAL', '127.0.0.1'));
    define('DB_NAME', env('DB_NAME_LOCAL', 'hr_app_db'));
    define('DB_USER', env('DB_USER_LOCAL', 'root'));
    define('DB_PASS', env('DB_PASS_LOCAL', ''));
    define('BASE_URL', env('BASE_URL_LOCAL', '/HR-App/'));
} else {
    define('DB_HOST', env('DB_HOST_PROD', 'localhost'));
    define('DB_NAME', env('DB_NAME_PROD', 'u331306605_ejazat'));
    define('DB_USER', env('DB_USER_PROD', 'u331306605_ejazatuser'));
    define('DB_PASS', env('DB_PASS_PROD', 'Az@99668'));
    define('BASE_URL', env('BASE_URL_PROD', '/'));
}

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إعداد اللغة
require_once 'languages.php';
require_once 'TotpHelper.php';
require_once 'EmailHelper.php';
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] == 'en') ? 'en' : 'ar';
}
$lang = $_SESSION['lang'] ?? 'ar';

// تعريف معرف المؤسسة الحالي بشكل صارم
// للـ Super Admin، يمكن أن يكون null (إدارة النظام) أو قيمة محددة (إدارة مؤسسة)
// للـ Admin والموظفين، يجب أن يكون قيمة محددة دائماً
$current_org_id = $_SESSION['organization_id'] ?? null;
define('CURRENT_ORG_ID', $current_org_id);

// تعريف الدوال المساعدة قبل استخدامها
function __($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}

// تحميل الإعدادات من قاعدة البيانات الخاصة بالجهة الحالية
$app_settings = [];

function getSetting($key, $default = null, $org_id = null) {
    global $app_settings, $pdo;
    $target_org = $org_id ?? CURRENT_ORG_ID;
    
    if ($target_org !== null && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE organization_id = ? AND setting_key = ?");
            $stmt->execute([$target_org, $key]);
            $row = $stmt->fetch();
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    return $app_settings[$key] ?? $default;
}

// تعريف اسم الموقع ديناميكياً بناءً على المؤسسة الحالية
$dynamic_site_name = ($lang == 'en') ? getSetting('site_name_en', 'HR Management System') : getSetting('site_name_ar', 'نظام إدارة الموظفين');
define('SITE_NAME', $dynamic_site_name);

function get_name($row) {
    global $lang;
    return $lang == 'en' ? ($row['name_en'] ?? $row['name_ar']) : ($row['name_ar'] ?? $row['name_en']);
}

function addNotification($user_id, $msg_ar, $msg_en, $org_id = null) {
    global $pdo;
    $target_org = $org_id ?? CURRENT_ORG_ID;
    if ($target_org === null) return false;

    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, organization_id, message_ar, message_en) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $target_org, $msg_ar, $msg_en]);
}

function logActivity($action_ar, $action_en, $details = null, $org_id = null) {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? null;
    $target_org = $org_id ?? CURRENT_ORG_ID;
    
    // التحقق من وجود المستخدم في قاعدة البيانات قبل تسجيل النشاط لتجنب خطأ Foreign Key
    if ($user_id !== null) {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmtCheck->execute([$user_id]);
        if (!$stmtCheck->fetch()) {
            $user_id = null;
        }
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Super Admin without org: route to system organization (ID: 1) to preserve audit trail
    if ($target_org === null && ($_SESSION['role'] ?? '') === 'super_admin') {
        $target_org = 1;
    }

    if ($target_org === null) return false;

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, organization_id, action_ar, action_en, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $target_org, $action_ar, $action_en, $details, $ip, $ua]);
    } catch (Exception $e) {
        return false;
    }
}

function generateSystemId() {
    return 'HR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateOperationCode($prefix = 'OP') {
    return $prefix . '-' . date('His') . '-' . strtoupper(substr(uniqid(), -4));
}

// الاتصال بقاعدة البيانات باستخدام PDO
try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die($is_local_environment ? "Database Error: " . $e->getMessage() : "Database connection error.");
}

// معالجة تغيير المؤسسة للمدير العام (Super Admin)
if (isset($_GET['switch_org']) && isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    $sw_id = intval($_GET['switch_org']);
    if ($sw_id === 0) {
        $_SESSION['organization_id'] = null;
    } else {
        $_SESSION['organization_id'] = $sw_id;
    }
    // إعادة التوجيه لتجنب بقاء معامل الـ GET في الرابط
    $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $clean_url);
    exit();
}

// استكمال تحميل الإعدادات بعد إنشاء اتصال الـ PDO
try {
    if (CURRENT_ORG_ID !== null && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE organization_id = ?");
        $stmt->execute([CURRENT_ORG_ID]);
        while ($row = $stmt->fetch()) {
            $app_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // في حال عدم وجود الجدول بعد
}

// دوال مساعدة عامة
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    
    $user_role = $_SESSION['role'];
    
    // Super Admin has access to EVERYTHING
    if ($user_role === 'super_admin') {
        return true;
    }
    
    // For non-super_admin roles, check if user has required role
    if (is_array($roles)) {
        return in_array($user_role, $roles);
    }
    
    return $user_role === $roles;
}

/**
 * Strict Super Admin gate — dies with 403 if not super_admin.
 * Use on all superadmin/* pages as the sole auth gate.
 */
function checkSuperAdmin() {
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'super_admin') {
        http_response_code(403);
        die(__('access_denied'));
    }
}

function checkAuth($roles = []) {
    if (!isLoggedIn()) {
        redirect('auth/login.php');
    }
    
    // إذا كان المستخدم ليس Super Admin، يجب أن يكون مرتبطاً بمؤسسة دائماً
    if ($_SESSION['role'] !== 'super_admin' && empty($_SESSION['organization_id'])) {
        session_destroy();
        redirect('auth/login.php?error=invalid_org');
    }

    // Super admin needs to select an organization before accessing org-specific pages
    $is_org_specific = false;
    if (!empty($roles)) {
        $roles_array = is_array($roles) ? $roles : [$roles];
        $is_org_specific = !in_array('super_admin', $roles_array);
    }
    if ($_SESSION['role'] === 'super_admin' && $is_org_specific && empty(CURRENT_ORG_ID)) {
        $_SESSION['org_required_redirect'] = $_SERVER['REQUEST_URI'];
        redirect('superadmin/dashboard.php?error=select_org_first');
    }

    if (!empty($roles) && !hasRole($roles)) {
        die(__('access_denied'));
    }
}

// الحماية من XSS
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// حساب عدد أيام الإجازة الفعلي باستثناء الإجازات الأسبوعية والعطلات الرسمية للمؤسسة
function calculateLeaveDays($start_date, $end_date, $org_id = null) {
    global $pdo;
    $target_org = $org_id ?? CURRENT_ORG_ID;
    if ($target_org === null) return 0;
    
    // 1. جلب إعدادات نهاية الأسبوع
    $weekend_setting = getSetting('weekend_days', 'Friday,Saturday', $target_org);
    $weekends = array_map('trim', explode(',', strtolower($weekend_setting)));

    // 2. جلب العطلات الرسمية لهذه المؤسسة
    $stmt = $pdo->prepare("SELECT start_date, end_date FROM holidays WHERE organization_id = ?");
    $stmt->execute([$target_org]);
    $holidays = $stmt->fetchAll();

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // لتضمين اليوم الأخير

    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval ,$end);

    $days_count = 0;
    foreach($daterange as $date){
        $day_name = strtolower($date->format('l')); // 'friday', 'saturday', ...
        
        // استبعاد عطلات نهاية الأسبوع
        if (in_array($day_name, $weekends)) {
            continue;
        }

        // استبعاد العطلات الرسمية المحددة في النظام
        $curr_date_str = $date->format('Y-m-d');
        $is_holiday = false;
        foreach ($holidays as $h) {
            if ($curr_date_str >= $h['start_date'] && $curr_date_str <= $h['end_date']) {
                $is_holiday = true;
                break;
            }
        }
        if ($is_holiday) {
            continue;
        }

        $days_count++;
    }

    return $days_count;
}

// ========================================
// EMAIL & AUTHENTICATION HELPER FUNCTIONS
// ========================================

/**
 * Generate email verification token and send verification email
 */
function sendVerificationEmail($user_id, $email, $username = '') {
    global $pdo;
    
    $verification_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Store verification token
    try {
        $stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $verification_token, $expires_at]);
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to generate verification token'];
    }
    
    // Generate verification link
    $verification_link = BASE_URL . 'auth/verify_email.php?token=' . $verification_token;
    
    // Send email
    try {
        $email_helper = new EmailHelper($pdo, CURRENT_ORG_ID);
        $result = $email_helper->sendVerificationEmail($email, $verification_link, getSetting('site_name_ar', 'HR System'));
        return $result;
    } catch (Exception $e) {
        error_log('Error sending verification email: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send verification email'];
    }
}

/**
 * Verify email token
 */
function verifyEmailToken($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT user_id FROM email_verifications WHERE token = ? AND verified_at IS NULL AND expires_at > NOW()");
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return ['success' => false, 'message' => 'Invalid or expired token'];
    }
    
    // Mark as verified
    $stmt = $pdo->prepare("UPDATE email_verifications SET verified_at = NOW() WHERE token = ?");
    $stmt->execute([$token]);
    
    // Update user email verified status if needed
    $user_id = $result['user_id'];
    
    return ['success' => true, 'user_id' => $user_id, 'message' => 'Email verified successfully'];
}

/**
 * Create password reset token
 */
function createPasswordResetToken($user_id) {
    global $pdo;
    
    $reset_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $reset_token, $expires_at]);
        return $reset_token;
    } catch (PDOException $e) {
        error_log('Error creating password reset token: ' . $e->getMessage());
        return null;
    }
}

/**
 * Verify password reset token
 */
function verifyPasswordResetToken($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW()");
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return null;
    }
    
    return $result['user_id'];
}

/**
 * Mark password reset token as used
 */
function markPasswordResetTokenAsUsed($token) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
        return $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log('Error marking password reset token as used: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmailToUser($email, $username = '', $reset_token = '') {
    global $pdo;
    
    if (empty($reset_token)) {
        // Get user to create token
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $reset_token = createPasswordResetToken($user['id']);
        if (!$reset_token) {
            return ['success' => false, 'message' => 'Failed to create reset token'];
        }
    }
    
    // Generate reset link
    $reset_link = BASE_URL . 'auth/reset_password.php?token=' . $reset_token;
    
    // Send email
    try {
        $email_helper = new EmailHelper($pdo, CURRENT_ORG_ID);
        $result = $email_helper->sendPasswordResetEmail($email, $reset_link, $username, getSetting('site_name_ar', 'HR System'));
        return $result;
    } catch (Exception $e) {
        error_log('Error sending password reset email: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send password reset email'];
    }
}

/**
 * Send welcome email to new user
 */
function sendWelcomeEmailToUser($email, $username, $full_name = '', $org_id = null) {
    global $pdo;
    
    try {
        $email_helper = new EmailHelper($pdo, $org_id ?? CURRENT_ORG_ID);
        $site_name = getSetting('site_name_ar', 'HR System', $org_id);
        $result = $email_helper->sendWelcomeEmail($email, $username, $full_name, $site_name, BASE_URL . 'auth/login.php');
        return $result;
    } catch (Exception $e) {
        error_log('Error sending welcome email: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send welcome email'];
    }
}

/**
 * Check if login attempts exceed limit
 */
function checkLoginAttempts($username) {
    global $pdo;
    
    $max_attempts = 5;
    $lockout_minutes = 15;
    
    try {
        $stmt = $pdo->prepare("SELECT failed_attempts, last_attempt FROM login_attempts WHERE username = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$username, $lockout_minutes]);
        $attempt = $stmt->fetch();
        
        if ($attempt && $attempt['failed_attempts'] >= $max_attempts) {
            $remaining_time = date('Y-m-d H:i:s', strtotime($attempt['last_attempt']) + ($lockout_minutes * 60));
            return [
                'locked' => true,
                'message' => "Account locked. Try again after {$remaining_time}",
                'attempts' => $attempt['failed_attempts']
            ];
        }
        
        return ['locked' => false, 'attempts' => $attempt['failed_attempts'] ?? 0];
    } catch (PDOException $e) {
        error_log('Error checking login attempts: ' . $e->getMessage());
        return ['locked' => false, 'attempts' => 0];
    }
}

/**
 * Record failed login attempt
 */
function recordFailedLogin($username) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, failed_attempts, last_attempt) VALUES (?, 1, NOW()) 
                              ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1, last_attempt = NOW()");
        $stmt->execute([$username]);
    } catch (PDOException $e) {
        error_log('Error recording failed login: ' . $e->getMessage());
    }
}

/**
 * Clear login attempts after successful login
 */
function clearLoginAttempts($username) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
    } catch (PDOException $e) {
        error_log('Error clearing login attempts: ' . $e->getMessage());
    }
}

/**
 * Generate random 12-character invitation code
 * Format: 3 letters + 3 numbers + 3 letters + 3 numbers (e.g., ABC123DEF456)
 */
function generateRandomInvitationCode() {
    $code = '';
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    // Pattern: 3 letters, 3 numbers, 3 letters, 3 numbers
    for ($i = 0; $i < 3; $i++) $code .= $letters[rand(0, 25)];
    for ($i = 0; $i < 3; $i++) $code .= rand(0, 9);
    for ($i = 0; $i < 3; $i++) $code .= $letters[rand(0, 25)];
    for ($i = 0; $i < 3; $i++) $code .= rand(0, 9);
    
    return $code;
}

/**
 * Get organization by invitation code
 */
function getOrganizationByCode($code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT o.id, o.name_ar, o.name_en, o.is_public, o.requires_invitation_code
            FROM organizations o
            JOIN organization_invitation_codes oic ON o.id = oic.organization_id
            WHERE oic.code = ? AND oic.is_active = 1 AND o.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting organization by code: ' . $e->getMessage());
        return null;
    }
}

/**
 * Validate organization invitation code
 * Returns: ['success' => bool, 'org_id' => int, 'org_name' => string, 'message' => string]
 */
function validateInvitationCode($code) {
    global $pdo;
    
    $code = trim(strtoupper($code));
    
    if (empty($code) || strlen($code) < 12) {
        return ['success' => false, 'org_id' => null, 'org_name' => null, 'message' => 'Invalid code format'];
    }
    
    // Check rate limiting first
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit = checkCodeRateLimit($ip_address);
    if (!$rate_limit['allowed']) {
        return ['success' => false, 'org_id' => null, 'org_name' => null, 'message' => $rate_limit['message']];
    }
    
    try {
        $org = getOrganizationByCode($code);
        
        if (!$org) {
            logCodeAttempt(null, $code, $ip_address, false);
            return ['success' => false, 'org_id' => null, 'org_name' => null, 'message' => 'Code not found or organization inactive'];
        }
        
        logCodeAttempt($org['id'], $code, $ip_address, true);
        $org_name = ($lang = $_SESSION['lang'] ?? 'ar') == 'en' ? $org['name_en'] : $org['name_ar'];
        
        return [
            'success' => true,
            'org_id' => (int)$org['id'],
            'org_name' => $org_name,
            'message' => 'Code validated successfully'
        ];
    } catch (PDOException $e) {
        error_log('Error validating invitation code: ' . $e->getMessage());
        return ['success' => false, 'org_id' => null, 'org_name' => null, 'message' => 'Database error'];
    }
}

/**
 * Check code attempt rate limiting
 * Max 5 failed attempts per IP per hour
 */
function checkCodeRateLimit($ip_address) {
    global $pdo;
    
    try {
        // Check failed attempts in last hour
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as failed_count FROM organization_code_attempts
            WHERE ip_address = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip_address]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['failed_count'] >= 5) {
            return [
                'allowed' => false,
                'message' => 'Too many failed attempts. Please try again in 1 hour.',
                'attempts_remaining' => 0
            ];
        }
        
        return [
            'allowed' => true,
            'message' => 'OK',
            'attempts_remaining' => 5 - $result['failed_count']
        ];
    } catch (PDOException $e) {
        error_log('Error checking code rate limit: ' . $e->getMessage());
        return ['allowed' => true, 'message' => 'OK', 'attempts_remaining' => 5];
    }
}

/**
 * Log invitation code attempt (for audit trail and rate limiting)
 */
function logCodeAttempt($org_id, $code_entered, $ip_address, $success = false) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO organization_code_attempts (organization_id, code_entered, ip_address, success, attempt_type)
            VALUES (?, ?, ?, ?, 'registration')
        ");
        $stmt->execute([$org_id, $code_entered, $ip_address, $success ? 1 : 0]);
    } catch (PDOException $e) {
        error_log('Error logging code attempt: ' . $e->getMessage());
    }
}

/**
 * Regenerate organization invitation code (Super Admin only)
 * Returns: ['success' => bool, 'new_code' => string, 'old_code' => string, 'message' => string]
 */
function regenerateOrganizationCode($org_id, $user_id) {
    global $pdo;
    
    try {
        // Verify super-admin privilege
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['role'] !== 'super_admin') {
            return ['success' => false, 'new_code' => null, 'old_code' => null, 'message' => 'Unauthorized'];
        }
        
        // Get current code
        $stmt = $pdo->prepare("SELECT code FROM organization_invitation_codes WHERE organization_id = ? AND is_active = 1");
        $stmt->execute([$org_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $old_code = $current ? $current['code'] : null;
        
        // Generate new code
        $new_code = generateRandomInvitationCode();
        
        // Check if code already exists (rare but possible)
        $max_attempts = 5;
        while ($max_attempts > 0) {
            $stmt = $pdo->prepare("SELECT id FROM organization_invitation_codes WHERE code = ? LIMIT 1");
            $stmt->execute([$new_code]);
            if (!$stmt->fetch()) break;
            $new_code = generateRandomInvitationCode();
            $max_attempts--;
        }
        
        if ($max_attempts === 0) {
            return ['success' => false, 'new_code' => null, 'old_code' => null, 'message' => 'Failed to generate unique code'];
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Deactivate old code
        if ($old_code) {
            $stmt = $pdo->prepare("UPDATE organization_invitation_codes SET is_active = 0, previous_code = code WHERE organization_id = ? AND code = ?");
            $stmt->execute([$org_id, $old_code]);
        }
        
        // Insert new code
        $stmt = $pdo->prepare("
            INSERT INTO organization_invitation_codes (organization_id, code, is_active, regenerated_by_user_id, code_regenerated_at)
            VALUES (?, ?, 1, ?, NOW())
        ");
        $stmt->execute([$org_id, $new_code, $user_id]);
        
        // Log activity
        logActivity(
            "🔄 إعادة إنشاء رمز المؤسسة",
            "🔄 Regenerated Organization Code",
            "Organization ID: {$org_id}, Old Code: {$old_code}, New Code: {$new_code}"
        );
        
        $pdo->commit();
        
        return [
            'success' => true,
            'new_code' => $new_code,
            'old_code' => $old_code,
            'message' => 'Code regenerated successfully'
        ];
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log('Error regenerating organization code: ' . $e->getMessage());
        return ['success' => false, 'new_code' => null, 'old_code' => null, 'message' => 'Database error'];
    }
}

/**
 * Get organization invitation code for admin panel
 */
function getOrganizationCode($org_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT code, is_active, code_regenerated_at, previous_code FROM organization_invitation_codes
            WHERE organization_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$org_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting organization code: ' . $e->getMessage());
        return null;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ========================================
// MAINTENANCE MODE
// ========================================
$maintenance_page = basename($_SERVER['PHP_SELF']);
$maintenance_dir = basename(dirname($_SERVER['PHP_SELF']));
$auth_pages = ['login.php', 'forgot_password.php', 'reset_password.php', 'logout.php', 'verify_email.php', 'verify_2fa.php', 'register.php', 'request_org.php'];
$is_auth_page = $maintenance_dir === 'auth' && in_array($maintenance_page, $auth_pages);

if (!$is_auth_page) {
    try {
        $mm_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $mm_row = $mm_stmt->fetch();
        if ($mm_row && $mm_row['setting_value'] === '1') {
            $user_role = $_SESSION['role'] ?? '';
            if ($user_role !== 'super_admin') {
                $maintenance_title = __('maintenance_mode');
                $maintenance_msg = __('maintenance_mode_msg');
                http_response_code(503);
                ?><!DOCTYPE html><html lang="<?php echo __('lang_code'); ?>" dir="<?php echo __('dir'); ?>" data-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title><?php echo h($maintenance_title); ?> — <?php echo h(SITE_NAME); ?></title><style>:root{--bg:#0f172a;--card:#1e293b;--text:#f8fafc;--muted:#94a3b8}body{font-family:<?php echo __('dir') === 'rtl' ? "'Cairo',sans-serif" : "'Inter',sans-serif"; ?>;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:var(--bg);color:var(--text);text-align:center;padding:1rem}.mm-card{max-width:480px;padding:2rem}.mm-icon{font-size:3rem;margin-bottom:1rem}h1{font-size:1.5rem;margin-bottom:0.75rem}p{color:var(--muted);line-height:1.6;font-size:0.9rem}</style><script>const t=localStorage.getItem('theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');document.documentElement.style.setProperty('--bg','#f4f7fa');document.documentElement.style.setProperty('--card','#ffffff');document.documentElement.style.setProperty('--text','#2b3440');document.documentElement.style.setProperty('--muted','#6c757d')}</script></head><body><div class="mm-card"><div class="mm-icon"><i class="bi bi-gear"></i></div><h1><?php echo h($maintenance_title); ?></h1><p><?php echo h($maintenance_msg); ?></p></div></body></html><?php
                exit();
            }
        }
    } catch (Exception $e) {
        // table might not exist yet; silently ignore
    }
}
?>
