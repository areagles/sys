<?php
// view_quote.php - النسخة الماسية (متوافق 100% مع الموبايل)
ob_start();
ini_set('display_errors', 0); 
require 'config.php'; 

// =================================================================
// 🛠️ وحدة التصليح الذاتي
// =================================================================
$check_col = $conn->query("SHOW COLUMNS FROM quotes LIKE 'client_comment'");
if($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE quotes ADD COLUMN client_comment TEXT NULL DEFAULT NULL AFTER status");
}
// =================================================================

// 1. تحديد طريقة الوصول
$quote = null;
$access_mode = ''; 

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    $sql = "SELECT q.*, c.name as client_name, c.phone as client_phone, c.address as client_addr 
            FROM quotes q JOIN clients c ON q.client_id = c.id 
            WHERE q.access_token = '$token'";
    $access_mode = 'token';
} elseif (isset($_GET['id'])) {
    require_once 'auth.php'; 
    $id = intval($_GET['id']);
    $sql = "SELECT q.*, c.name as client_name, c.phone as client_phone, c.address as client_addr 
            FROM quotes q JOIN clients c ON q.client_id = c.id 
            WHERE q.id = $id";
    $access_mode = 'id';
} else {
    die("<div style='text-align:center; padding:50px; color:white; background:#000;'>رابط غير صالح.</div>");
}

$res = $conn->query($sql);
if ($res->num_rows == 0) die("<div style='text-align:center; padding:50px; color:white; background:#000;'>العرض غير موجود.</div>");
$quote = $res->fetch_assoc();
$quote_id = $quote['id'];

// 2. معالجة القرار
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action']; 
    $client_note = isset($_POST['client_note']) ? $conn->real_escape_string($_POST['client_note']) : '';
    $status = ($action == 'approve') ? 'approved' : 'rejected';
    
    $update = $conn->query("UPDATE quotes SET status='$status', client_comment='$client_note' WHERE id=$quote_id");
    
    if ($update) {
        $redirect_url = ($access_mode == 'token') ? "?token={$quote['access_token']}" : "?id=$quote_id";
        header("Location: " . $redirect_url);
        exit;
    }
}

