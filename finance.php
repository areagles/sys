<?php
// finance.php - (Royal Finance V26.0 - Smart FIFO Engine & Mobile AI)
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. الذكاء السياقي: القيم الافتراضية
$default_type = isset($_GET['def_type']) ? $_GET['def_type'] : 'in';
$default_cat  = isset($_GET['def_cat'])  ? $_GET['def_cat']  : 'general';
$default_emp  = isset($_GET['emp_id'])   ? intval($_GET['emp_id']) : '';
$default_pid  = isset($_GET['payroll_id']) ? intval($_GET['payroll_id']) : ''; 
$default_inv  = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : ''; 
$default_sup  = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : ''; 

// العنوان والأيقونة
$page_title = "تسجيل حركة مالية";
$icon_class = "fa-solid fa-pen-to-square";

if($default_cat == 'salary' || $default_cat == 'loan') {
    $page_title = "💸 صرف راتب / سلفة موظف";
    $icon_class = "fa-solid fa-user-clock";
} elseif($default_cat == 'supplier') {
    $page_title = "📦 تسجيل فاتورة مشتريات / سداد مورد";
    $icon_class = "fa-solid fa-truck-field";
}

/* ==================================================
   1. المحرك المحاسبي (Smart Engine - FIFO Logic)
   ================================================== */
function recalculateSalesInvoice($conn, $invoice_id) {
    if (!$invoice_id || $invoice_id == 'NULL') return;
    $invoice_id = intval($invoice_id);
    $inv = $conn->query("SELECT total_amount FROM invoices WHERE id = $invoice_id")->fetch_assoc();
    if(!$inv) return;
    
    $paid = (float)$conn->query("SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE invoice_id = $invoice_id AND type = 'in'")->fetch_row()[0];
    $remaining = round($inv['total_amount'] - $paid, 2);
    
    $status = ($remaining <= 0) ? 'paid' : (($paid > 0) ? 'partially_paid' : 'unpaid');
    if($remaining < 0) $remaining = 0;

    $conn->query("UPDATE invoices SET paid_amount = $paid, remaining_amount = $remaining, status = '$status' WHERE id = $invoice_id");
}

function recalculatePurchaseInvoice($conn, $invoice_id) {
    if (!$invoice_id || $invoice_id == 'NULL') return;
    $invoice_id = intval($invoice_id);
    
    $inv = $conn->query("SELECT total_amount FROM purchase_invoices WHERE id = $invoice_id")->fetch_assoc();
    if(!$inv) return;

    $paid = (float)$conn->query("SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE invoice_id = $invoice_id AND type = 'out'")->fetch_row()[0];
    $remaining = round($inv['total_amount'] - $paid, 2);
    
    $status = 'unpaid';
    if($remaining <= 0) { $status = 'paid'; $remaining = 0; } 
    elseif($paid > 0) { $status = 'partially_paid'; }

    $conn->query("UPDATE purchase_invoices SET paid_amount = $paid, remaining_amount = $remaining, status = '$status' WHERE id = $invoice_id");
}

function recalculatePayroll($conn, $payroll_id) {
    if (!$payroll_id || $payroll_id == 'NULL') return;
    $payroll_id = intval($payroll_id);
    $sheet = $conn->query("SELECT net_salary FROM payroll_sheets WHERE id = $payroll_id")->fetch_assoc();
    if(!$sheet) return;
    
    $paid = (float)$conn->query("SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE payroll_id = $payroll_id AND type = 'out'")->fetch_row()[0];
    $remaining = round($sheet['net_salary'] - $paid, 2);
    
    $status = 'pending';
    if ($remaining <= 0) { $status = 'paid'; $remaining = 0; } 
    elseif ($paid > 0) { $status = 'partially_paid'; }
    
    $conn->query("UPDATE payroll_sheets SET paid_amount = $paid, remaining_amount = $remaining, status = '$status' WHERE id = $payroll_id");
}

