<?php
// suppliers.php - إدارة الموردين (النسخة الملكية V5.0 - تعديل + روابط)
ob_start();
ini_set('display_errors', 0); // إخفاء الأخطاء للمستخدم
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

/* ==================================================
   1. التصليح الذاتي (Auto-Update)
   ================================================== */
$conn->query("CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `opening_balance` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `access_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// التأكد من الأعمدة
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM suppliers");
while($c = $res->fetch_assoc()) $cols[] = $c['Field'];

if(!in_array('access_token', $cols)) $conn->query("ALTER TABLE suppliers ADD COLUMN access_token VARCHAR(100) DEFAULT NULL");
if(!in_array('notes', $cols)) $conn->query("ALTER TABLE suppliers ADD COLUMN notes TEXT DEFAULT NULL");

/* ==================================================
   2. معالجة العمليات (إضافة - تعديل - حذف)
   ================================================== */

// A. الحذف
if(isset($_GET['del']) && $_SESSION['role'] == 'admin'){
    $id = intval($_GET['del']);
    $conn->query("DELETE FROM suppliers WHERE id=$id");
    header("Location: suppliers.php?msg=deleted"); exit;
}

// B. الإضافة / التعديل
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $contact = $conn->real_escape_string($_POST['contact_person']);
    $opening = floatval($_POST['opening_balance']);
    $notes = $conn->real_escape_string($_POST['notes']);

    if(isset($_POST['update_supplier'])){
        // تحديث
        $id = intval($_POST['supplier_id']);
        $sql = "UPDATE suppliers SET 
                name='$name', category='$category', phone='$phone', email='$email', 
                address='$address', contact_person='$contact', opening_balance='$opening', notes='$notes' 
                WHERE id=$id";
        if($conn->query($sql)) { header("Location: suppliers.php?msg=updated"); exit; }
    } elseif(isset($_POST['add_supplier'])) {
        // إضافة جديد
        $token = bin2hex(random_bytes(16));
        $sql = "INSERT INTO suppliers (name, category, phone, email, address, contact_person, opening_balance, notes, access_token) 
                VALUES ('$name', '$category', '$phone', '$email', '$address', '$contact', '$opening', '$notes', '$token')";
        if($conn->query($sql)) { header("Location: suppliers.php?msg=success"); exit; }
    }
}

// C. جلب بيانات للتعديل
$edit_mode = false;
$s_edit = [];
if(isset($_GET['edit'])){
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM suppliers WHERE id=$id");
    if($res->num_rows > 0){
        $edit_mode = true;
        $s_edit = $res->fetch_assoc();
    }
}

// دالة الروابط
function get_portal_link($id, $token) {
    global $conn;
    if(empty($token)) {
        $token = bin2hex(random_bytes(16));
        $conn->query("UPDATE suppliers SET access_token='$token' WHERE id=$id");
    }
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $path = str_replace('/modules', '', dirname($_SERVER['PHP_SELF'])); 
    return "$protocol://$host$path/financial_review.php?token=$token&type=supplier";
}
?>

