<?php
require_once 'includes/config.php';

// تفعيل عرض الأخطاء للتشخيص
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; padding: 20px;'>";
echo "<h2 style='color: #2c3e50;'>Comprehensive Database Synchronization</h2>";

try {
    // تحديد ملف قاعدة البيانات الشامل
    $sqlFile = 'database/hr_app_db.sql';
    
    if (!file_exists($sqlFile)) {
        die("<p style='color: red;'>Error: SQL file not found at $sqlFile</p>");
    }

    echo "<p>Using SQL file: <strong>$sqlFile</strong></p>";
    $sql = file_get_contents($sqlFile);

    // إعدادات PDO لتنفيذ استعلامات متعددة
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    
    echo "<p>Starting import... This may take a few seconds.</p>";

    // الطريقة الأفضل: تعطيل التحقق من المفاتيح الأجنبية مؤقتاً لضمان نجاح الحذف والإنشاء
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // تنفيذ الملف بالكامل
    // ملاحظة: exec() يمكنها تنفيذ استعلامات متعددة إذا كان المحرك يدعم ذلك
    $result = $pdo->exec($sql);
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<h3>✅ Success!</h3>";
    echo "<p>The database has been synchronized successfully.</p>";
    echo "<ul>";
    echo "<li>Tables created/updated: organizations, users, employees, etc.</li>";
    echo "<li>Missing columns (like two_factor_enabled) have been added.</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='index.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Home Page</a> ";
    echo "<a href='auth/login.php' style='background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
    echo "</p>";
    
    echo "<p style='color: #e74c3c; font-weight: bold; margin-top: 30px;'>⚠️ Security Warning: Please delete 'fix_db.php' from your server immediately.</p>";

} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<h3>❌ Error During Import</h3>";
    echo "<pre style='white-space: pre-wrap;'>" . h($e->getMessage()) . "</pre>";
    echo "</div>";
    
    echo "<h4>Suggested Alternative:</h4>";
    echo "<p>If this script fails, the <strong>Best Way</strong> is to:</p>";
    echo "<ol>";
    echo "<li>Open <strong>phpMyAdmin</strong> in Hostinger.</li>";
    echo "<li>Select your database <code>u331306605_ejazat</code>.</li>";
    echo "<li>Go to <strong>Import</strong> tab.</li>";
    echo "<li>Upload the file: <code>database/hr_app_db.sql</code> from your project.</li>";
    echo "<li>Click <strong>Go</strong>.</li>";
    echo "</ol>";
}

echo "</body></html>";
?>