// --- توزيع تلقائي للعملاء (FIFO) معدلة: الأولوية للرصيد الافتتاحي ---
function autoAllocatePayment($conn, $client_id, $amount, $date, $desc, $user) {
    $rem = $amount;

    // 1. [جديد] التحقق من الرصيد الافتتاحي أولاً
    // نجلب قيمة الرصيد الافتتاحي للعميل
    $c_data = $conn->query("SELECT opening_balance FROM clients WHERE id = $client_id")->fetch_assoc();
    $opening_bal = $c_data ? floatval($c_data['opening_balance']) : 0;

    if ($opening_bal > 0) {
        // نحسب كم دفع العميل سابقاً "دفعات عامة" (غير مرتبطة بفواتير) لأنها هي التي تسدد الرصيد الافتتاحي
        $paid_general = $conn->query("SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE client_id = $client_id AND type = 'in' AND invoice_id IS NULL")->fetch_row()[0];
        
        // المتبقي من الرصيد الافتتاحي
        $rem_opening = $opening_bal - $paid_general;

        if ($rem_opening > 0) {
            // نخصم من الدفعة الحالية لسداد الرصيد الافتتاحي
            $pay_to_opening = ($rem >= $rem_opening) ? $rem_opening : $rem;
            
            // نسجل الحركة كدفعة عامة (invoice_id = NULL) لسداد القديم
            $conn->query("INSERT INTO financial_receipts (type, category, amount, description, trans_date, client_id, created_by) VALUES ('in', 'general', '$pay_to_opening', '$desc (سداد رصيد افتتاحي)', '$date', $client_id, '$user')");
            
            // نخصم المبلغ المسدد من إجمالي الدفعة الحالية
            $rem -= $pay_to_opening;
        }
    }

    // 2. إذا تبقى مبلغ، نوزعه على الفواتير (FIFO)
    if ($rem > 0) {
        // جلب الفواتير غير المدفوعة الأقدم فالأحدث
        $invs = $conn->query("SELECT id, remaining_amount FROM invoices WHERE client_id = $client_id AND status != 'paid' ORDER BY inv_date ASC, id ASC");
        if($invs){
            while($inv = $invs->fetch_assoc()){
                if($rem <= 0) break;
                $pay = ($rem >= $inv['remaining_amount']) ? $inv['remaining_amount'] : $rem;
                $conn->query("INSERT INTO financial_receipts (type, category, amount, description, trans_date, client_id, invoice_id, created_by) VALUES ('in', 'general', '$pay', '$desc (توزيع تلقائي)', '$date', $client_id, {$inv['id']}, '$user')");
                recalculateSalesInvoice($conn, $inv['id']);
                $rem -= $pay;
            }
        }
    }

    // 3. المبلغ المتبقي (فائض) يوضع كرصيد عام
    if($rem > 0) {
        $conn->query("INSERT INTO financial_receipts (type, category, amount, description, trans_date, client_id, created_by) VALUES ('in', 'general', '$rem', '$desc (رصيد دائن)', '$date', $client_id, '$user')");
    }
}

// --- توزيع تلقائي للموردين (FIFO - New Feature) ---
function autoAllocateSupplierPayment($conn, $supplier_id, $amount, $date, $desc, $user) {
    $rem = $amount;
    // جلب فواتير المشتريات غير المدفوعة الأقدم فالأحدث
    $invs = $conn->query("SELECT id, remaining_amount FROM purchase_invoices WHERE supplier_id = $supplier_id AND status != 'paid' ORDER BY inv_date ASC, id ASC");
    if($invs){
        while($inv = $invs->fetch_assoc()){
            if($rem <= 0) break;
            $pay = ($rem >= $inv['remaining_amount']) ? $inv['remaining_amount'] : $rem;
            // تسجيل الحركة وربطها بالفاتورة
            $conn->query("INSERT INTO financial_receipts (type, category, amount, description, trans_date, supplier_id, invoice_id, created_by) VALUES ('out', 'supplier', '$pay', '$desc (سداد تلقائي)', '$date', $supplier_id, {$inv['id']}, '$user')");
            recalculatePurchaseInvoice($conn, $inv['id']);
            $rem -= $pay;
        }
    }
    // المبلغ المتبقي كدفعة مقدمة للمورد
    if($rem > 0) {
        $conn->query("INSERT INTO financial_receipts (type, category, amount, description, trans_date, supplier_id, created_by) VALUES ('out', 'supplier', '$rem', '$desc (دفعة مقدمة)', '$date', $supplier_id, '$user')");
    }
}

