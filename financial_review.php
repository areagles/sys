<?php
// financial_review.php - (Royal Financial Portal V1.0)
// بوابة تفاعلية للعملاء والموردين لمراجعة الحسابات، المصادقة، ورفع الإيصالات
require 'config.php';

// 1. التحقق من الرابط والتوكن
if(!isset($_GET['token']) || empty($_GET['token'])) 
    die("<div style='background:#000;color:#d4af37;height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;'><h3>⛔ الرابط غير صالح.</h3></div>");

$token = $conn->real_escape_string($_GET['token']);
$type = $_GET['type'] ?? 'client'; // client | supplier

// تحديد الجدول المستهدف
$table = ($type == 'supplier') ? 'suppliers' : 'clients';
$col_id = ($type == 'supplier') ? 'supplier_id' : 'client_id';

// جلب بيانات الطرف
$sql = "SELECT * FROM $table WHERE access_token = '$token'";
$res = $conn->query($sql);

if($res->num_rows == 0) 
    die("<div style='background:#000;color:#d4af37;height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;'><h3>⛔ الرابط منتهي الصلاحية.</h3></div>");

$entity = $res->fetch_assoc();
$entity_id = $entity['id'];
$name = $entity['name'];

// 2. معالجة الإجراءات (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // أ. المصادقة على الرصيد
    if (isset($_POST['confirm_balance'])) {
        $now = date('Y-m-d H:i:s');
        $conn->query("UPDATE $table SET last_balance_confirm = '$now' WHERE id = $entity_id");
        echo "<script>alert('✅ تم تسجيل مصادقتك على الرصيد بنجاح.'); window.location.href='?token=$token&type=$type';</script>";
    }

    // ب. رفع إيصال سداد/تحويل
    if (isset($_POST['upload_receipt']) && !empty($_FILES['receipt_file']['name'])) {
        if (!file_exists('uploads/finance')) @mkdir('uploads/finance', 0777, true);
        
        $ext = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
        $target = "uploads/finance/" . time() . "_pay_{$type}_{$entity_id}.$ext";
        
        if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target)) {
            // تسجيل ملاحظة في سجل العميل/المورد
            $note = "📎 تم رفع إيصال سداد عبر البوابة المالية.\nالتاريخ: " . date('Y-m-d') . "\nملاحظات: " . $_POST['notes'];
            $conn->query("UPDATE $table SET notes = CONCAT(IFNULL(notes, ''), '\n$note') WHERE id=$entity_id");
            
            // (اختياري) يمكن إضافة سجل في جدول إشعارات جديد، هنا نكتفي بتحديث النوتس
            echo "<script>alert('✅ تم رفع الإيصال وإبلاغ الإدارة المالية.'); window.location.href='?token=$token&type=$type';</script>";
        }
    }
}

// 3. حساب الرصيد الحي (Live Balance Calculation)
if ($type == 'client') {
    // للعميل: (رصيد افتتاحي + فواتير) - مقبوضات
    $inv_total = $conn->query("SELECT IFNULL(SUM(total_amount),0) FROM invoices WHERE client_id=$entity_id")->fetch_row()[0];
    $rec_total = $conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE client_id=$entity_id AND type='in'")->fetch_row()[0];
    $balance = $entity['opening_balance'] + $inv_total - $rec_total;
    $label_pos = "مستحق عليك";
    $label_neg = "رصيد دائن لك (فائض)";
} else {
    // للمورد: (رصيد افتتاحي + توريدات) - مدفوعات
    $pur_total = $conn->query("SELECT IFNULL(SUM(total_amount),0) FROM purchase_invoices WHERE supplier_id=$entity_id")->fetch_row()[0];
    $pay_total = $conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE supplier_id=$entity_id AND type='out'")->fetch_row()[0];
    $balance = $entity['opening_balance'] + $pur_total - $pay_total;
    $label_pos = "مستحق لك";
    $label_neg = "مدين علينا (دفعة مقدمة)";
}

