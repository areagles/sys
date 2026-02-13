<?php
// edit_job.php - (نسخة الإصلاح الذاتي V3.2 - PHP 8.1 Fix)
ob_start();
error_reporting(E_ALL); 
ini_set('display_errors', 1);

require 'auth.php'; 
require 'config.php'; 

// =========================================================
// 0. بروتوكول الإصلاح الذاتي لقاعدة البيانات (Self-Healing)
// =========================================================
$columns_to_fix = [
    'price' => "DECIMAL(10,2) DEFAULT 0.00",
    'paid'  => "DECIMAL(10,2) DEFAULT 0.00",
    'quantity' => "INT(11) DEFAULT 0",
    'job_details' => "TEXT"
];

foreach ($columns_to_fix as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM job_orders LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE job_orders ADD COLUMN $col $def");
    }
}
// =========================================================

// 1. التحقق من الصلاحيات
if(in_array($_SESSION['role'], ['driver', 'worker'])) {
    die("⛔ عذراً، ليس لديك صلاحية تعديل أوامر الشغل.");
}

// 2. التحقق من الرابط
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php"); exit;
}

$id = intval($_GET['id']);
$msg = "";

// 3. معالجة الحفظ
if(isset($_POST['update_job'])){
    $job_name = $conn->real_escape_string($_POST['job_name']);
    $client_id = intval($_POST['client_id']);
    $delivery_date = $_POST['delivery_date'];
    
    $price = !empty($_POST['price']) ? floatval($_POST['price']) : 0.00;
    $paid = !empty($_POST['paid']) ? floatval($_POST['paid']) : 0.00;
    $quantity = intval($_POST['quantity']);
    
    $notes = $conn->real_escape_string($_POST['notes']);
    $job_details = $conn->real_escape_string($_POST['job_details']);
    $current_stage = $_POST['current_stage'];
    
    $sql = "UPDATE job_orders SET 
            job_name = '$job_name',
            client_id = '$client_id',
            delivery_date = '$delivery_date',
            price = '$price',
            paid = '$paid',
            quantity = '$quantity',
            notes = '$notes',
            job_details = '$job_details',
            current_stage = '$current_stage'
            WHERE id = $id";

    if($conn->query($sql)){
        $msg = "
        <script>
            alert('✅ تم حفظ التعديلات بنجاح!');
            window.location.href = 'job_details.php?id=$id';
        </script>";
    } else {
        $error = $conn->error;
        $msg = "<div class='royal-alert error'>❌ حدث خطأ في قاعدة البيانات: <br> $error</div>";
    }
}

// 4. جلب البيانات
$query = $conn->query("SELECT * FROM job_orders WHERE id = $id");
if($query->num_rows == 0) die("العملية غير موجودة");
$job = $query->fetch_assoc();

// قائمة المراحل
$stages = [
    'briefing' => '📝 أمر التشغيل',
    'design' => '🎨 التصميم',
    'client_rev' => '⏳ مراجعة العميل',
    'pre_press' => '⚙️ التجهيز الفني',
    'printing' => '🖨️ الطباعة / الإنتاج',
    'finishing' => '✨ التشطيب',
    'die_cutting' => '✂️ التكسير (كرتون)',
    'gluing' => '🧴 اللصق (كرتون)',
    'delivery' => '🚚 التسليم',
    'completed' => '✅ منتهي / أرشيف'
];

require 'header.php'; 
?>