// --- توزيع تلقائي للرواتب (FIFO - New Feature) ---
function autoAllocatePayrollPayment($conn, $employee_id, $amount, $date, $desc, $user) {
    $rem = $amount;
    // جلب الرواتب المعلقة الأقدم فالأحدث
    $sheets = $conn->query("SELECT id, remaining_amount FROM payroll_sheets WHERE employee_id = $employee_id AND status != 'paid' ORDER BY month_year ASC, id ASC");
    if($sheets){
        while($sheet = $sheets->fetch_assoc()){
            if($rem <= 0) break;
            $pay = ($rem >= $sheet['remaining_amount']) ? $sheet['remaining_amount'] : $rem;
            $conn->query("INSERT INTO financial_receipts (type, category, amount, description, trans_date, employee_id, payroll_id, created_by) VALUES ('out', 'salary', '$pay', '$desc (صرف تلقائي)', '$date', $employee_id, {$sheet['id']}, '$user')");
            recalculatePayroll($conn, $sheet['id']);
            $rem -= $pay;
        }
    }
    // المبلغ المتبقي كسلفة
    if($rem > 0) {
        $conn->query("INSERT INTO financial_receipts (type, category, amount, description, trans_date, employee_id, created_by) VALUES ('out', 'loan', '$rem', '$desc (سلفة جديدة)', '$date', $employee_id, '$user')");
    }
}

/* ==================================================
   2. معالجة الإجراءات (Actions)
   ================================================== */
$edit_mode = false;
$edit_data = [];
$msg = "";
$last_id = 0; // للمفاجأة (Undo)

if(isset($_GET['edit'])){
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM financial_receipts WHERE id=$id");
    if($res && $res->num_rows > 0){
        $edit_mode = true;
        $edit_data = $res->fetch_assoc();
        $default_type = $edit_data['type'];
        $default_cat = $edit_data['category'];
        $page_title = "تعديل حركة (" . ($default_type=='in'?'قبض':'صرف') . ")";
    }
}

// خاصية التكرار (Duplicate Receipt)
if(isset($_GET['duplicate'])){
    $id = intval($_GET['duplicate']);
    $res = $conn->query("SELECT * FROM financial_receipts WHERE id=$id");
    if($res && $res->num_rows > 0){
        $edit_mode = false; // وضع إضافة جديد
        $edit_data = $res->fetch_assoc();
        // تفريغ المعرفات لإنشاء حركة جديدة بنفس البيانات
        $default_type = $edit_data['type'];
        $default_cat = $edit_data['category'];
        $default_sup = $edit_data['supplier_id'];
        $default_emp = $edit_data['employee_id'];
        $page_title = "تكرار حركة مالية 🔁";
        $msg = "تم نسخ بيانات الحركة السابقة، يرجى مراجعة المبلغ والتاريخ ثم الحفظ.";
    }
}

