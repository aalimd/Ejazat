<?php
require_once 'includes/config.php';

echo "<h2>Starting Database Fix...</h2>";

try {
    // قراءة ملف SQL
    $sqlFile = 'sql/database.sql';
    if (!file_exists($sqlFile)) {
        die("Error: sql/database.sql not found!");
    }

    $sql = file_get_contents($sqlFile);

    // تنفيذ الاستعلامات
    $pdo->exec($sql);

    echo "<h3 style='color:green;'>✅ Success! All tables have been created.</h3>";
    echo "<p>You can now go to <a href='auth/login.php'>Login Page</a></p>";
    echo "<p><b>Security Note:</b> Please delete this file (fix_db.php) from your server now.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Error during import:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>