// آخر 5 حركات للعرض
if($type == 'client'){
    $hist_sql = "SELECT created_at as t_date, 'فاتورة' as type, total_amount as amount FROM invoices WHERE client_id=$entity_id UNION ALL SELECT trans_date, 'سداد', amount FROM financial_receipts WHERE client_id=$entity_id AND type='in' ORDER BY t_date DESC LIMIT 5";
} else {
    $hist_sql = "SELECT created_at as t_date, 'توريد' as type, total_amount as amount FROM purchase_invoices WHERE supplier_id=$entity_id UNION ALL SELECT trans_date, 'دفعة', amount FROM financial_receipts WHERE supplier_id=$entity_id AND type='out' ORDER BY t_date DESC LIMIT 5";
}
$history = $conn->query($hist_sql);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف المالي | <?php echo $name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; }
        body { background: var(--bg); color: var(--text); font-family: 'Cairo', sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid var(--gold); padding-bottom: 15px; }
        .brand { color: var(--gold); font-size: 1.5rem; font-weight: bold; }
        
        .balance-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #252525 100%);
            padding: 30px; border-radius: 15px; text-align: center;
            border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 20px;
        }
        .balance-amount { font-size: 2.5rem; font-weight: bold; color: <?php echo $balance > 0 ? '#e74c3c' : '#2ecc71'; ?>; direction: ltr; margin: 10px 0; }
        .balance-label { font-size: 0.9rem; color: #888; }
        
        .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-confirm { background: var(--gold); color: #000; }
        .btn-upload { background: #333; color: #fff; border: 1px solid #555; }
        
        .history-box { background: var(--card); padding: 15px; border-radius: 10px; margin-top: 20px; }
        .h-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #333; }
        .h-item:last-child { border: none; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 999; justify-content: center; align-items: center; }
        .modal-content { background: #222; padding: 25px; border-radius: 10px; width: 90%; max-width: 400px; border: 1px solid var(--gold); }
        input, textarea { width: 100%; background: #111; border: 1px solid #444; color: #fff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="brand"><i class="fa-solid fa-eagle"></i> ARAB EAGLES</div>
        <div style="font-size:0.9rem; color:#888;">بوابة الخدمات المالية الذكية</div>
    </div>

    <div class="balance-card">
        <div class="balance-label">الرصيد الحالي (حتى اللحظة)</div>
        <div class="balance-amount"><?php echo number_format(abs($balance), 2); ?></div>
        <div style="color: <?php echo $balance > 0 ? '#e74c3c' : '#2ecc71'; ?>; font-size:0.9rem;">
            <?php echo $balance > 0 ? $label_pos : $label_neg; ?>
        </div>

        <?php if(!empty($entity['last_balance_confirm'])): ?>
            <div style="margin-top:15px; font-size:0.8rem; color:#2ecc71; background:rgba(46, 204, 113, 0.1); padding:8px; border-radius:5px;">
                <i class="fa-solid fa-check-circle"></i> تمت المصادقة بتاريخ: <?php echo $entity['last_balance_confirm']; ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <button onclick="document.getElementById('confirmModal').style.display='flex'" class="btn btn-confirm">
            <i class="fa-solid fa-handshake"></i> مصادقة الرصيد
        </button>
        <button onclick="document.getElementById('uploadModal').style.display='flex'" class="btn btn-upload">
            <i class="fa-solid fa-file-invoice-dollar"></i> رفع إيصال
        </button>
    </div>

    <div class="history-box">
        <h4 style="margin:0 0 10px 0; color:var(--gold); border-bottom:1px solid #333; padding-bottom:5px;">📋 آخر التحركات المالية</h4>
        <?php while($h = $history->fetch_assoc()): ?>
        <div class="h-item">
            <div>
                <div style="font-weight:bold;"><?php echo $h['type']; ?></div>
                <div style="font-size:0.8rem; color:#888;"><?php echo date('Y-m-d', strtotime($h['t_date'])); ?></div>
            </div>
            <div style="font-family:sans-serif;"><?php echo number_format($h['amount'], 2); ?></div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<div id="confirmModal" class="modal">
    <div class="modal-content" style="text-align:center;">
        <h3 style="color:var(--gold);">✅ تأكيد المصادقة</h3>
        <p>هل تقر بصحة الرصيد الظاهر أعلاه (<?php echo number_format(abs($balance), 2); ?>)؟</p>
        <form method="POST">
            <button type="submit" name="confirm_balance" class="btn btn-confirm">نعم، أصادق على الرصيد</button>
            <button type="button" onclick="this.closest('.modal').style.display='none'" class="btn btn-upload" style="margin-top:5px;">إلغاء</button>
        </form>
    </div>
</div>

<div id="uploadModal" class="modal">
    <div class="modal-content">
        <h3 style="color:var(--gold); text-align:center;">📤 رفع إيصال سداد / تحويل</h3>
        <form method="POST" enctype="multipart/form-data">
            <label>صورة الإيصال:</label>
            <input type="file" name="receipt_file" required>
            <label>ملاحظات (رقم العملية / البنك):</label>
            <textarea name="notes" placeholder="اكتب تفاصيل التحويل..."></textarea>
            <button type="submit" name="upload_receipt" class="btn btn-confirm">رفع وإرسال</button>
            <button type="button" onclick="this.closest('.modal').style.display='none'" class="btn btn-upload" style="margin-top:5px;">إلغاء</button>
        </form>
    </div>
</div>

</body>
</html>