if(isset($_GET['del'])){
    $id = intval($_GET['del']);
    $old_res = $conn->query("SELECT invoice_id, payroll_id, type FROM financial_receipts WHERE id=$id");
    if($old_res){
        $old = $old_res->fetch_assoc();
        $conn->query("DELETE FROM financial_receipts WHERE id=$id");
        
        if($old['invoice_id']){
            if($old['type'] == 'in') recalculateSalesInvoice($conn, $old['invoice_id']);
            else recalculatePurchaseInvoice($conn, $old['invoice_id']);
        }
        if($old['payroll_id']) recalculatePayroll($conn, $old['payroll_id']);
        
        header("Location: finance.php?msg=deleted"); exit;
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_trans'])){
    $type = $_POST['type']; 
    $cat = $_POST['category'] ?? 'general';
    $amt = floatval($_POST['amount']);
    $desc = $conn->real_escape_string($_POST['desc']);
    $date = $_POST['date'];
    
    $cid = !empty($_POST['client_id']) ? intval($_POST['client_id']) : "NULL";
    $iid = !empty($_POST['invoice_id']) ? intval($_POST['invoice_id']) : "NULL";
    $sid = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : "NULL";
    $eid = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : "NULL";
    $pid = !empty($_POST['payroll_id']) ? intval($_POST['payroll_id']) : "NULL"; 
    
    $user = $_SESSION['name'] ?? 'Admin';

    if(isset($_POST['trans_id']) && !empty($_POST['trans_id'])){
        // وضع التعديل (كما هو)
        $tid = intval($_POST['trans_id']);
        $old_data = $conn->query("SELECT invoice_id, payroll_id, type FROM financial_receipts WHERE id=$tid")->fetch_assoc();
        
        $sql = "UPDATE financial_receipts SET type='$type', category='$cat', amount='$amt', description='$desc', trans_date='$date', client_id=$cid, invoice_id=$iid, supplier_id=$sid, employee_id=$eid, payroll_id=$pid WHERE id=$tid";
        
        if($conn->query($sql)){
            // إعادة حساب القديم والجديد
            if($old_data['invoice_id']) { ($old_data['type']=='in') ? recalculateSalesInvoice($conn, $old_data['invoice_id']) : recalculatePurchaseInvoice($conn, $old_data['invoice_id']); }
            if($old_data['payroll_id']) recalculatePayroll($conn, $old_data['payroll_id']);
            
            if($iid !== "NULL") { ($type=='in') ? recalculateSalesInvoice($conn, $iid) : recalculatePurchaseInvoice($conn, $iid); }
            if($pid !== "NULL") recalculatePayroll($conn, $pid);
            
            header("Location: finance.php?msg=updated"); exit;
        }
    } else {
        // وضع الإضافة الجديد مع منطق FIFO
        
        // 1. عميل + دفع عام (بدون تحديد فاتورة) = توزيع تلقائي
        if($type == 'in' && $cid !== "NULL" && $iid === "NULL"){
            autoAllocatePayment($conn, $cid, $amt, $date, $desc, $user);
            header("Location: finance.php?msg=auto"); exit;
        }
        // 2. مورد + دفع عام = توزيع تلقائي (NEW)
        elseif($type == 'out' && $cat == 'supplier' && $sid !== "NULL" && $iid === "NULL"){
            autoAllocateSupplierPayment($conn, $sid, $amt, $date, $desc, $user);
            header("Location: finance.php?msg=auto_sup"); exit;
        }
        // 3. موظف + صرف عام = توزيع تلقائي (NEW)
        elseif($type == 'out' && ($cat == 'salary' || $cat == 'loan') && $eid !== "NULL" && $pid === "NULL"){
            autoAllocatePayrollPayment($conn, $eid, $amt, $date, $desc, $user);
            header("Location: finance.php?msg=auto_emp"); exit;
        } 
        // 4. إدخال عادي محدد
        else {
            $sql = "INSERT INTO financial_receipts (type, category, amount, description, trans_date, client_id, invoice_id, supplier_id, employee_id, payroll_id, created_by) VALUES ('$type', '$cat', '$amt', '$desc', '$date', $cid, $iid, $sid, $eid, $pid, '$user')";
            
            if($conn->query($sql)){
                $last_id = $conn->insert_id;
                if($iid !== "NULL") { ($type == 'in') ? recalculateSalesInvoice($conn, $iid) : recalculatePurchaseInvoice($conn, $iid); }
                if($pid !== "NULL") recalculatePayroll($conn, $pid);
                header("Location: finance.php?msg=saved&lid=$last_id"); exit;
            } else {
                $msg = "خطأ في الحفظ: " . $conn->error;
            }
        }
    }
}

// Stats
$total_in = $conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE type='in'")->fetch_row()[0];
$total_out = $conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE type='out'")->fetch_row()[0];
$net = $total_in - $total_out; 
?>

<style>
    :root { --gold: #d4af37; --card-bg: #1a1a1a; --bg-dark: #0f0f0f; }
    body { background-color: var(--bg-dark); color: #fff; font-family: 'Cairo', sans-serif; margin: 0; padding-bottom: 80px; }
    
    .container { max-width: 1400px; margin: 0 auto; padding: 10px; }
    
    /* Improved KPI Grid */
    .kpi-wrapper { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 10px; }
    .kpi-box { background: var(--card-bg); padding: 15px; border-radius: 12px; border: 1px solid #333; text-align: center; }
    .kpi-box h4 { margin: 0 0 5px; color: #888; font-size: 0.8rem; }
    .kpi-box .num { font-size: 1.4rem; font-weight: 900; }

    .main-layout { display: grid; grid-template-columns: 350px 1fr; gap: 20px; margin-top: 20px; }
    
    .panel { background: var(--card-bg); padding: 20px; border-radius: 15px; border: 1px solid #333; height: fit-content; position: sticky; top: 10px; }
    
    input, select, textarea { 
        width: 100%; background: #050505; border: 1px solid #444; color: #fff; 
        padding: 12px; border-radius: 8px; margin-bottom: 15px; font-family: 'Cairo'; box-sizing: border-box; 
    }
    input:focus, select:focus { border-color: var(--gold); outline: none; }
    
    .btn-submit { width: 100%; padding: 15px; background: linear-gradient(45deg, var(--gold), #b8860b); border: none; font-weight: bold; border-radius: 8px; cursor: pointer; color: #000; font-size: 1rem; }

    /* Mobile First Tables */
    .journal-row { 
        background: #151515; border: 1px solid #333; padding: 15px; border-radius: 10px; 
        margin-bottom: 10px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: 0.2s;
    }
    .journal-row:hover { transform: translateX(-5px); border-right: 3px solid var(--gold); }
    
    .j-info { flex: 1; min-width: 200px; }
    .j-date { font-size: 0.8rem; color: #666; display: flex; align-items: center; gap: 5px; }
    .j-desc { font-weight: bold; font-size: 1rem; margin: 5px 0; color: #eee; }
    .j-meta { font-size: 0.85rem; display: flex; gap: 10px; }
    
    .j-amount { text-align: left; min-width: 100px; font-weight: 900; font-size: 1.2rem; }
    .j-actions { width: 100%; margin-top: 10px; padding-top: 10px; border-top: 1px solid #222; display: flex; justify-content: flex-end; gap: 15px; }
    .j-actions a { color: #888; text-decoration: none; font-size: 1rem; display: flex; align-items: center; gap: 5px; }
    .j-actions a:hover { color: var(--gold); }

    .tag { padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
    .tag.in { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }
    .tag.out { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

    .hidden { display: none; }
    
    /* Smart Surprise: Undo Notification */
    .toast-undo {
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: #222; border: 1px solid #2ecc71; padding: 15px 25px; border-radius: 50px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; gap: 15px;
        animation: slideUp 0.3s ease;
    }
    @keyframes slideUp { from {bottom: -50px; opacity:0;} to {bottom: 20px; opacity:1;} }

    @media (max-width: 900px) {
        .main-layout { grid-template-columns: 1fr; }
        .panel { position: static; margin-bottom: 20px; }
        .j-actions { justify-content: space-between; }
    }
</style>

<div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2 style="color:var(--gold); margin:0;">الإدارة المالية</h2>
        <div style="font-size:0.8rem; color:#666;">Arab Eagles System</div>
    </div>

    <div class="kpi-wrapper">
        <div class="kpi-box" style="border-bottom: 2px solid #2ecc71">
            <h4>قبض (وارد)</h4>
            <div class="num" style="color:#2ecc71"><?php echo number_format($total_in); ?></div>
        </div>
        <div class="kpi-box" style="border-bottom: 2px solid #e74c3c">
            <h4>صرف (صادر)</h4>
            <div class="num" style="color:#e74c3c"><?php echo number_format($total_out); ?></div>
        </div>
        <div class="kpi-box" style="border-bottom: 2px solid var(--gold)">
            <h4>صافي الخزينة</h4>
            <div class="num" style="color:var(--gold)"><?php echo number_format($net); ?></div>
        </div>
    </div>
    
    <?php if(isset($_GET['msg']) && isset($_GET['lid'])): ?>
    <div class="toast-undo">
        <span style="color:#2ecc71;"><i class="fa-solid fa-check-circle"></i> تم الحفظ بنجاح</span>
        <a href="?del=<?php echo $_GET['lid']; ?>" style="color:#e74c3c; font-weight:bold; text-decoration:none; border-right:1px solid #444; padding-right:15px;">
            <i class="fa-solid fa-rotate-left"></i> تراجع (Undo)
        </a>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:#888; cursor:pointer;">✕</button>
    </div>
    <?php endif; ?>

    <div class="main-layout">
        
        <div class="panel">
            <h3 style="color:var(--gold); margin-top:0;">
                <i class="<?php echo $icon_class; ?>"></i> <span><?php echo $page_title; ?></span>
            </h3>
            
            <?php 
                if(isset($_GET['msg']) && $_GET['msg']=='auto') echo "<p style='color:#f1c40f; font-size:0.9rem;'>✨ تم توزيع المبلغ آلياً على الفواتير القديمة (FIFO).</p>"; 
                if(isset($_GET['msg']) && $_GET['msg']=='auto_sup') echo "<p style='color:#f1c40f; font-size:0.9rem;'>✨ تم سداد فواتير المورد القديمة آلياً (FIFO).</p>";
                if(isset($_GET['msg']) && $_GET['msg']=='auto_emp') echo "<p style='color:#f1c40f; font-size:0.9rem;'>✨ تم صرف الرواتب المتأخرة آلياً (FIFO).</p>";
            ?>

            <form method="POST">
                <?php if($edit_mode): ?><input type="hidden" name="trans_id" value="<?php echo $edit_data['id']; ?>"><?php endif; ?>

                <div style="<?php echo isset($_GET['def_type']) ? 'opacity:0.7;' : ''; ?>">
                    <label>نوع الحركة</label>
                    <select name="type" id="t_type" onchange="toggleFields()">
                        <option value="in" <?php if($default_type=='in') echo 'selected'; ?>>📥 قبض (وارد)</option>
                        <option value="out" <?php if($default_type=='out') echo 'selected'; ?>>📤 صرف (صادر)</option>
                    </select>
                </div>

                <div id="category_div" class="hidden" style="<?php echo isset($_GET['def_cat']) ? 'opacity:0.7;' : ''; ?>">
                    <label>التصنيف</label>
                    <select name="category" id="t_cat" onchange="toggleFields()">
                        <option value="general" <?php if($default_cat=='general') echo 'selected'; ?>>مصروفات عامة / نثرية</option>
                        <option value="supplier" <?php if($default_cat=='supplier') echo 'selected'; ?>>سداد لمورد (مشتريات)</option>
                        <option value="salary" <?php if($default_cat=='salary') echo 'selected'; ?>>راتب شهري</option>
                        <option value="loan" <?php if($default_cat=='loan') echo 'selected'; ?>>سلفة موظف</option>
                    </select>
                </div>

                <label>المبلغ (EGP)</label>
                <input type="number" name="amount" step="0.01" required value="<?php echo $edit_mode||isset($_GET['duplicate']) ? $edit_data['amount'] : (isset($_GET['amount'])?$_GET['amount']:''); ?>" placeholder="0.00" style="font-size:1.2rem; font-weight:bold; color:var(--gold); border-color:var(--gold);">

                <div id="client_div" class="hidden">
                    <label>العميل</label>
                    <select name="client_id" id="client_select" onchange="populateInvoices('sales')">
                        <option value="">-- اختر العميل --</option>
                        <?php 
                        $cl = $conn->query("SELECT id, name FROM clients");
                        while($c=$cl->fetch_assoc()){
                            $sel = (($edit_mode||isset($_GET['duplicate'])) && $edit_data['client_id'] == $c['id']) ? 'selected' : '';
                            echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div id="supplier_div" class="hidden">
                    <label>المورد / الشركة</label>
                    <select name="supplier_id" id="supplier_select" onchange="filterPurchaseInvoices()">
                        <option value="">-- اختر المورد --</option>
                        <?php 
                        $sups = $conn->query("SELECT id, name FROM suppliers");
                        if($sups) while($s=$sups->fetch_assoc()){
                            $sel = (($edit_mode||isset($_GET['duplicate'])) && $edit_data['supplier_id'] == $s['id']) ? 'selected' : (($default_sup == $s['id']) ? 'selected' : '');
                            echo "<option value='{$s['id']}' $sel>{$s['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div id="employee_div" class="hidden">
                    <label>الموظف</label>
                    <select name="employee_id" id="emp_select" onchange="fetchPayrolls(this.value)">
                        <option value="">-- اختر الموظف --</option>
                        <?php 
                        $emps = $conn->query("SELECT id, full_name as name FROM users");
                        while($e=$emps->fetch_assoc()){
                            $sel = (($edit_mode||isset($_GET['duplicate'])) && $edit_data['employee_id'] == $e['id']) ? 'selected' : (($default_emp == $e['id']) ? 'selected' : '');
                            echo "<option value='{$e['id']}' $sel>{$e['name']}</option>";
                        }
                        ?>
                    </select>
                    
                    <div id="payroll_select_div" style="display:none; margin-top:10px; border-right:3px solid #f1c40f; padding-right:10px;">
                        <label style="color:#f1c40f; font-size:0.85rem;">تخصيص راتب (اختياري)</label>
                        <select name="payroll_id" id="payroll_id">
                            <option value="">-- 🤖 صرف آلي للأقدم (FIFO) --</option>
                        </select>
                    </div>
                </div>

                <div id="invoice_div" class="hidden">
                    <label id="inv_label" style="color:#2ecc71;">ربط بفاتورة (اختياري)</label>
                    <select name="invoice_id" id="invoice_select">
                        <option value="">-- 🤖 سداد آلي للأقدم (FIFO) --</option>
                    </select>
                </div>

                <label>التاريخ</label>
                <input type="date" name="date" value="<?php echo ($edit_mode||isset($_GET['duplicate'])) ? $edit_data['trans_date'] : date('Y-m-d'); ?>" required>

                <label>التفاصيل</label>
                <textarea name="desc" rows="2" required placeholder="شرح مختصر..." style="font-size:0.9rem;"><?php echo ($edit_mode||isset($_GET['duplicate'])) ? $edit_data['description'] : (isset($_GET['desc'])?$_GET['desc']:''); ?></textarea>

                <button type="submit" name="save_trans" class="btn-submit">
                    <?php echo $edit_mode ? 'تحديث العملية 🔄' : 'حفظ العملية ✅'; ?>
                </button>
            </form>
        </div>

        <div style="background:transparent;">
            <div style="margin-bottom:15px; display:flex; gap:10px;">
                <input type="text" id="liveSearch" placeholder="🔍 بحث في الحركات..." style="margin:0; width:100%; border-radius:25px;">
            </div>
            
            <div id="journalContainer">
                <?php 
                $q = "SELECT t.*, c.name as cname, s.name as sname, u.full_name as ename 
                      FROM financial_receipts t 
                      LEFT JOIN clients c ON t.client_id=c.id 
                      LEFT JOIN suppliers s ON t.supplier_id=s.id
                      LEFT JOIN users u ON t.employee_id=u.id 
                      ORDER BY t.trans_date DESC, t.id DESC LIMIT 50";
                $hist = $conn->query($q);
                
                if($hist && $hist->num_rows > 0):
                    while($row = $hist->fetch_assoc()):
                        $in = ($row['type'] == 'in');
                        $cat_map = ['general'=>'عام', 'supplier'=>'موردين', 'salary'=>'رواتب', 'loan'=>'سلف'];
                        $cat_txt = $cat_map[$row['category']] ?? 'عام';
                ?>
                <div class="journal-row">
                    <div class="j-info">
                        <div class="j-date">
                            <span class="tag <?php echo $in?'in':'out'; ?>"><?php echo $cat_txt; ?></span>
                            <?php echo $row['trans_date']; ?>
                            <span style="color:#444;">#<?php echo $row['id']; ?></span>
                        </div>
                        <div class="j-desc"><?php echo $row['description']; ?></div>
                        <div class="j-meta">
                            <?php 
                                if($row['cname']) echo "<span style='color:var(--gold);'>👤 {$row['cname']}</span>";
                                if($row['sname']) echo "<span style='color:#e74c3c;'>🚛 {$row['sname']}</span>";
                                if($row['ename']) echo "<span style='color:#3498db;'>👔 {$row['ename']}</span>";
                                if($row['invoice_id']) echo "<span style='color:#777;'>📄 ف#{$row['invoice_id']}</span>";
                            ?>
                        </div>
                    </div>
                    <div class="j-amount" style="color:<?php echo $in?'#2ecc71':'#e74c3c'; ?>">
                        <?php echo ($in?'+':'-') . number_format($row['amount']); ?>
                    </div>
                    <div class="j-actions">
                        <a href="?duplicate=<?php echo $row['id']; ?>" title="تكرار العملية"><i class="fa-solid fa-copy"></i> تكرار</a>
                        <a href="?edit=<?php echo $row['id']; ?>"><i class="fa-solid fa-pen"></i> تعديل</a>
                        <a href="?del=<?php echo $row['id']; ?>" onclick="return confirm('حذف نهائي؟')" style="color:#e74c3c;"><i class="fa-solid fa-trash"></i> حذف</a>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div style="text-align:center; padding:30px; color:#666;">لا توجد حركات مسجلة</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// JSON Data
let payrolls = <?php 
    $all = []; $qp = $conn->query("SELECT id, employee_id, month_year, remaining_amount FROM payroll_sheets WHERE status != 'paid'");
    if($qp) while($p = $qp->fetch_assoc()) $all[] = $p; echo json_encode($all);
?>;
let salesInvoices = <?php 
    $all = []; $q = $conn->query("SELECT id, client_id, remaining_amount FROM invoices WHERE status != 'paid'");
    if($q) while($row = $q->fetch_assoc()) $all[] = $row; echo json_encode($all);
?>;
let purchaseInvoices = <?php 
    $all = []; $q = $conn->query("SELECT id, supplier_id, remaining_amount FROM purchase_invoices WHERE status != 'paid'");
    if($q) while($row = $q->fetch_assoc()) $all[] = $row; echo json_encode($all);
?>;

let defaultPid = "<?php echo $default_pid; ?>";
let defaultInv = "<?php echo $default_inv; ?>";

function toggleFields() {
    let type = document.getElementById('t_type').value;
    let cat = document.getElementById('t_cat').value;
    
    let els = {
        cat: document.getElementById('category_div'),
        client: document.getElementById('client_div'),
        supplier: document.getElementById('supplier_div'),
        emp: document.getElementById('employee_div'),
        inv: document.getElementById('invoice_div')
    };

    // Hide all
    for (let k in els) els[k].classList.add('hidden');

    if(type === 'in') {
        els.client.classList.remove('hidden');
        els.inv.classList.remove('hidden');
        populateInvoices('sales'); 
    } else {
        els.cat.classList.remove('hidden');
        if(cat === 'supplier') {
            els.supplier.classList.remove('hidden');
            els.inv.classList.remove('hidden');
            filterPurchaseInvoices();
        } else if (cat === 'salary' || cat === 'loan') {
            els.emp.classList.remove('hidden');
            fetchPayrolls(document.getElementById('emp_select').value);
        } 
    }
}

function populateInvoices(mode) {
    let select = document.getElementById('invoice_select');
    select.innerHTML = '<option value="">-- 🤖 سداد آلي للأقدم (FIFO) --</option>';
    let clientId = document.getElementById('client_select').value;

    if(mode === 'sales' && clientId) {
        salesInvoices.forEach(i => {
            if(i.client_id == clientId) {
                let opt = document.createElement('option');
                opt.value = i.id;
                opt.text = `فاتورة #${i.id} (متبقي: ${i.remaining_amount})`;
                if(i.id == defaultInv) opt.selected = true;
                select.add(opt);
            }
        });
    }
}

function filterPurchaseInvoices() {
    let select = document.getElementById('invoice_select');
    select.innerHTML = '<option value="">-- 🤖 سداد آلي للأقدم (FIFO) --</option>';
    let supId = document.getElementById('supplier_select').value;
    
    purchaseInvoices.forEach(i => {
        if(!supId || i.supplier_id == supId) {
            let opt = document.createElement('option');
            opt.value = i.id;
            opt.text = `شراء #${i.id} (متبقي: ${i.remaining_amount})`;
            if(i.id == defaultInv) opt.selected = true;
            select.add(opt);
        }
    });
}

function fetchPayrolls(empId) {
    let select = document.getElementById('payroll_id');
    select.innerHTML = '<option value="">-- 🤖 صرف آلي للأقدم (FIFO) --</option>';
    let div = document.getElementById('payroll_select_div');
    
    let found = false;
    payrolls.forEach(p => {
        if(p.employee_id == empId) {
            let opt = document.createElement('option');
            opt.value = p.id;
            opt.text = `شهر ${p.month_year} (متبقي: ${p.remaining_amount})`;
            if(p.id == defaultPid) opt.selected = true;
            select.add(opt);
            found = true;
        }
    });
    if(found || empId) div.style.display = 'block'; else div.style.display = 'none';
}

// Live Search Filter
document.getElementById('liveSearch').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let rows = document.getElementsByClassName("journal-row");
    for (let i = 0; i < rows.length; i++) {
        let text = rows[i].innerText.toUpperCase();
        rows[i].style.display = text.indexOf(filter) > -1 ? "" : "none";
    }
});

window.onload = toggleFields;
</script>

<?php include 'footer.php'; ob_end_flush(); ?>