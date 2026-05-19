<?php
require_once 'includes/config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; padding: 20px;'>";
echo "<h2 style='color: #2c3e50;'>Professional Multi-Tenant Database Reset</h2>";

try {
    $sqlFile = 'database/clean_db.sql';
    
    if (!file_exists($sqlFile)) {
        die("<p style='color: red;'>Error: SQL file not found at $sqlFile</p>");
    }

    echo "<p>Using Strict Multi-Tenant Schema: <strong>$sqlFile</strong></p>";
    $sql = file_get_contents($sqlFile);

    // تعطيل القيود وتنفيذ المسح والإنشاء
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // تقسيم الملف إلى استعلامات منفصلة
    $queries = preg_split("/;(?:\s*[\r\n]+|$)/", $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    // إنشاء مستخدم superadmin النهائي بكلمة مرور مشفرة برمجياً لضمان الدقة
    $username = 'superadmin';
    $password = 'admin123';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $email = 'admin@ejazat.com';
    $role = 'super_admin';

    // مسح أي نسخة سابقة
    $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$username]);

    // الإدراج النهائي (بدون organization_id للـ superadmin ليكون مستقلاً عن الجهات)
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, organization_id) VALUES (?, ?, ?, ?, NULL)");
    $stmt->execute([$username, $hashedPassword, $email, $role]);

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<h3>✅ System Reset Successful!</h3>";
    echo "<p>Strict isolation mode enabled. All tables are ready.</p>";
    echo "<h4>Super Admin Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>Username:</strong> superadmin</li>";
    echo "<li><strong>Password:</strong> admin123</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='auth/login.php' style='background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
    echo "</p>";
    
    echo "<p style='color: #e74c3c; font-weight: bold; margin-top: 30px;'>⚠️ Security Warning: Please delete 'fix_db.php' and 'database/clean_db.sql' now.</p>";

} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<h3>❌ Error During Reset</h3>";
    echo "<pre style='white-space: pre-wrap;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
?>