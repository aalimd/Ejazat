<?php
require_once '../includes/config.php';

// تسجيل النشاط قبل إنهاء الجلسة
logActivity("🚪 تسجيل الخروج", "🚪 Logout");

// إنهاء الجلسة بشكل كامل
$_SESSION = [];
session_destroy();

// إلغاء صلاحية كوكي الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// التوجه لصفحة تسجيل الدخول
header("Location: " . BASE_URL . "auth/login.php");
exit();
?>
