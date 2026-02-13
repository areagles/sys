<?php
// invoices.php - (Royal Invoices V28.0 - Full Salary Integration)
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// مفتاح التشفير (للمشاركة الآمنة)
$secret_key = "Eagle_Secret_Key_99"; 

// دالة واتساب الذكية
function get_wa_link($phone, $text) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11 && (substr($phone, 0, 2) == '01' || substr($phone, 0, 2) == '00')) {
         $phone = '2' . $phone; 
    }
    return "https://wa.me/$phone?text=" . urlencode($text);
}

// --- معالجة الإجراءات (Duplicate / Delete) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $act = $_GET['action'];
    $id = intval($_GET['id']);
    $type = $_GET['type'] ?? 'sales';
    
    // التكرار
    if ($act == 'duplicate') {
        $new_date = date('Y-m-d');
        if ($type == 'sales') {
            $orig = $conn->query("SELECT client_id, items_json, notes, sub_total, tax, discount, total_amount FROM invoices WHERE id=$id")->fetch_assoc();
            if ($orig) {
                $sql = "INSERT INTO invoices (inv_date, client_id, items_json, notes, sub_total, tax, discount, total_amount, remaining_amount, status) 
                        VALUES ('$new_date', '{$orig['client_id']}', '{$conn->real_escape_string($orig['items_json'])}', '{$conn->real_escape_string($orig['notes'])}', '{$orig['sub_total']}', '{$orig['tax']}', '{$orig['discount']}', '{$orig['total_amount']}', '{$orig['total_amount']}', 'unpaid')";
                $conn->query($sql);
                header("Location: edit_invoice.php?id=" . $conn->insert_id . "&msg=cloned"); exit;
            }
        } elseif ($type == 'purchase') {
            $orig = $conn->query("SELECT supplier_id, items_json, notes, sub_total, tax, discount, total_amount FROM purchase_invoices WHERE id=$id")->fetch_assoc();
            if ($orig) {
                $sql = "INSERT INTO purchase_invoices (inv_date, due_date, supplier_id, items_json, notes, sub_total, tax, discount, total_amount, remaining_amount, status) 
                        VALUES ('$new_date', '$new_date', '{$orig['supplier_id']}', '{$conn->real_escape_string($orig['items_json'])}', '{$conn->real_escape_string($orig['notes'])}', '{$orig['sub_total']}', '{$orig['tax']}', '{$orig['discount']}', '{$orig['total_amount']}', '{$orig['total_amount']}', 'unpaid')";
                $conn->query($sql);
                header("Location: edit_purchase.php?id=" . $conn->insert_id . "&msg=cloned"); exit;
            }
        }
    }
    
    // الحذف
    if ($act == 'delete' && $_SESSION['role'] == 'admin') {
        if ($type == 'sales') {
            $conn->query("DELETE FROM invoices WHERE id=$id");
            $conn->query("DELETE FROM financial_receipts WHERE invoice_id=$id"); 
        } elseif ($type == 'purchase') {
            $conn->query("DELETE FROM purchase_invoices WHERE id=$id");
        } elseif ($type == 'salary') {
            $conn->query("DELETE FROM payroll_sheets WHERE id=$id");
            $conn->query("DELETE FROM financial_receipts WHERE payroll_id=$id"); 
        }
        header("Location: invoices.php?tab=" . ($type == 'sales' ? 'sales' : ($type == 'purchase' ? 'purchases' : 'salaries')) . "&msg=deleted");
        exit();
    }
}

// تحديد التبويب والاستعلام
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'sales';
$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$title = ""; $stat_total = 0; $stat_paid = 0; $stat_due = 0;

