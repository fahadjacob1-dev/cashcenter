<?php
// =====================================================
// ملف التنصيب لإنشاء الجداول في قاعدة بيانات Railway
// =====================================================
require_once 'config.php';

$sql = file_get_contents('cashcenter.sql');

// مسح أوامر إنشاء قاعدة البيانات لأن Railway تنشئها تلقائياً باسم railway
$sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
$sql = preg_replace('/USE cashcenter;/i', '', $sql);

// تنفيذ ملف الـ SQL بالكامل
if ($conn->multi_query($sql)) {
    do {
        // تفريغ النتائج حتى يكمل كل الأوامر
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    echo "<h1 style='color: green; text-align: center; margin-top: 50px;'>تم إنشاء الجداول وقاعدة البيانات بنجاح! 🎉</h1>";
    echo "<p style='text-align: center;'><a href='login.html'>اضغط هنا للذهاب إلى صفحة تسجيل الدخول</a></p>";
} else {
    echo "<h1 style='color: red; text-align: center; margin-top: 50px;'>حدث خطأ أثناء إنشاء الجداول:</h1>";
    echo "<p style='text-align: center;'>" . $conn->error . "</p>";
}

$conn->close();
?>
