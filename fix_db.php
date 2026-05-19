<?php
require_once 'includes/config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; padding: 20px;'>";
echo "<h2 style='color: #2c3e50;'>Database Clean Setup</h2>";

try {
    $sqlFile = 'database/clean_db.sql';
    
    if (!file_exists($sqlFile)) {
        die("<p style='color: red;'>Error: SQL file not found at $sqlFile</p>");
    }

    echo "<p>Using Clean SQL file: <strong>$sqlFile</strong></p>";
    $sql = file_get_contents($sqlFile);

    // تعطيل القيود وتنفيذ المسح والإنشاء
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // تقسيم الملف إلى استعلامات منفصلة لضمان التنفيذ الصحيح
    // نستخدم ; كفاصل مع مراعاة الأسطر الجديدة
    $queries = preg_split("/;(?:\s*[\r\n]+|$)/", $sql);
    
    $count = 0;
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
            $count++;
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<h3>✅ Success! Clean Setup Complete.</h3>";
    echo "<p>Database has been reset with clean tables.</p>";
    echo "<h4>Default Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>Username:</strong> superadmin</li>";
    echo "<li><strong>Password:</strong> admin123</li>";
    echo "</ul>";
    echo "<p style='color: #856404;'>Please change the password immediately after login.</p>";
    echo "</div>";
    
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='auth/login.php' style='background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
    echo "</p>";
    
    echo "<p style='color: #e74c3c; font-weight: bold; margin-top: 30px;'>⚠️ Security Warning: Please delete 'fix_db.php' and 'database/clean_db.sql' from your server now.</p>";

} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<h3>❌ Error During Setup</h3>";
    echo "<pre style='white-space: pre-wrap;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
?>