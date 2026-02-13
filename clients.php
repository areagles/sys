<?php
// clients.php - (Royal Clients V10.0 - Royal Cards Design)
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

/* ==================================================
   1. التحديث التلقائي (Auto-Schema)
   ================================================== */
$conn->query("CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `google_map` text DEFAULT NULL,
  `opening_balance` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `access_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$cols = [];
$res = $conn->query("SHOW COLUMNS FROM clients");
if($res) { while($c = $res->fetch_assoc()) $cols[] = $c['Field']; }

if(!in_array('password_hash', $cols)) $conn->query("ALTER TABLE clients ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL");
if(!in_array('access_token', $cols)) $conn->query("ALTER TABLE clients ADD COLUMN access_token VARCHAR(100) DEFAULT NULL");
if(!in_array('opening_balance', $cols)) $conn->query("ALTER TABLE clients ADD COLUMN opening_balance DECIMAL(10,2) DEFAULT 0.00");

/* ==================================================
   2. المعالجة (POST Request)
   ================================================== */

if(isset($_POST['reset_pass'])){
    $cid = intval($_POST['client_id']);
    $def_pass = password_hash('123456', PASSWORD_DEFAULT);
    $conn->query("UPDATE clients SET password_hash='$def_pass' WHERE id=$cid");
    header("Location: clients.php?msg=pass_reset"); exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if(isset($_POST['add_client']) || isset($_POST['update_client'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $email = $conn->real_escape_string($_POST['email']);
        $address = $conn->real_escape_string($_POST['address']);
        $map = $conn->real_escape_string($_POST['google_map']);
        $opening = floatval($_POST['opening_balance']);
        $notes = $conn->real_escape_string($_POST['notes']);

        if(isset($_POST['update_client'])){
            $id = intval($_POST['client_id']);
            $sql = "UPDATE clients SET 
                    name='$name', phone='$phone', email='$email', address='$address', 
                    google_map='$map', opening_balance='$opening', notes='$notes' 
                    WHERE id=$id";
            if($conn->query($sql)) header("Location: clients.php?msg=updated");
        } elseif(isset($_POST['add_client'])) {
            $pass = password_hash('123456', PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16));
            $sql = "INSERT INTO clients (name, phone, email, password_hash, address, google_map, opening_balance, notes, access_token) 
                    VALUES ('$name', '$phone', '$email', '$pass', '$address', '$map', '$opening', '$notes', '$token')";
            if($conn->query($sql)) header("Location: clients.php?msg=added");
        }
    }
}

if(isset($_GET['del']) && $_SESSION['role'] == 'admin'){
    $id = intval($_GET['del']);
    $conn->query("DELETE FROM clients WHERE id=$id");
    header("Location: clients.php?msg=deleted"); exit;
}

function get_portal_link() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    return "$protocol://$host/portal/login.html";
}

// --- الإحصائيات ---
$stats = $conn->query("
    SELECT 
        COUNT(*) as count,
        SUM(opening_balance) as open_bal,
        (SELECT IFNULL(SUM(total_amount),0) FROM invoices) as total_inv,
        (SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE type='in') as total_rec
    FROM clients
")->fetch_assoc();

$total_debt = ($stats['open_bal'] + $stats['total_inv']) - $stats['total_rec'];
?>

<style>
    :root { --gold: #d4af37; --bg-dark: #0f0f0f; --card-bg: #1a1a1a; --border: #333; }
    body { background-color: var(--bg-dark); font-family: 'Cairo', sans-serif; color: #fff; padding-bottom: 80px; }
    
    /* Stats Bar */
    .stats-bar {
        display: flex; gap: 15px; margin-bottom: 30px; overflow-x: auto; padding-bottom: 5px;
    }
    .stat-box {
        background: var(--card-bg); border: 1px solid var(--border); padding: 20px; border-radius: 12px;
        min-width: 200px; text-align: center; position: relative; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .stat-box::after { content:''; position:absolute; top:0; left:0; width:4px; height:100%; background:var(--gold); }
    .stat-val { font-size: 1.8rem; font-weight: bold; color: #fff; }
    .stat-lbl { font-size: 0.9rem; color: #888; }

    /* Search & Add */
    .toolbar {
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 15px;
        position: sticky; top: 10px; z-index: 10; background: rgba(15,15,15,0.95); padding: 15px;
        border-radius: 15px; border: 1px solid var(--border); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .search-box {
        flex: 1; background: #000; border: 1px solid #444; color: #fff; padding: 12px 20px; 
        border-radius: 30px; outline: none; font-size: 1rem; transition: 0.3s;
    }
    .search-box:focus { border-color: var(--gold); box-shadow: 0 0 10px rgba(212, 175, 55, 0.2); }
    
    .btn-add-main {
        background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; border: none;
        padding: 12px 25px; border-radius: 30px; font-weight: bold; cursor: pointer; 
        white-space: nowrap; box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3); transition: 0.3s;
    }
    .btn-add-main:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4); }

    /* Client Cards Grid (Max 3 Columns) */
    .clients-grid {
        display: grid; 
        grid-template-columns: repeat(3, 1fr); /* 3 أعمدة كحد أقصى */
        gap: 20px;
    }
    
    @media (max-width: 1100px) {
        .clients-grid { grid-template-columns: repeat(2, 1fr); } /* عمودين للتابلت */
    }
    @media (max-width: 700px) {
        .clients-grid { grid-template-columns: 1fr; } /* عمود واحد للموبايل */
    }

    /* Card Design */
    .client-card {
        background: linear-gradient(145deg, #1a1a1a, #151515);
        border: 1px solid var(--border); border-radius: 16px; padding: 25px;
        position: relative; transition: all 0.3s ease; 
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .client-card:hover { 
        transform: translateY(-5px); 
        border-color: var(--gold); 
        box-shadow: 0 10px 30px rgba(212, 175, 55, 0.15);
    }
    
    .c-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
    .c-avatar { 
        width: 50px; height: 50px; background: rgba(212, 175, 55, 0.1); 
        color: var(--gold); border-radius: 50%; display: flex; align-items: center; 
        justify-content: center; font-size: 1.2rem; margin-left: 15px; border: 1px solid rgba(212, 175, 55, 0.3);
    }
    .c-info { flex: 1; }
    .c-name { font-size: 1.1rem; font-weight: bold; color: #fff; margin: 0 0 5px 0; }
    .c-phone { font-size: 0.9rem; color: #aaa; font-family: monospace; }
    
    .c-balance-box {
        background: #000; border-radius: 10px; padding: 15px; margin: 15px 0;
        text-align: center; border: 1px dashed #333;
    }
    .c-balance-val { font-size: 1.4rem; font-weight: 800; display: block; }
    .bal-pos { color: #e74c3c; } /* عليه */
    .bal-neg { color: #2ecc71; } /* ليه */
    
    .c-actions {
        display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; border-top: 1px solid #333; padding-top: 15px;
    }
    .action-btn {
        background: #222; color: #fff; border: 1px solid #444; border-radius: 10px; 
        height: 40px; display: flex; align-items: center; justify-content: center; 
        text-decoration: none; font-size: 1rem; transition: 0.2s; cursor: pointer;
    }
    .action-btn:hover { background: var(--gold); color: #000; border-color: var(--gold); }
    
    /* Specific Button Colors */
    .btn-wa { color: #25D366; border-color: rgba(37, 211, 102, 0.3); }
    .btn-wa:hover { background: #25D366; color: #fff; }
    
    .btn-edit { color: #f39c12; border-color: rgba(243, 156, 18, 0.3); }
    .btn-edit:hover { background: #f39c12; color: #000; }
    
    .btn-del { color: #e74c3c; border-color: rgba(231, 76, 60, 0.3); }
    .btn-del:hover { background: #e74c3c; color: #fff; }

    /* Modal */
    .custom-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 2000; padding: 20px; overflow-y: auto; backdrop-filter: blur(5px); }
    .modal-content { max-width: 600px; margin: 30px auto; background: #151515; border: 1px solid var(--gold); padding: 30px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .full-width { grid-column: span 2; }
    .form-control { width: 100%; background: #0a0a0a; border: 1px solid #444; color: #fff; padding: 14px; border-radius: 10px; margin-bottom: 5px; outline: none; transition: 0.3s; }
    .form-control:focus { border-color: var(--gold); box-shadow: 0 0 10px rgba(212, 175, 55, 0.1); }
</style>

<div class="container">

    <div class="stats-bar">
        <div class="stat-box">
            <div class="stat-val"><?php echo $stats['count']; ?></div>
            <div class="stat-lbl">العملاء</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color:<?php echo $total_debt > 0 ? '#e74c3c' : '#2ecc71'; ?>">
                <?php echo number_format($total_debt); ?> <small>EGP</small>
            </div>
            <div class="stat-lbl">إجمالي المديونية</div>
        </div>
    </div>

    <div class="toolbar">
        <form method="GET" style="flex:1; display:flex;">
            <input type="text" name="q" class="search-box" placeholder="بحث باسم العميل أو الهاتف..." value="<?php echo isset($_GET['q']) ? $_GET['q'] : ''; ?>">
        </form>
        <button onclick="openModal('addModal')" class="btn-add-main"><i class="fa-solid fa-user-plus"></i> <span style="display:none; display:md-inline;">إضافة عميل</span></button>
    </div>

    <div class="clients-grid">
        <?php 
        $search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
        $sql = "SELECT c.*, 
                (SELECT IFNULL(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id) as total_sales,
                (SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE client_id = c.id AND type = 'in') as total_paid
                FROM clients c 
                WHERE name LIKE '%$search%' OR phone LIKE '%$search%'
                ORDER BY c.id DESC";
        
        $res = $conn->query($sql);
        if($res && $res->num_rows > 0):
            while($row = $res->fetch_assoc()):
                $balance = ($row['opening_balance'] + $row['total_sales']) - $row['total_paid'];
                $bal_class = $balance > 0 ? 'bal-pos' : 'bal-neg';
                
                // إعداد واتساب
                $portal_link = get_portal_link();
                $has_pass = !empty($row['password_hash']);
                $msg_text = "مرحباً {$row['name']}،\nيسعدنا تواصلك مع Arab Eagles.\nرابط البوابة: $portal_link\nاسم المستخدم: {$row['phone']}\n";
                if(!$has_pass) $msg_text .= "كلمة المرور: 123456";
                $wa_msg = urlencode($msg_text);
                $wa_num = preg_replace('/[^0-9]/', '', $row['phone']);
                if(substr($wa_num, 0, 1) == '0') $wa_num = '2'.$wa_num;

                // تأمين البيانات
                $safeData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="client-card">
            <div class="c-header">
                <div style="display:flex; align-items:center;">
                    <div class="c-avatar"><i class="fa-solid fa-user"></i></div>
                    <div class="c-info">
                        <h3 class="c-name"><?php echo $row['name']; ?></h3>
                        <div class="c-phone"><?php echo $row['phone']; ?></div>
                    </div>
                </div>
                <a href="tel:<?php echo $row['phone']; ?>" class="action-btn" style="width:35px; height:35px; border-radius:50%;"><i class="fa-solid fa-phone"></i></a>
            </div>

            <div class="c-balance-box">
                <span style="font-size:0.8rem; color:#888;">الرصيد الحالي</span>
                <span class="c-balance-val <?php echo $bal_class; ?>">
                    <?php echo number_format($balance, 2); ?> <small style="font-size:0.8rem;">EGP</small>
                </span>
            </div>

            <div class="c-actions">
                <a href="https://wa.me/<?php echo $wa_num; ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="action-btn btn-wa" title="إرسال بيانات الدخول"><i class="fa-brands fa-whatsapp"></i></a>
                
                <button onclick="editClient(<?php echo $safeData; ?>)" class="action-btn btn-edit" title="تعديل"><i class="fa-solid fa-pen"></i></button>
                
                <form method="POST" style="display:contents;" onsubmit="return confirm('إعادة تعيين الباسورد لـ 123456؟');">
                    <input type="hidden" name="client_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="reset_pass" value="1">
                    <button type="submit" class="action-btn" title="باسورد افتراضي"><i class="fa-solid fa-key"></i></button>
                </form>

                <?php if($_SESSION['role'] == 'admin'): ?>
                <a href="?del=<?php echo $row['id']; ?>" onclick="return confirm('حذف نهائي؟')" class="action-btn btn-del" title="حذف"><i class="fa-solid fa-trash"></i></a>
                <?php else: ?>
                <button class="action-btn" disabled style="opacity:0.3"><i class="fa-solid fa-lock"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column:1/-1; text-align:center; padding:50px; color:#666;">لا يوجد عملاء مطابقين للبحث</div>
        <?php endif; ?>
    </div>
</div>

<div id="addModal" class="custom-modal">
    <div class="modal-content">
        <h3 style="color:var(--gold); margin-top:0; border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:20px;">➕ إضافة عميل جديد</h3>
        <form method="POST">
            <div class="form-grid">
                <div class="full-width"><label style="color:#aaa;">الاسم</label><input type="text" name="name" required class="form-control"></div>
                <div><label style="color:#aaa;">الهاتف</label><input type="text" name="phone" required class="form-control"></div>
                <div><label style="color:#aaa;">الرصيد الافتتاحي</label><input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00"></div>
                <div><label style="color:#aaa;">البريد</label><input type="email" name="email" class="form-control"></div>
                <div><label style="color:#aaa;">الخريطة</label><input type="text" name="google_map" class="form-control"></div>
                <div class="full-width"><label style="color:#aaa;">العنوان</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="full-width"><label style="color:#aaa;">ملاحظات</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            </div>
            <button type="submit" name="add_client" class="btn-add-main" style="width:100%; margin-top:20px; border-radius:10px;">حفظ العميل</button>
            <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn-add-main" style="width:100%; margin-top:10px; background:#333; border-radius:10px;">إلغاء</button>
        </form>
    </div>
</div>

<div id="editModal" class="custom-modal">
    <div class="modal-content">
        <h3 style="color:var(--gold); margin-top:0; border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:20px;">✏️ تعديل البيانات</h3>
        <form method="POST">
            <input type="hidden" name="client_id" id="e_id">
            <div class="form-grid">
                <div class="full-width"><label style="color:#aaa;">الاسم</label><input type="text" name="name" id="e_name" required class="form-control"></div>
                <div><label style="color:#aaa;">الهاتف</label><input type="text" name="phone" id="e_phone" required class="form-control"></div>
                <div><label style="color:#aaa;">الرصيد الافتتاحي</label><input type="number" step="0.01" name="opening_balance" id="e_open" class="form-control"></div>
                <div><label style="color:#aaa;">البريد</label><input type="email" name="email" id="e_email" class="form-control"></div>
                <div><label style="color:#aaa;">الخريطة</label><input type="text" name="google_map" id="e_map" class="form-control"></div>
                <div class="full-width"><label style="color:#aaa;">العنوان</label><textarea name="address" id="e_address" class="form-control" rows="2"></textarea></div>
                <div class="full-width"><label style="color:#aaa;">ملاحظات</label><textarea name="notes" id="e_notes" class="form-control" rows="2"></textarea></div>
            </div>
            <button type="submit" name="update_client" class="btn-add-main" style="width:100%; margin-top:20px; border-radius:10px;">تحديث البيانات</button>
            <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn-add-main" style="width:100%; margin-top:10px; background:#333; border-radius:10px;">إلغاء</button>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    
    function editClient(data) {
        document.getElementById('e_id').value = data.id;
        document.getElementById('e_name').value = data.name;
        document.getElementById('e_phone').value = data.phone;
        document.getElementById('e_open').value = data.opening_balance;
        document.getElementById('e_email').value = data.email || '';
        document.getElementById('e_address').value = data.address || '';
        document.getElementById('e_map').value = data.google_map || '';
        document.getElementById('e_notes').value = data.notes || '';
        openModal('editModal');
    }
</script>

<?php include 'footer.php'; ?>