<?php
require_once 'includes/config.php';

// تعطيل عرض الأخطاء للمستخدم العادي وتفعيلها للتشخيص هنا
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Starting Comprehensive Database Fix...</h2>";

try {
    // الملف الصحيح والشامل لقاعدة البيانات
    $sqlFile = 'database/hr_app_db.sql'; 
    if (!file_exists($sqlFile)) {
        // محاولة مسار بديل إذا لم ينجح الأول
        $sqlFile = 'sql/database.sql';
    }

    if (!file_exists($sqlFile)) {
        die("Error: SQL file not found in database/hr_app_db.sql or sql/database.sql");
    }

    echo "Using SQL file: " . $sqlFile . "<br>";
    $sql = file_get_contents($sqlFile);

    // تقسيم الملف إلى استعلامات منفصلة لتجنب مشاكل التنفيذ المتعدد
    // ملاحظة: هذا التقسيم بسيط وقد يحتاج لمعالجة أدق للـ delimiters، 
    // لكنه كافٍ لمعظم ملفات الدامب العادية.
    $queries = explode(";\n", $sql);

    $count = 0;
    $pdo->beginTransaction();
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
            $count++;
        }
    }
    
    $pdo->commit();

    echo "<h3 style='color:green;'>✅ Success! $count queries executed.</h3>";
    echo "<p>The 'organizations' table and others should now be created.</p>";
    echo "<p>You can now go to <a href='auth/login.php'>Login Page</a></p>";
    echo "<p style='color:red;'><b>Important:</b> Please delete this file (fix_db.php) now for security.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h3 style='color:red;'>❌ Error during import:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<h4>Debug Info:</h4>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
}
?>