<style>
    :root { --gold: #d4af37; --bg-dark: #0f0f0f; --card-bg: #1a1a1a; }
    body { background-color: var(--bg-dark); font-family: 'Cairo', sans-serif; color: #fff; }
    
    .page-header { display: flex; justify-content: space-between; align-items: center; margin: 30px 0; }
    .page-title { margin: 0; color: #fff; font-size: 1.8rem; display: flex; align-items: center; gap: 10px; }
    .page-title i { color: var(--gold); }

    .royal-form-card { background: var(--card-bg); padding: 30px; border-radius: 15px; border: 1px solid #333; border-top: 4px solid var(--gold); margin-bottom: 40px; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
    
    .form-group label { display: block; color: var(--gold); margin-bottom: 8px; font-size: 0.9rem; font-weight: bold; }
    .form-control { width: 100%; background: #000; border: 1px solid #444; color: #fff; padding: 12px; border-radius: 8px; box-sizing: border-box; transition: 0.3s; }
    .form-control:focus { border-color: var(--gold); outline: none; box-shadow: 0 0 10px rgba(212, 175, 55, 0.2); }
    
    .btn-royal { width: 100%; padding: 15px; background: linear-gradient(45deg, var(--gold), #b8860b); border: none; font-weight: bold; font-size: 1rem; color: #000; border-radius: 8px; cursor: pointer; transition: 0.3s; }
    .btn-royal:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3); }
    .btn-cancel { background: #333; color: #ccc; margin-top: 10px; display:block; text-align:center; text-decoration:none; }

    /* الجدول */
    .table-container { overflow-x: auto; background: var(--card-bg); border-radius: 12px; border: 1px solid #333; }
    table { width: 100%; border-collapse: collapse; min-width: 900px; }
    th { background: #111; color: var(--gold); padding: 15px; text-align: right; border-bottom: 2px solid #333; font-size: 0.9rem; }
    td { padding: 15px; border-bottom: 1px solid #222; color: #ddd; vertical-align: middle; }
    tr:hover { background: #222; }
    
    .badge-cat { background: rgba(52, 152, 219, 0.15); color: #3498db; padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; border: 1px solid #3498db; }
    .balance-box { font-weight: 900; color: #e74c3c; font-size: 1rem; }
    .zero-balance { color: #2ecc71; }

    .action-btn { width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; color: #fff; transition: 0.3s; margin-left: 5px; text-decoration: none; border: 1px solid #333; }
    .btn-wa { background: #25D366; border-color: #25D366; } .btn-wa:hover { transform: scale(1.1); }
    .btn-copy { background: #3498db; border-color: #3498db; } .btn-copy:hover { transform: scale(1.1); }
    .btn-edit { background: #f39c12; border-color: #f39c12; color: #000; } .btn-edit:hover { background: #e67e22; }
    .btn-del { background: #c0392b; border-color: #c0392b; } .btn-del:hover { background: #e74c3c; }
</style>

<div class="container">
    <div class="page-header">
        <h2 class="page-title"><i class="fa-solid fa-truck-field"></i> إدارة الموردين</h2>
    </div>

    <div class="royal-form-card" id="formArea">
        <h3 style="margin-top:0; color:#fff; border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:20px;">
            <?php echo $edit_mode ? '✏️ تعديل بيانات المورد' : '➕ تسجيل مورد جديد'; ?>
        </h3>
        <form method="POST">
            <?php if($edit_mode): ?>
                <input type="hidden" name="supplier_id" value="<?php echo $s_edit['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>اسم المورد / الشركة</label>
                    <input type="text" name="name" required class="form-control" value="<?php echo $edit_mode ? $s_edit['name'] : ''; ?>" placeholder="اسم الشركة أو المورد">
                </div>
                <div class="form-group">
                    <label>التخصص (Category)</label>
                    <input type="text" name="category" list="cat_list" class="form-control" value="<?php echo $edit_mode ? $s_edit['category'] : ''; ?>" placeholder="ورق، زنكات، أحبار...">
                    <datalist id="cat_list">
                        <option value="ورق وطباعة">
                        <option value="خامات بلاستيك">
                        <option value="زنكات وسلندرات">
                        <option value="نقل وشحن">
                        <option value="أدوات مكتبية">
                    </datalist>
                </div>
                <div class="form-group">
                    <label>الشخص المسؤول</label>
                    <input type="text" name="contact_person" class="form-control" value="<?php echo $edit_mode ? $s_edit['contact_person'] : ''; ?>" placeholder="اسم المندوب">
                </div>
                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo $edit_mode ? $s_edit['phone'] : ''; ?>" placeholder="01xxxxxxxxx">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="<?php echo $edit_mode ? $s_edit['email'] : ''; ?>" placeholder="example@company.com">
                </div>
                <div class="form-group">
                    <label>العنوان</label>
                    <input type="text" name="address" class="form-control" value="<?php echo $edit_mode ? $s_edit['address'] : ''; ?>" placeholder="العنوان التفصيلي">
                </div>
                <div class="form-group">
                    <label>رصيد أول المدة</label>
                    <input type="number" step="0.01" name="opening_balance" class="form-control" value="<?php echo $edit_mode ? $s_edit['opening_balance'] : '0.00'; ?>">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>ملاحظات إضافية</label>
                <textarea name="notes" class="form-control" rows="2"><?php echo $edit_mode ? $s_edit['notes'] : ''; ?></textarea>
            </div>

            <button type="submit" name="<?php echo $edit_mode ? 'update_supplier' : 'add_supplier'; ?>" class="btn-royal">
                <?php echo $edit_mode ? 'حفظ التعديلات ✅' : 'حفظ بيانات المورد 💾'; ?>
            </button>
            <?php if($edit_mode): ?>
                <a href="suppliers.php" class="btn-royal btn-cancel">إلغاء</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="royal-form-card" style="padding:0; overflow:hidden; border-top:none;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>المورد</th>
                        <th>التخصص</th>
                        <th>المسؤول</th>
                        <th>الهاتف</th>
                        <th>المستحق له (عليه)</th>
                        <th>التحكم & الروابط</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // معادلة دقيقة: رصيد أول المدة + مشتريات - مدفوعات
                    $sql = "SELECT s.*, 
                            (SELECT IFNULL(SUM(total_amount), 0) FROM purchase_invoices WHERE supplier_id = s.id) as total_purchases,
                            (SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE supplier_id = s.id AND type = 'out') as total_paid
                            FROM suppliers s 
                            ORDER BY s.id DESC";
                    
                    $sups = $conn->query($sql);
                    
                    if($sups && $sups->num_rows > 0):
                        while($s = $sups->fetch_assoc()):
                            // الرصيد = (أول مدة + مشتريات) - مدفوعات
                            $real_balance = ($s['opening_balance'] + $s['total_purchases']) - $s['total_paid'];

                            // تجهيز الرابط
                            $link = get_portal_link($s['id'], $s['access_token']);
                            $wa_msg = urlencode("السادة {$s['name']}،\nمرفق رابط كشف الحساب للمراجعة:\n$link");
                    ?>
                    <tr>
                        <td>
                            <strong style="font-size:1rem;"><?php echo $s['name']; ?></strong>
                            <?php if($s['address']) echo "<div style='font-size:0.8rem; color:#777;'>📍 {$s['address']}</div>"; ?>
                        </td>
                        <td><span class="badge-cat"><?php echo $s['category'] ?: 'عام'; ?></span></td>
                        <td><?php echo $s['contact_person'] ?: '-'; ?></td>
                        <td style="font-family:sans-serif;"><?php echo $s['phone']; ?></td>
                        <td>
                            <span class="balance-box <?php echo $real_balance <= 0 ? 'zero-balance' : ''; ?>" style="direction:ltr; display:inline-block;">
                                <?php echo number_format($real_balance, 2); ?> ج.م
                            </span>
                            <div style="font-size:0.7rem; color:#666;">
                                مشتريات: <?php echo number_format($s['total_purchases']); ?> | مدفوع: <?php echo number_format($s['total_paid']); ?>
                            </div>
                        </td>
                        <td>
                            <button onclick="copyLink('<?php echo $link; ?>')" class="action-btn btn-copy" title="نسخ رابط البوابة"><i class="fa-solid fa-link"></i></button>
                            <a href="https://wa.me/<?php echo $s['phone']; ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="action-btn btn-wa" title="إرسال واتساب"><i class="fa-brands fa-whatsapp"></i></a>
                            
                            <a href="?edit=<?php echo $s['id']; ?>#formArea" class="action-btn btn-edit" title="تعديل"><i class="fa-solid fa-pen"></i></a>
                            
                            <?php if($_SESSION['role'] == 'admin'): ?>
                            <a href="?del=<?php echo $s['id']; ?>" onclick="return confirm('حذف المورد سيحذف تاريخ تعاملاته. هل أنت متأكد؟')" class="action-btn btn-del" title="حذف"><i class="fa-solid fa-trash-can"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#666;">لا يوجد موردين مسجلين.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyLink(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('تم نسخ رابط بوابة المورد! 📋');
    }, function(err) {
        console.error('فشل النسخ: ', err);
    });
}
</script>

<?php include 'footer.php'; ob_end_flush(); ?>