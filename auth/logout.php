<?php
require_once '../includes/config.php';

// تسجيل النشاط قبل إنهاء الجلسة
logActivity("🚪 تسجيل الخروج", "🚪 Logout");

// إنهاء الجلسة
$_SESSION = [];
session_destroy();

// التوجه لصفحة تسجيل الدخول
header("Location: " . BASE_URL . "auth/login.php");
exit();
?>