$items = $conn->query("SELECT * FROM quote_items WHERE quote_id = $quote_id");

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$public_link = "$protocol://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/view_quote.php?token=" . $quote['access_token'];
$wa_msg = "مرحباً أ/{$quote['client_name']}،\nمرفق عرض السعر من Arab Eagles:\n$public_link";
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض سعر #<?php echo $quote_id; ?> | Arab Eagles</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- المتغيرات الملكية --- */
        :root { 
            --bg-body: #050505; --card-bg: #121212; --gold: #d4af37; 
            --text-main: #ffffff; --text-sub: #aaaaaa; --border: #333;
        }

        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Cairo', sans-serif; margin: 0; padding: 15px; }
        
        .container { 
            max-width: 800px; margin: 0 auto; 
            background: var(--card-bg); 
            border-radius: 15px; 
            box-shadow: 0 0 50px rgba(0,0,0,0.8);
            position: relative; margin-bottom: 100px; 
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--gold), #b8860b, var(--gold));
        }

        .invoice-box { padding: 40px; }

        /* --- الهيدر المركزي الجديد --- */
        .header { 
            display: flex; justify-content: space-between; align-items: center; 
            border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; 
        }

        .header-side { flex: 1; text-align: center; }
        .header-side.right { text-align: right; }
        .header-side.left { text-align: left; }

        .invoice-title { font-size: 2rem; font-weight: 900; color: var(--gold); margin: 0; line-height: 1; }
        .invoice-id { font-size: 1rem; color: #fff; letter-spacing: 2px; margin-top: 5px; opacity: 0.8; font-family: monospace; }

        .logo-img { width: 300px; max-width: 100%; display: block; margin: 0 auto; }

        .date-item { font-size: 0.85rem; color: var(--text-sub); margin-bottom: 4px; }
        .date-item strong { color: #fff; display: inline-block; width: 70px; }

        /* الأقسام الأخرى */
        .client-section { margin-bottom: 30px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 10px; border-right: 3px solid var(--gold); }
        .section-label { font-size: 0.8rem; color: var(--gold); text-transform: uppercase; margin-bottom: 5px; font-weight: bold; }
        .client-name { font-size: 1.3rem; font-weight: 700; margin: 0; color: #fff; }
        .client-details { font-size: 0.9rem; color: var(--text-sub); margin: 3px 0 0 0; }

        /* الجدول */
        .items-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 30px; }
        .items-table th { background: rgba(212, 175, 55, 0.1); color: var(--gold); padding: 12px; text-align: center; font-size: 0.9rem; border-bottom: 1px solid var(--gold); font-weight: bold; }
        .items-table td { padding: 12px; border-bottom: 1px solid var(--border); text-align: center; font-size: 0.95rem; color: #eee; vertical-align: middle; }
        .items-table td.desc { text-align: right; width: 50%; color: #fff; }
        .total-row td { background: var(--gold); color: #000; font-weight: 900; font-size: 1.2rem; border: none; }
        .total-row td:first-child { border-radius: 0 10px 10px 0; }
        .total-row td:last-child { border-radius: 10px 0 0 10px; }

        /* الشروط */
        .terms-box { border: 1px dashed var(--border); padding: 20px; border-radius: 10px; font-size: 0.85rem; color: var(--text-sub); line-height: 1.7; page-break-inside: avoid; background: rgba(0,0,0,0.2); }
        .terms-box strong { color: var(--gold); display: block; margin-bottom: 8px; font-size: 0.95rem; }

        /* الفوتر */
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-sub); line-height: 1.8; }
        .footer a { color: var(--gold); text-decoration: none; }

        /* ختم الحالة */
        .status-stamp { 
            position: absolute; top: 150px; left: 50%; transform: translateX(-50%) rotate(-10deg);
            padding: 10px 40px; border: 4px double; 
            font-weight: 900; text-transform: uppercase; 
            opacity: 0.15; font-size: 3rem; letter-spacing: 10px;
            z-index: 0; pointer-events: none; white-space: nowrap;
        }
        .st-pending { color: #f39c12; border-color: #f39c12; }
        .st-approved { color: #2ecc71; border-color: #2ecc71; opacity: 0.8; }
        .st-rejected { color: #e74c3c; border-color: #e74c3c; opacity: 0.8; }

        /* --- منطقة القرار --- */
        .decision-area { 
            background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
            border: 1px solid var(--gold); padding: 30px; border-radius: 20px; 
            margin-top: 40px; text-align: center; page-break-inside: avoid;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.1);
            position: relative; overflow: hidden;
        }
        .decision-title { font-weight: 800; margin-bottom: 20px; font-size: 1.2rem; color: #fff; }
        .decision-btns { display: flex; gap: 20px; justify-content: center; margin-top: 20px; position: relative; z-index: 2; }

        .btn-decision { 
            padding: 15px 35px; border-radius: 50px; border: none; font-weight: 800; 
            cursor: pointer; font-size: 1rem; transition: 0.3s; color: #fff;
            display: inline-flex; align-items: center; gap: 10px;
        }
        .btn-accept { background: linear-gradient(45deg, #27ae60, #2ecc71); box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3); }
        .btn-accept:hover { transform: translateY(-3px); }
        .btn-reject { background: rgba(231, 76, 60, 0.1); border: 1px solid #c0392b; color: #e74c3c; }
        .btn-reject:hover { background: #c0392b; color: #fff; }

        /* --- حقل الملاحظات الذهبي المتحرك --- */
        .client-note-wrapper { position: relative; max-width: 600px; margin: 0 auto; }
        .client-note { 
            width: 100%; padding: 15px; border: 1px solid #444; border-radius: 12px; 
            margin-bottom: 10px; font-family: 'Cairo'; resize: vertical;
            background: #050505; color: #fff; transition: 0.3s;
            position: relative; z-index: 2; outline: none;
        }
        @keyframes borderPulse {
            0% { box-shadow: 0 0 5px rgba(212, 175, 55, 0.1); border-color: #444; }
            50% { box-shadow: 0 0 15px rgba(212, 175, 55, 0.4); border-color: var(--gold); }
            100% { box-shadow: 0 0 5px rgba(212, 175, 55, 0.1); border-color: #444; }
        }
        .client-note:focus { animation: borderPulse 3s infinite; }

        /* شريط التحكم */
        .actions-bar {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(10px);
            padding: 10px 30px; border-radius: 50px; width: 90%; max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8); display: flex; gap: 10px; justify-content: center;
            border: 1px solid var(--gold); z-index: 1000;
        }
        .btn { border: none; padding: 10px 20px; border-radius: 30px; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.9rem; font-family: 'Cairo'; color: #fff; transition: 0.3s; flex: 1; white-space: nowrap; }
        .btn:hover { transform: translateY(-3px); }
        .btn-print { background: var(--gold); color: #000; }
        .btn-wa { background: #2ecc71; }
        .btn-back { background: #333; }

        /* =========================================
           📱 تحسينات الموبايل القوية (Mobile Force)
           ========================================= */
        @media (max-width: 768px) {
            .invoice-box { padding: 20px 15px; }
            
            /* الهيدر العمودي */
            .header { flex-direction: column; text-align: center; gap: 20px; }
            .header-side { width: 100%; text-align: center !important; }
            .header-side.center { order: -1; margin-bottom: 10px; } /* اللوجو الأول */
            .logo-img { width: 200px; max-width: 80%; }
            .invoice-title { font-size: 1.8rem; }
            
            /* الجدول المتجاوب */
            .items-table { display: block; overflow-x: auto; white-space: nowrap; }
            .items-table th, .items-table td { padding: 10px 5px; font-size: 0.85rem; }
            
            /* أزرار القرار */
            .decision-btns { flex-direction: column; gap: 10px; }
            .btn-decision { width: 100%; justify-content: center; }
            
            /* شريط التحكم */
            .actions-bar { padding: 10px; bottom: 10px; flex-wrap: wrap; }
            .btn { font-size: 0.8rem; padding: 8px 5px; }
            
            /* الختم */
            .status-stamp { font-size: 1.5rem; top: auto; bottom: 20%; opacity: 0.1; }
        }

        /* =========================================
           وضع الطباعة (Clean White Paper)
           ========================================= */
        @media print {
            body { background: #fff; color: #000; padding: 0; margin: 0; }
            .container { box-shadow: none; border: none; margin: 0; max-width: 100%; width: 100%; background: #fff; color: #000; border-radius: 0; }
            .container::before { display: none; }
            .invoice-box { padding: 0; }
            
            .header { border-bottom: 2px solid #000; flex-direction: row; text-align: left; }
            .header-side { text-align: inherit; }
            .header-side.right { text-align: right; }
            .invoice-title { color: #000; text-shadow: none; }
            .invoice-id { color: #333; }
            .date-item { color: #000; }
            .date-item strong { color: #000; }
            .logo-img { width: 150px; margin: 0; }
            
            .client-section { background: #fff; border: 1px solid #ddd; border-right: 4px solid #000; }
            .section-label { color: #000; }
            .client-name { color: #000; }
            .client-details { color: #333; }
            
            .items-table { display: table; width: 100%; overflow: visible; white-space: normal; }
            .items-table th { background: #f0f0f0; color: #000; border-bottom: 2px solid #000; }
            .items-table td { color: #000; border-bottom: 1px solid #ddd; }
            .total-row td { background: #eee !important; color: #000 !important; -webkit-print-color-adjust: exact; }
            
            .terms-box { border: 1px solid #ccc; background: #fff; color: #000; }
            .terms-box strong { color: #000; }
            
            .footer { color: #000; border-top: 1px solid #ccc; }
            .footer a { color: #000; }
            
            .actions-bar, .decision-area { display: none !important; }
            .status-stamp { opacity: 0.1; color: #000 !important; border-color: #000 !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-box">
        
        <div class="status-stamp st-<?php echo $quote['status']; ?>">
            <?php 
            if($quote['status'] == 'pending') echo 'PENDING';
            elseif($quote['status'] == 'approved') echo 'APPROVED';
            else echo 'REJECTED';
            ?>
        </div>

        <div class="header">
            <div class="header-side right">
                <h1 class="invoice-title">عرض سعر</h1>
                <div class="invoice-id">#<?php echo str_pad($quote_id, 4, '0', STR_PAD_LEFT); ?></div>
            </div>

            <div class="header-side center">
                <img src="assets/img/Logo.png" alt="Arab Eagles Logo" class="logo-img">
            </div>

            <div class="header-side left">
                <div class="date-item"><strong>الإصدار:</strong> <?php echo $quote['created_at']; ?></div>
                <div class="date-item"><strong>الصلاحية:</strong> <?php echo $quote['valid_until']; ?></div>
            </div>
        </div>

        <div class="client-section">
            <div class="section-label">مقدم إلى السيد / السادة:</div>
            <h2 class="client-name"><?php echo $quote['client_name']; ?></h2>
            <p class="client-details">
                <i class="fa-solid fa-phone"></i> <?php echo $quote['client_phone']; ?> 
                <?php if($quote['client_addr']) echo " &nbsp;|&nbsp; <i class='fa-solid fa-location-dot'></i> " . $quote['client_addr']; ?>
            </p>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="50%">البيان / الوصف</th>
                    <th width="15%">الكمية</th>
                    <th width="15%">السعر</th>
                    <th width="15%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                while($item = $items->fetch_assoc()): 
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td class="desc"><?php echo nl2br($item['item_name']); ?></td>
                    <td><?php echo floatval($item['quantity']); ?></td>
                    <td><?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
                
                <tr class="total-row">
                    <td colspan="4" style="text-align: left; padding-left: 20px;">الإجمالي النهائي (EGP)</td>
                    <td><?php echo number_format($quote['total_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="terms-box">
            <strong><i class="fa-solid fa-gavel"></i> الشروط والأحكام والملاحظات:</strong>
            <?php echo nl2br($quote['notes']); ?>
        </div>

        <?php if($quote['status'] == 'pending'): ?>
        <div class="decision-area">
            <div class="decision-title">يرجى اتخاذ قرار بشأن هذا العرض</div>
            <form method="POST" action="">
                <div class="client-note-wrapper">
                    <textarea name="client_note" class="client-note" placeholder="هل لديك أي ملاحظات أو استفسارات؟ اكتبها هنا..."></textarea>
                </div>
                <div class="decision-btns">
                    <button type="submit" name="action" value="approve" class="btn-decision btn-accept" onclick="return confirm('هل أنت متأكد من الموافقة؟')">
                        <i class="fa-solid fa-check-circle"></i> موافقة واعتماد
                    </button>
                    <button type="submit" name="action" value="reject" class="btn-decision btn-reject" onclick="return confirm('هل أنت متأكد من الرفض؟')">
                        <i class="fa-solid fa-times-circle"></i> اعتذار / رفض
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div style="text-align:center; margin-top:40px; padding:20px; background:rgba(255,255,255,0.05); border-radius:10px; border:1px solid var(--border);">
                <div style="font-size:1.1rem; margin-bottom:10px;">حالة العرض الحالية:</div>
                <?php if($quote['status'] == 'approved'): ?>
                    <div style="color:#2ecc71; font-size:1.5rem; font-weight:bold;"><i class="fa-solid fa-check-circle"></i> تمت الموافقة من قبل العميل</div>
                <?php else: ?>
                    <div style="color:#e74c3c; font-size:1.5rem; font-weight:bold;"><i class="fa-solid fa-times-circle"></i> تم رفض العرض</div>
                <?php endif; ?>
                
                <?php if(!empty($quote['client_comment'])): ?>
                    <div style="margin-top:15px; color:#aaa; font-style:italic;">"<?php echo $quote['client_comment']; ?>"</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>شكراً لثقتكم بنا | ARAB EAGLES FOR PRINTING, MARKETING, INDUSTRY AND TRADING</p>
            <p>ت: 01000571057 | العنوان: ١٠٥ شارع الطيران - مدينة نصر- القاهرة - مصر</p>
            <p><a href="https://www.areagles.com" target="_blank">www.areagles.com</a> | info@areagles.com</p>
        </div>

    </div>
</div>

<div class="actions-bar">
    <?php if($access_mode == 'id'): ?>
    <a href="quotes.php" class="btn btn-back">
        <i class="fa-solid fa-arrow-right"></i> رجوع
    </a>
    <a href="https://wa.me/20<?php echo ltrim($quote['client_phone'], '0'); ?>?text=<?php echo urlencode($wa_msg); ?>" target="_blank" class="btn btn-wa">
        <i class="fa-brands fa-whatsapp"></i> إرسال للعميل
    </a>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-print">
        <i class="fa-solid fa-print"></i> طباعة / PDF
    </button>
</div>

</body>
</html>