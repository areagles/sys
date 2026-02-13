<?php
// job_details.php - (Universal Logic Fix V6.0)
// هذا التعديل يضمن عمل زر الإنهاء وإعادة الفتح مع جميع أنواع قواعد البيانات

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 

// --- 1. المحرك الذكي لتحديث الحالة (Universal Update Engine) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    
    $action_id = intval($_GET['id']);
    $new_status = '';
    $action_msg = '';

    // تحديد الحالة المطلوبة
    if (isset($_POST['archive_job'])) {
        $new_status = 'completed';
        $action_msg = 'archived';
    } elseif (isset($_POST['reopen_job'])) {
        $new_status = 'processing';
        $action_msg = 'reopened';
    }

    // تنفيذ التحديث إذا تم تحديد حالة جديدة
    if (!empty($new_status)) {
        // محاولة التحديث باستخدام PDO (الأحدث والأكثر أماناً)
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("UPDATE job_orders SET status = ?, current_stage = ? WHERE id = ?");
            $stmt->execute([$new_status, $new_status, $action_id]);
        } 
        // محاولة التحديث باستخدام MySQLi (للتوافق مع الأنظمة القديمة)
        elseif (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("UPDATE job_orders SET status = ?, current_stage = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_status, $new_status, $action_id);
            $stmt->execute();
        }

        // إعادة التوجيه
        header("Location: job_details.php?id=" . $action_id . "&msg=" . $action_msg);
        exit;
    }
}
// ---------------------------------------------------------

require 'header.php';

if(!isset($_GET['id']) || empty($_GET['id'])) {
    die("<div class='container'><div class='royal-alert error'>⛔ رابط غير صحيح.</div></div>");
}

$job_id = intval($_GET['id']);

// جلب البيانات (يدعم MySQLi فقط للعرض لأن أغلب القوالب تعتمد عليه)
// إذا كان نظامك PDO بالكامل، يرجى تحويل هذا الاستعلام
if (isset($conn)) {
    $sql = "SELECT j.*, c.name as client_name, c.phone as client_phone 
            FROM job_orders j 
            LEFT JOIN clients c ON j.client_id = c.id 
            WHERE j.id = $job_id";
    $res = $conn->query($sql);
    $job = $res->fetch_assoc();
} elseif (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT j.*, c.name as client_name, c.phone as client_phone 
            FROM job_orders j 
            LEFT JOIN clients c ON j.client_id = c.id 
            WHERE j.id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
}

if(!$job) {
    die("<div class='container'><div class='royal-alert error'>⛔ العملية غير موجودة.</div></div>");
}

$job_type = $job['job_type'];
$curr = $job['current_stage']; 

?>

<style>
    .royal-container { max-width: 1200px; margin: 30px auto; padding: 0 15px; }
    .royal-alert { padding: 20px; border-radius: 10px; text-align: center; font-weight: bold; margin-top: 50px; border: 1px solid #333; }
    .royal-alert.error { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border-color: #e74c3c; }
    .royal-alert.success { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border-color: #2ecc71; margin-bottom: 20px; }
    .btn-royal { background: linear-gradient(45deg, var(--gold), #b8860b); color: #000; padding: 8px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; }
    .missing-module-card { background: var(--panel); border: 1px dashed #555; padding: 40px; text-align: center; border-radius: 15px; margin-top: 50px; }
</style>

<div class="royal-container">

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'archived'): ?>
        <div class="royal-alert success">✅ تم إنهاء وأرشفة العملية بنجاح.</div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'reopened'): ?>
        <div class="royal-alert success">🔄 تم إعادة فتح العملية للعمل.</div>
    <?php endif; ?>

    <?php
    $module_map = [
        'print'       => 'print.php',
        'carton'      => 'carton.php',
        'plastic'     => 'plastic.php',
        'web'         => 'web.php',
        'social'      => 'social.php',
        'identity'    => 'identity.php',
        'design_only' => 'design_only.php'
    ];

    if (array_key_exists($job_type, $module_map)) {
        $module_file = "modules/" . $module_map[$job_type];
        if (file_exists($module_file)) include $module_file;
        else echo "<div class='missing-module-card'><h2 style='color:#e74c3c'>⚠️ الموديول مفقود</h2></div>";
    } else {
        echo "<div class='missing-module-card'><h2 style='color:#f1c40f'>⚠️ نوع غير معروف</h2></div>";
    }
    ?>

</div>

<?php include 'footer.php'; ob_end_flush(); ?>