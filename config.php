<?php
// config.php - إعدادات الاتصال (نسخة خالية من الأخطاء)

// تعريف رابط النظام الأساسي
if (!defined('SYSTEM_URL')) {
    define('SYSTEM_URL', 'https://work.areagles.com'); 
}

$servername = "localhost";
$username = "u159629331_work"; 
$password = "AllahAkbar@1986"; 
$dbname = "u159629331_wo"; 

// [تصحيح هام]
// نتحقق أولاً إذا كانت الجلسة لم تبدأ بعد قبل محاولة تغيير الإعدادات
// هذا يمنع ظهور الخطأ: Session ini settings cannot be changed...
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    // محاولة مشاركة الجلسة (اختياري، ويمكن تعطيله إذا استمرت المشاكل)
    @ini_set('session.cookie_domain', '.areagles.com');
}

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// التحقق من الأخطاء
if ($conn->connect_error) {
    // تسجيل الخطأ في ملف النظام بدلاً من إظهاره للمستخدم
    error_log("Connection failed: " . $conn->connect_error);
    die("⚠️ نأسف، النظام في وضع الصيانة حالياً (خطأ اتصال).");
}
?>