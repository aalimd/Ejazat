<?php
/**
 * إعدادات قاعدة البيانات والاتصال
 * تم تجهيز هذا الملف ليعمل على استضافة Hostinger
 */

// إعداد المنطقة الزمنية لمكة المكرمة
date_default_timezone_set('Asia/Riyadh');

// إعدادات قاعدة البيانات
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // الإعدادات المحلية (XAMPP)
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'hr_app_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', '/HR-App/');
} else {
    // إعدادات استضافة Hostinger (قم بتعديلها لتطابق بياناتك في Hostinger)
    define('DB_HOST', 'localhost'); // عادة ما يكون localhost في Hostinger
    define('DB_NAME', 'u123456789_hr_db'); // اسم قاعدة البيانات في Hostinger
    define('DB_USER', 'u123456789_hr_user'); // اسم المستخدم في Hostinger
    define('DB_PASS', 'your_password_here'); // كلمة مرور قاعدة البيانات في Hostinger
    define('BASE_URL', '/'); // عادة ما يكون الجذر في الاستضافة
}

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إعداد اللغة
require_once 'languages.php';
require_once 'TotpHelper.php';
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] == 'en') ? 'en' : 'ar';
}
$lang = $_SESSION['lang'] ?? 'ar';

// تعريف اسم الموقع ديناميكياً
$dynamic_site_name = ($lang == 'en') ? getSetting('site_name_en', 'HR Management System') : getSetting('site_name_ar', 'نظام إدارة الموظفين');
define('SITE_NAME', $dynamic_site_name);

function __($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}

function get_name($row) {
    global $lang;
    return $lang == 'en' ? ($row['name_en'] ?? $row['name_ar']) : ($row['name_ar'] ?? $row['name_en']);
}

function addNotification($user_id, $msg_ar, $msg_en) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message_ar, message_en) VALUES (?, ?, ?)");
    return $stmt->execute([$user_id, $msg_ar, $msg_en]);
}

function logActivity($action_ar, $action_en, $details = null) {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action_ar, action_en, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $action_ar, $action_en, $details, $ip, $ua]);
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
    die(__('db_error') . ": " . $e->getMessage());
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

// تحميل الإعدادات من قاعدة البيانات الخاصة بالجهة الحالية
$app_settings = [];
try {
    $org_id = $_SESSION['organization_id'] ?? 1;
    // إذا كان هناك اتصال بقاعدة البيانات
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE organization_id = ?");
        $stmt->execute([$org_id]);
        while ($row = $stmt->fetch()) {
            $app_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // في حال عدم وجود الجدول بعد
}

function getSetting($key, $default = null, $org_id = null) {
    global $app_settings, $pdo;
    if ($org_id === null && isset($_SESSION['organization_id'])) {
        $org_id = $_SESSION['organization_id'];
    }
    if ($org_id !== null && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE organization_id = ? AND setting_key = ?");
            $stmt->execute([$org_id, $key]);
            $row = $stmt->fetch();
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    return $app_settings[$key] ?? $default;
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
    $is_acting_as_admin = ($user_role === 'super_admin' && !empty($_SESSION['organization_id']));
    
    if (is_array($roles)) {
        if ($is_acting_as_admin && in_array('admin', $roles)) return true;
        return in_array($user_role, $roles);
    }
    
    if ($is_acting_as_admin && $roles === 'admin') return true;
    return $user_role === $roles;
}

function checkAuth($roles = []) {
    if (!isLoggedIn()) {
        redirect('auth/login.php');
    }
    if (!empty($roles) && !hasRole($roles)) {
        die(__('access_denied'));
    }
}

// الحماية من XSS
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// حساب عدد أيام الإجازة الفعلي باستثناء الإجازات الأسبوعية والعطلات الرسمية للمؤسسة
function calculateLeaveDays($start_date, $end_date, $org_id) {
    global $pdo;
    
    // 1. جلب إعدادات نهاية الأسبوع
    $weekend_setting = getSetting('weekend_days', 'Friday,Saturday', $org_id);
    $weekends = array_map('trim', explode(',', strtolower($weekend_setting)));

    // 2. جلب العطلات الرسمية لهذه المؤسسة
    $stmt = $pdo->prepare("SELECT start_date, end_date FROM holidays WHERE organization_id = ?");
    $stmt->execute([$org_id]);
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
?>
