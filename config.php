<?php
// config.php - إعدادات الاتصال (Secure Mode)
$servername = "localhost";
$username = "u159629331_work"; 
$password = "AllahAkbar@1986"; 
$dbname = "u159629331_wo"; 

// إخفاء الأخطاء الظاهرة للمستخدم لحماية المسار
error_reporting(0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // تسجيل الخطأ في ملف لوج داخلي بدلاً من عرضه
    error_log($e->getMessage());
    die("⚠️ نأسف، يوجد عطل فني في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.");
}
?>
