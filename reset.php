<?php
require_once 'config.php';

// توليد تشفير جديد لكلمة 123456
$new_hash = password_hash('123456', PASSWORD_BCRYPT, ['cost' => 12]);

// تحديث الباسورد لحساب admin
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->bind_param('s', $new_hash);

if ($stmt->execute()) {
    echo "<h1 style='color:green; text-align:center;'>تم تصفير الباسورد بنجاح!</h1>";
    echo "<h2 style='text-align:center;'>اليوزر: admin</h2>";
    echo "<h2 style='text-align:center;'>الباسورد الجديد: 123456</h2>";
    echo "<p style='text-align:center;'><a href='login.html'>اضغط هنا للعودة لصفحة الدخول</a></p>";
} else {
    echo "حدث خطأ: " . $conn->error;
}
$conn->close();
?>
