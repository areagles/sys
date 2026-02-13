<?php
// view_invoice.php - (Royal View V3.0 - Unified Design)
ob_start();
ini_set('display_errors', 0); 
require 'config.php'; 

// 1. الأمان والتحقق
$id = intval($_GET['id']);
$token = $_GET['token'] ?? '';
$secret_key = "Eagle_Secret_Key_99"; 

if ($token !== md5($id . $secret_key)) {
    die("<div style='height:100vh; display:flex; align-items:center; justify-content:center; background:#000; color:#d4af37; font-family:sans-serif;'>
            <div style='text-align:center;'>
                <h1 style='font-size:3rem; margin:0;'>⛔</h1>
                <h2 style='color:#fff;'>رابط غير صالح</h2>
            </div>
         </div>");
}

// 2. جلب البيانات
$res = $conn->query("SELECT i.*, c.name as client_name, c.phone as client_phone, c.address as client_addr FROM invoices i JOIN clients c ON i.client_id=c.id WHERE i.id=$id");
if(!$res || $res->num_rows==0) die("الفاتورة غير موجودة");
$inv = $res->fetch_assoc();
$items = json_decode($inv['items_json'], true);

// 3. معالجة الردود
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'approve') {
        $note = "\n[✅ العميل]: تمت الموافقة عبر الرابط الإلكتروني.";
        $conn->query("UPDATE invoices SET notes = CONCAT(IFNULL(notes, ''), '$note') WHERE id=$id");
        echo "<script>alert('شكراً لك! تم تأكيد الاستلام.'); window.location.reload();</script>";
    } elseif ($_POST['action'] == 'reject') {
        $reason = $conn->real_escape_string($_POST['reason']);
        $note = "\n[⚠️ ملاحظة عميل]: $reason";
        $conn->query("UPDATE invoices SET notes = CONCAT(IFNULL(notes, ''), '$note') WHERE id=$id");
        echo "<script>alert('تم إرسال الملاحظات للإدارة.'); window.location.reload();</script>";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة #<?php echo $id; ?> - Arab Eagles</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- المتغيرات الملكية --- */
        :root { 
            --bg-body: #050505; --card-bg: #121212; --gold: #d4af37; 
            --text-main: #ffffff; --text-sub: #aaaaaa; --border: #333;
        }

        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Cairo', sans-serif; margin: 0; padding: 15px; padding-bottom: 80px; }
        
        .container { 
            max-width: 800px; margin: 0 auto; 
            background: var(--card-bg); 
            border-radius: 15px; 
            box-shadow: 0 0 50px rgba(0,0,0,0.8);
            position: relative; 
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--gold), #b8860b, var(--gold));
        }

        .invoice-box { padding: 40px; }

        /* --- الهيدر المركزي --- */
        .header { 
            display: flex; justify-content: space-between; align-items: center; 
            border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; 
        }
        .header-side { flex: 1; text-align: center; }
        .header-side.right { text-align: right; }
        .header-side.left { text-align: left; }

        .invoice-title { font-size: 2rem; font-weight: 900; color: var(--gold); margin: 0; line-height: 1; }
        .invoice-id { font-size: 1rem; color: #fff; letter-spacing: 2px; margin-top: 5px; opacity: 0.8; font-family: monospace; }
        .logo-img { width: 300px; display: block; margin: 0 auto; }
        .date-item { font-size: 0.85rem; color: var(--text-sub); }
        .date-item strong { color: #fff; }

        /* بيانات العميل */
        .client-section { margin-bottom: 30px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 10px; border-right: 3px solid var(--gold); }
        .section-label { font-size: 0.8rem; color: var(--gold); text-transform: uppercase; font-weight: bold; }
        .client-name { font-size: 1.3rem; font-weight: 700; margin: 5px 0; color: #fff; }
        .client-details { font-size: 0.9rem; color: var(--text-sub); }

        /* الجدول */
        .items-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; }
        .items-table th { background: rgba(212, 175, 55, 0.1); color: var(--gold); padding: 12px; text-align: center; font-size: 0.9rem; border-bottom: 1px solid var(--gold); }
        .items-table td { padding: 12px; border-bottom: 1px solid var(--border); text-align: center; font-size: 0.95rem; color: #eee; }
        .items-table td.desc { text-align: right; width: 50%; color: #fff; }
        
        /* الإجماليات */
        .totals-area { 
            background: #0a0a0a; border: 1px solid var(--border); 
            border-radius: 10px; padding: 20px; margin-bottom: 30px;
        }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.95rem; color: #ccc; }
        .final-total { 
            display: flex; justify-content: space-between; 
            border-top: 1px dashed #444; padding-top: 10px; margin-top: 10px; 
            font-size: 1.2rem; font-weight: 800; color: var(--gold); 
        }

        /* الفوتر */
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-sub); }

        /* ختم الحالة */
        .status-stamp { 
            position: absolute; top: 150px; left: 50%; transform: translateX(-50%) rotate(-10deg);
            padding: 10px 40px; border: 4px double; font-weight: 900; text-transform: uppercase; 
            opacity: 0.15; font-size: 3rem; letter-spacing: 10px; pointer-events: none;
        }
        .st-paid { color: #2ecc71; border-color: #2ecc71; opacity: 0.3; }
        .st-unpaid { color: #c0392b; border-color: #c0392b; }
        .st-partially { color: #f39c12; border-color: #f39c12; }

        /* Actions Bar */
        .actions-bar {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(10px);
            padding: 10px 20px; border-radius: 50px; width: 90%; max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8); display: flex; gap: 10px; justify-content: center;
            border: 1px solid var(--gold); z-index: 1000;
        }
        .btn { border: none; padding: 10px 20px; border-radius: 30px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.9rem; font-family: 'Cairo'; color: #fff; transition: 0.3s; flex: 1; text-decoration: none; white-space: nowrap; }
        .btn:hover { transform: translateY(-3px); }
        
        .btn-insta { background: linear-gradient(45deg, #4c1d95, #6d28d9); box-shadow: 0 0 15px rgba(76, 29, 149, 0.4); }
        .btn-approve { background: #27ae60; }
        .btn-reject { background: #c0392b; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: #1a1a1a; border: 1px solid var(--gold); padding: 25px; border-radius: 15px; width: 90%; max-width: 400px; text-align: center; }
        .modal-content h3 { color: var(--gold); margin-top: 0; }
        textarea { width: 100%; height: 100px; padding: 10px; margin: 15px 0; border: 1px solid #444; border-radius: 8px; background: #050505; color: #fff; font-family: 'Cairo'; outline: none; }
        textarea:focus { border-color: var(--gold); }

        /* --- الموبايل --- */
        @media (max-width: 768px) {
            .invoice-box { padding: 20px 15px; }
            .header { flex-direction: column; text-align: center; gap: 15px; }
            .header-side { width: 100%; text-align: center !important; }
            .header-side.center { order: -1; }
            .logo-img { width: 200px; }
            
            .items-table { display: block; overflow-x: auto; white-space: nowrap; }
            .actions-bar { flex-wrap: wrap; border-radius: 20px; padding: 15px; }
            .btn { font-size: 0.8rem; padding: 12px; }
        }

        /* --- الطباعة --- */
        @media print {
            body { background: #fff; color: #000; padding: 0; }
            .container { box-shadow: none; border: none; margin: 0; max-width: 100%; width: 100%; background: #fff; color: #000; border-radius: 0; }
            .container::before { display: none; }
            
            .header { border-bottom: 2px solid #000; flex-direction: row; }
            .header-side { text-align: inherit !important; }
            .header-side.right { text-align: right !important; }
            .invoice-title, .section-label { color: #000; text-shadow: none; }
            .invoice-id, .date-item, .client-name, .client-details { color: #000; }
            .logo-img { width: 150px; }
            
            .client-section { background: #fff; border: 1px solid #ddd; border-right: 4px solid #000; }
            .items-table th { background: #f0f0f0; color: #000; border-bottom: 2px solid #000; }
            .items-table td { color: #000; border-bottom: 1px solid #ddd; }
            
            .totals-area { background: #fff; border: 1px solid #000; color: #000; }
            .total-row, .final-total { color: #000; }
            
            .footer { color: #000; border-top: 1px solid #ccc; }
            .actions-bar, .modal { display: none !important; }
            .status-stamp { opacity: 0.1; color: #000 !important; border-color: #000 !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-box">
        
        <div class="status-stamp st-<?php echo $inv['status']; ?>">
            <?php 
            if($inv['status']=='paid') echo 'PAID';
            elseif($inv['status']=='unpaid') echo 'UNPAID';
            else echo 'PARTIAL';
            ?>
        </div>

        <div class="header">
            <div class="header-side right">
                <h1 class="invoice-title">فاتورة مبيعات</h1>
                <div class="invoice-id">#<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="header-side center">
                <img src="assets/img/Logo.png" alt="Arab Eagles Logo" class="logo-img">
            </div>
            <div class="header-side left">
                <div class="date-item"><strong>التاريخ:</strong> <?php echo $inv['inv_date']; ?></div>
            </div>
        </div>

        <div class="client-section">
            <div class="section-label">فاتورة إلى:</div>
            <h2 class="client-name"><?php echo $inv['client_name']; ?></h2>
            <p class="client-details">
                <i class="fa-solid fa-phone"></i> <?php echo $inv['client_phone']; ?> 
                <?php if($inv['client_address']) echo " | " . $inv['client_address']; ?>
            </p>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="50%">البيان</th>
                    <th width="15%">الكمية</th>
                    <th width="15%">السعر</th>
                    <th width="15%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php $count=1; foreach($items as $item): ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td class="desc"><?php echo $item['desc']; ?></td>
                    <td><?php echo $item['qty']; ?></td>
                    <td><?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-area">
            <div class="total-row">
                <span>المجموع الفرعي</span>
                <span><?php echo number_format($inv['sub_total'], 2); ?></span>
            </div>
            <?php if($inv['discount'] > 0): ?>
            <div class="total-row" style="color:#c0392b;">
                <span>خصم</span>
                <span>-<?php echo number_format($inv['discount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="final-total">
                <span>الإجمالي النهائي</span>
                <span><?php echo number_format($inv['total_amount'], 2); ?> <small>EGP</small></span>
            </div>
        </div>

        <?php if(!empty($inv['notes'])): ?>
        <div style="margin-top:20px; font-size:0.9rem; color:var(--text-sub); border-top:1px dashed #333; padding-top:10px;">
            <strong style="color:var(--gold);">ملاحظات:</strong> <?php echo nl2br($inv['notes']); ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>شكراً لثقتكم بنا | ARAB EAGLES FOR PRINTING, MARKETING, INDUSTRY AND TRADING</p>
            <p><a href="https://www.areagles.com" style="color:var(--gold); text-decoration:none;">www.areagles.com</a> | info@areagles.com</p>
        </div>

    </div>
</div>

<div class="actions-bar">
    <a href="https://ipn.eg/S/eagles.bm/instapay/3MH6E0" target="_blank" class="btn btn-insta">
        <i class="fa-solid fa-bolt"></i> دفع InstaPay
    </a>
    
    <form method="POST" style="display:contents;">
        <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirm('تأكيد استلام الفاتورة؟')">
            <i class="fa-solid fa-check-circle"></i> موافقة
        </button>
    </form>
    
    <button type="button" class="btn btn-reject" onclick="document.getElementById('rejModal').style.display='flex'">
        <i class="fa-solid fa-triangle-exclamation"></i> ملاحظة
    </button>
</div>

<div id="rejModal" class="modal">
    <div class="modal-content">
        <h3><i class="fa-solid fa-pen-to-square"></i> إضافة ملاحظة</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <textarea name="reason" required placeholder="هل هناك أي خطأ في الفاتورة؟ اكتبه هنا..."></textarea>
            <div style="display:flex; gap:10px;">
                <button class="btn btn-reject" style="flex:1;">إرسال</button>
                <button type="button" class="btn" style="flex:1; background:#333;" onclick="document.getElementById('rejModal').style.display='none'">إلغاء</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>