<style>
    :root { --gold: #d4af37; --bg: #121212; --panel: #1e1e1e; --input-bg: #0a0a0a; --border: #333; }
    body { background-color: var(--bg); color: #e0e0e0; font-family: 'Cairo', sans-serif; }
    
    .edit-card {
        background: var(--panel);
        border: 1px solid var(--border);
        border-top: 4px solid var(--gold);
        border-radius: 10px;
        padding: 30px;
        max-width: 900px;
        margin: 30px auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    }
    
    .section-title {
        color: var(--gold);
        font-size: 1.1rem;
        border-bottom: 1px dashed var(--border);
        padding-bottom: 10px;
        margin: 25px 0 15px 0;
        display: flex; align-items: center; gap: 10px;
    }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    
    label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #aaa; }
    
    input, select, textarea {
        width: 100%; padding: 12px;
        background: var(--input-bg); border: 1px solid var(--border);
        color: #fff; border-radius: 6px; font-family: 'Cairo';
        box-sizing: border-box; transition: 0.3s;
    }
    
    input:focus, select:focus, textarea:focus { border-color: var(--gold); outline: none; }
    
    .btn-save {
        background: linear-gradient(45deg, var(--gold), #b8860b);
        color: #000; border: none; padding: 15px 40px;
        border-radius: 50px; font-weight: bold; cursor: pointer;
        font-size: 1.1rem; width: 100%; margin-top: 30px;
        transition: transform 0.2s;
    }
    .btn-save:hover { transform: scale(1.02); }
    
    .royal-alert.error { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;}
</style>

<div class="edit-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0; color:#fff;">✏️ تعديل أمر الشغل #<?php echo $job['id']; ?></h2>
        <a href="job_details.php?id=<?php echo $id; ?>" style="color:#aaa; text-decoration:none;">↩️ إلغاء وعودة</a>
    </div>
    
    <?php echo $msg; ?>

    <form method="POST">
        
        <div class="section-title"><i class="fa-solid fa-file-signature"></i> البيانات الأساسية</div>
        <div class="grid-2">
            <div>
                <label>اسم العملية</label>
                <input type="text" name="job_name" value="<?php echo htmlspecialchars($job['job_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label>العميل</label>
                <select name="client_id">
                    <?php 
                    $c_q = $conn->query("SELECT id, name FROM clients");
                    while($c = $c_q->fetch_assoc()){
                        $sel = ($c['id'] == $job['client_id']) ? 'selected' : '';
                        echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="grid-2" style="margin-top:15px;">
            <div>
                <label>تاريخ التسليم</label>
                <input type="date" name="delivery_date" value="<?php echo $job['delivery_date']; ?>" required>
            </div>
            <div>
                <label>الكمية المطلوبة</label>
                <input type="number" name="quantity" value="<?php echo $job['quantity']; ?>">
            </div>
        </div>

        <div class="section-title"><i class="fa-solid fa-microchip"></i> التفاصيل الفنية</div>
        <textarea name="job_details" rows="8" style="font-family:monospace; line-height:1.6;"><?php echo htmlspecialchars($job['job_details'] ?? ''); ?></textarea>

        <div class="section-title"><i class="fa-solid fa-coins"></i> الملف المالي</div>
        <div class="grid-2">
            <div>
                <label>إجمالي السعر</label>
                <input type="number" step="0.01" name="price" style="border-color:#2ecc71; color:#2ecc71;" value="<?php echo $job['price']; ?>">
            </div>
            <div>
                <label>المدفوع</label>
                <input type="number" step="0.01" name="paid" style="border-color:#2ecc71; color:#2ecc71;" value="<?php echo $job['paid']; ?>">
            </div>
        </div>

        <div class="section-title"><i class="fa-solid fa-sliders"></i> التحكم والمرحلة</div>
        <div class="grid-2">
            <div>
                <label>المرحلة الحالية</label>
                <select name="current_stage" style="border-color: #e67e22;">
                    <?php foreach($stages as $key => $label): 
                        $sel = ($key == $job['current_stage']) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $key; ?>" <?php echo $sel; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <label style="margin-top:15px;">ملاحظات إدارية</label>
        <textarea name="notes" rows="3"><?php echo htmlspecialchars($job['notes'] ?? ''); ?></textarea>

        <button type="submit" name="update_job" class="btn-save">💾 حفظ التعديلات</button>

    </form>
</div>

<?php include 'footer.php'; ob_end_flush(); ?>