<?php
// view_order.php - (Royal Print Order View - Invoice Style)
ob_start();
ini_set('display_errors', 0); 
require 'config.php'; 

// 1. الأمان والتحقق
$id = intval($_GET['id']);
$token = $_GET['token'] ?? '';

// التحقق من التوكن
$res = $conn->query("SELECT * FROM job_orders WHERE id=$id");
if(!$res || $res->num_rows==0) die("أمر الشغل غير موجود");
$job = $res->fetch_assoc();

if ($token !== $job['access_token']) {
    die("<div style='height:100vh; display:flex; align-items:center; justify-content:center; background:#000; color:#d4af37; font-family:sans-serif;'>
            <div style='text-align:center;'>
                <h1 style='font-size:3rem; margin:0;'>⛔</h1>
                <h2 style='color:#fff;'>رابط غير صالح</h2>
            </div>
         </div>");
}

// 2. استخراج البيانات الفنية (نفس منطق النظام)
$raw_text = $job['job_details'] ?? '';
function get_spec($pattern, $text, $default = '-') {
    if(empty($text)) return $default;
    preg_match($pattern, $text, $matches);
    return isset($matches[1]) ? trim($matches[1]) : $default;
}

$specs = [
    'p_size'     => get_spec('/مقاس الورق:.*?([\d\.]+\s*x\s*[\d\.]+)/u', $raw_text, 'غير محدد'),
    'c_size'     => get_spec('/مقاس القص:.*?([\d\.]+\s*x\s*[\d\.]+)/u', $raw_text, 'غير محدد'),
    'machine'    => get_spec('/الماكينة: (.*?)(?:\||$)/u', $raw_text, 'غير محدد'),
    'print_face' => get_spec('/الوجه: (.*?)(?:\||$)/u', $raw_text, 'غير محدد'),
    'colors'     => get_spec('/الألوان: (.*?)(?:\||$)/u', $raw_text, 'غير محدد'),
    'zinc'       => get_spec('/الزنكات: ([\d\.]+)/u', $raw_text, '0'),
    'finish'     => get_spec('/التكميلي: (.*?)(?:\||$)/u', $raw_text, 'غير محدد'),
];

// جلب بيانات العميل
$client_res = $conn->query("SELECT * FROM clients WHERE id={$job['client_id']}");
$client = $client_res->fetch_assoc();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أمر تشغيل #<?php echo $id; ?> - Arab Eagles</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- نفس تصميم الفاتورة الملكي (Invoice Style) --- */
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

        /* الهيدر */
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

        /* جدول المواصفات (بديل جدول المنتجات) */
        .specs-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 30px;
        }
        .spec-item {
            background: #0a0a0a; border: 1px solid var(--border); padding: 15px; border-radius: 8px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .spec-label { color: var(--text-sub); font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .spec-value { color: #fff; font-weight: bold; font-size: 1.1rem; }
        .spec-icon { color: var(--gold); }

        /* الفوتر */
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-sub); }

        /* الأزرار (تظهر فقط للشاشة) */
        .actions-bar {
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(18, 18, 18, 0.95); backdrop-filter: blur(10px);
            padding: 10px 20px; border-radius: 50px; width: 90%; max-width: 400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8); display: flex; gap: 10px; justify-content: center;
            border: 1px solid var(--gold); z-index: 1000;
        }
        .btn { border: none; padding: 10px 20px; border-radius: 30px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.9rem; font-family: 'Cairo'; color: #000; transition: 0.3s; flex: 1; text-decoration: none; white-space: nowrap; background: var(--gold); }
        .btn:hover { transform: translateY(-3px); }

        /* الطباعة */
        @media print {
            body { background: #fff; color: #000; padding: 0; }
            .container { box-shadow: none; border: none; margin: 0; max-width: 100%; width: 100%; background: #fff; color: #000; border-radius: 0; }
            .container::before { display: none; }
            .header { border-bottom: 2px solid #000; flex-direction: row; }
            .header-side { text-align: inherit !important; }
            .header-side.right { text-align: right !important; }
            .invoice-title, .section-label { color: #000; text-shadow: none; }
            .invoice-id, .date-item, .client-name, .client-details { color: #000; }
            .logo-img { width: 250px; }
            .client-section { background: #fff; border: 1px solid #ddd; border-right: 4px solid #000; }
            .spec-item { background: #fff; border: 1px solid #ddd; color: #000; }
            .spec-label, .spec-value { color: #000; }
            .spec-icon { color: #000; }
            .footer { color: #000; border-top: 1px solid #ccc; }
            .actions-bar { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-box">
        
        <div class="header">
            <div class="header-side right">
                <h1 class="invoice-title">أمر تشغيل</h1>
                <div class="invoice-id">ORDER #<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="header-side center">
                <img src="assets/img/Logo.png" alt="Arab Eagles Logo" class="logo-img">
            </div>
            <div class="header-side left">
                <div class="date-item"><strong>التاريخ:</strong> <?php echo date('Y-m-d', strtotime($job['created_at'])); ?></div>
            </div>
        </div>

        <div class="client-section">
            <div class="section-label">بيانات العملية:</div>
            <h2 class="client-name"><?php echo $job['job_name']; ?></h2>
            <p class="client-details">
                العميل: <?php echo $client['name']; ?> | الكمية: <strong><?php echo $job['quantity']; ?></strong>
            </p>
        </div>

        <div class="specs-grid">
            <div class="spec-item">
                <div class="spec-label"><i class="fa-solid fa-scroll spec-icon"></i> مقاس الورق</div>
                <div class="spec-value"><?php echo $specs['p_size']; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label"><i class="fa-solid fa-scissors spec-icon"></i> مقاس القص</div>
                <div class="spec-value"><?php echo $specs['c_size']; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label"><i class="fa-solid fa-print spec-icon"></i> الماكينة</div>
                <div class="spec-value"><?php echo $specs['machine']; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label"><i class="fa-solid fa-palette spec-icon"></i> الألوان</div>
                <div class="spec-value"><?php echo $specs['colors']; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label"><i class="fa-regular fa-file spec-icon"></i> الوجه</div>
                <div class="spec-value"><?php echo $specs['print_face']; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label"><i class="fa-solid fa-layer-group spec-icon"></i> الزنكات</div>
                <div class="spec-value"><?php echo $specs['zinc']; ?></div>
            </div>
        </div>

        <?php if(!empty($specs['finish'])): ?>
        <div style="margin-top:20px; font-size:0.9rem; color:var(--text-sub); border-top:1px dashed #333; padding-top:10px;">
            <strong style="color:var(--gold);">✨ التشطيب التكميلي:</strong> <?php echo $specs['finish']; ?>
        </div>
        <?php endif; ?>

        <?php if(!empty($job['notes'])): ?>
        <div style="margin-top:10px; font-size:0.9rem; color:var(--text-sub);">
            <strong style="color:var(--gold);">📝 ملاحظات:</strong> <?php echo nl2br($job['notes']); ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Arab Eagles for Printing & Marketing | www.areagles.com</p>
        </div>

    </div>
</div>

<div class="actions-bar">
    <button onclick="window.print()" class="btn">
        <i class="fa-solid fa-print"></i> طباعة / حفظ PDF
    </button>
</div>

</body>
</html>