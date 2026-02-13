<?php
// work/config.php
$servername = "localhost";
$username = "u159629331_work"; 
$password = "AllahAkbar@1986"; 
$dbname = "u159629331_wo"; 

// إنشاء الاتصال بنظام MySQLi
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// دعم اللغة العربية
$conn->set_charset("utf8mb4");
?>