if ($tab == 'sales') {
    $title = "🧾 فواتير المبيعات";
    $stats = $conn->query("SELECT SUM(total_amount) as t, SUM(paid_amount) as p, SUM(remaining_amount) as r FROM invoices")->fetch_assoc();
    $sql = "SELECT i.*, c.name as party_name, c.phone as party_phone FROM invoices i LEFT JOIN clients c ON i.client_id = c.id WHERE 1=1";
    if($search) $sql .= " AND (i.id LIKE '%$search%' OR c.name LIKE '%$search%')";
    $sql .= " ORDER BY i.id DESC";

} elseif ($tab == 'purchases') {
    $title = "📦 فواتير المشتريات";
    $stats = $conn->query("SELECT SUM(total_amount) as t, SUM(paid_amount) as p, SUM(remaining_amount) as r FROM purchase_invoices")->fetch_assoc();
    $sql = "SELECT p.*, s.name as party_name, s.phone as party_phone FROM purchase_invoices p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE 1=1";
    if($search) $sql .= " AND (p.id LIKE '%$search%' OR s.name LIKE '%$search%')";
    $sql .= " ORDER BY p.id DESC";

} elseif ($tab == 'salaries') {
    $title = "💰 مسيرات الرواتب";
    // [تصحيح 1]: حساب الإحصائيات للرواتب
    $stats = $conn->query("SELECT SUM(net_salary) as t, SUM(paid_amount) as p, SUM(remaining_amount) as r FROM payroll_sheets")->fetch_assoc();
    
    // [تصحيح 2]: جلب رقم الهاتف (u.phone) لاستخدامه في الواتساب
    $sql = "SELECT p.*, u.full_name as party_name, u.phone as party_phone FROM payroll_sheets p LEFT JOIN users u ON p.employee_id = u.id WHERE 1=1";
    if($search) $sql .= " AND (u.full_name LIKE '%$search%' OR p.month_year LIKE '%$search%')";
    $sql .= " ORDER BY p.month_year DESC, p.id DESC";
}

$stat_total = $stats['t'] ?? 0; $stat_paid = $stats['p'] ?? 0; $stat_due = $stats['r'] ?? 0;
$res = $conn->query($sql);
?>

<style>
    :root { --gold: #d4af37; --panel-bg: #1a1a1a; }
    .tabs-container { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
    .tab-btn { padding: 10px 25px; border-radius: 8px; text-decoration: none; color: #888; background: #222; transition: 0.3s; white-space: nowrap; font-weight: bold; }
    .tab-btn.active { background: rgba(212, 175, 55, 0.15); color: var(--gold); border: 1px solid var(--gold); }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .kpi-card { background: var(--panel-bg); padding: 15px; border-radius: 12px; border: 1px solid #333; text-align: center; }
    .kpi-card .num { font-size: 1.4rem; font-weight: bold; color: #fff; margin-top: 5px; }
    
    .table-container { background: var(--panel-bg); border-radius: 12px; overflow: hidden; border: 1px solid #333; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #111; color: var(--gold); padding: 15px; text-align: right; font-size: 0.9rem; }
    td { padding: 15px; border-bottom: 1px solid #222; color: #ddd; vertical-align: middle; }
    
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
    .badge-paid { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
    .badge-unpaid { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
    
    .action-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #2a2a2a; color: #fff; text-decoration: none; margin-left: 5px; border: 1px solid #444; transition: 0.2s; }
    .action-btn:hover { border-color: var(--gold); color: var(--gold); transform: scale(1.1); }
    .btn-wa { color: #25D366; border-color: #25D366; background: rgba(37, 211, 102, 0.1); }

    @media (max-width: 900px) {
        table, thead, tbody, th, td, tr { display: block; }
        thead { display: none; }
        tr { margin-bottom: 15px; background: #222; border-radius: 10px; padding: 15px; border: 1px solid #333; }
        td { padding: 8px 0; border: none; display: flex; justify-content: space-between; font-size: 0.9rem; }
        td::before { content: attr(data-label); font-weight: bold; color: #888; }
    }
</style>

<div class="container" style="margin-top:20px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <h2 style="color:var(--gold); margin:0;"><?php echo $title; ?></h2>
        <?php if($tab == 'sales'): ?><a href="edit_invoice.php" class="tab-btn active" style="background:var(--gold); color:#000;">+ مبيعات</a>
        <?php elseif($tab == 'purchases'): ?><a href="add_purchase.php" class="tab-btn active" style="background:var(--gold); color:#000;">+ مشتريات</a>
        <?php elseif($tab == 'salaries'): ?><a href="add_payroll.php" class="tab-btn active" style="background:var(--gold); color:#000;">+ راتب</a><?php endif; ?>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card"><h3>الإجمالي</h3><div class="num"><?php echo number_format($stat_total); ?></div></div>
        <div class="kpi-card"><h3>المدفوع</h3><div class="num" style="color:#2ecc71"><?php echo number_format($stat_paid); ?></div></div>
        <div class="kpi-card"><h3>المتبقي</h3><div class="num" style="color:#e74c3c"><?php echo number_format($stat_due); ?></div></div>
    </div>

    <div class="tabs-container">
        <a href="?tab=sales" class="tab-btn <?php echo $tab=='sales'?'active':''; ?>">مبيعات</a>
        <a href="?tab=purchases" class="tab-btn <?php echo $tab=='purchases'?'active':''; ?>">مشتريات</a>
        <a href="?tab=salaries" class="tab-btn <?php echo $tab=='salaries'?'active':''; ?>">رواتب</a>
    </div>

    <form method="GET" style="margin-bottom:20px;">
        <input type="hidden" name="tab" value="<?php echo $tab; ?>">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="بحث سريع..." style="width:100%; padding:12px; background:#000; border:1px solid #333; color:#fff; border-radius:8px;">
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr><th>#</th> <th>الجهة</th> <th>التاريخ</th> <th>الإجمالي</th> <th>المتبقي</th> <th>الحالة</th> <th>إجراءات</th></tr>
            </thead>
            <tbody>
                <?php if($res && $res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): ?>
                        <?php 
                            $id = $row['id'];
                            $name = $row['party_name'] ?? 'غير محدد';
                            $status = $row['status'] ?? '-';
                            $st_class = ($status=='paid') ? 'badge-paid' : 'badge-unpaid';
                            
                            // توليد الرابط الذكي (Token Link)
                            $token = md5($id . $secret_key);
                            $view_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/view_invoice.php?id=$id&type=$tab&token=$token";
                            
                            // رسالة الواتساب
                            $wa_link = "#";
                            if (!empty($row['party_phone'])) {
                                $msg_txt = "مرحباً {$name}،\nإليك تفاصيل الفاتورة رقم #{$id} من Arab Eagles:\n$view_url\n\nيمكنك المراجعة أو الدفع مباشرة عبر الرابط.";
                                $wa_link = get_wa_link($row['party_phone'], $msg_txt);
                            }
                        ?>
                        <tr>
                            <td data-label="#">#<?php echo $id; ?></td>
                            <td data-label="الجهة"><strong><?php echo $name; ?></strong></td>
                            <td data-label="التاريخ"><?php echo $tab=='salaries'?$row['month_year']:$row['inv_date']; ?></td>
                            <td data-label="الإجمالي"><?php echo number_format($tab=='salaries'?$row['net_salary']:$row['total_amount']); ?></td>
                            <td data-label="المتبقي" style="color:#e74c3c;"><?php echo number_format($row['remaining_amount']); ?></td>
                            <td data-label="الحالة"><span class="badge <?php echo $st_class; ?>"><?php echo $status; ?></span></td>
                            <td data-label="الإجراءات">
                                <div style="display:flex; justify-content:flex-end;">
                                    <a href="<?php echo $wa_link; ?>" target="_blank" class="action-btn btn-wa"><i class="fa-brands fa-whatsapp"></i></a>
                                    
                                    <a href="?action=duplicate&type=<?php echo ($tab=='purchases'?'purchase':'sales'); ?>&id=<?php echo $id; ?>" onclick="return confirm('تكرار الفاتورة؟')" class="action-btn" title="تكرار"><i class="fa-solid fa-copy"></i></a>
                                    
                                    <?php if($tab == 'sales'): ?>
                                        <a href="view_invoice.php?id=<?php echo $id; ?>&token=<?php echo $token; ?>&type=sales" target="_blank" class="action-btn"><i class="fa-solid fa-eye"></i></a>
                                        <a href="edit_invoice.php?id=<?php echo $id; ?>" class="action-btn"><i class="fa-solid fa-pen"></i></a>
                                    
                                    <?php elseif($tab == 'purchases'): ?>
                                        <a href="print_purchase.php?id=<?php echo $id; ?>" target="_blank" class="action-btn"><i class="fa-solid fa-print"></i></a>
                                        <a href="edit_purchase.php?id=<?php echo $id; ?>" class="action-btn"><i class="fa-solid fa-pen"></i></a>
                                    
                                    <?php elseif($tab == 'salaries'): ?>
                                        <a href="print_salary.php?id=<?php echo $id; ?>" target="_blank" class="action-btn"><i class="fa-solid fa-print"></i></a>
                                        <a href="edit_payroll.php?id=<?php echo $id; ?>" class="action-btn"><i class="fa-solid fa-pen"></i></a>
                                    <?php endif; ?>

                                    <?php if($_SESSION['role'] == 'admin'): ?>
                                    <a href="?action=delete&type=<?php echo ($tab=='purchases'?'purchase':($tab=='salaries'?'salary':'sales')); ?>&id=<?php echo $id; ?>" onclick="return confirm('حذف نهائي؟')" class="action-btn" style="color:#e74c3c; border-color:#e74c3c;"><i class="fa-solid fa-trash"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:#666;">لا توجد بيانات</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ob_end_flush(); ?>