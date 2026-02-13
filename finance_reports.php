<?php
// finance_reports.php - (Royal Finance Hub V13.1 - Precision Fix)
// تم إصلاح معادلات الجمع لتكون دقيقة محاسبياً

ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// --- 1. بروتوكول الإصلاح الذاتي ---
$sqls = [
    "ALTER TABLE clients ADD COLUMN IF NOT EXISTS access_token VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE clients ADD COLUMN IF NOT EXISTS last_balance_confirm DATETIME DEFAULT NULL",
    "ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS access_token VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS last_balance_confirm DATETIME DEFAULT NULL",
    "CREATE TABLE IF NOT EXISTS purchase_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
];
foreach($sqls as $q) $conn->query($q);

// دالة الروابط
function get_finance_link($type, $id, $token) {
    global $conn;
    if(empty($token)) {
        $token = bin2hex(random_bytes(16));
        $table = ($type == 'client') ? 'clients' : 'suppliers';
        $conn->query("UPDATE $table SET access_token='$token' WHERE id=$id");
    }
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $path = str_replace('/modules', '', dirname($_SERVER['PHP_SELF'])); 
    return "$protocol://$host$path/financial_review.php?token=$token&type=$type";
}

// --- 2. المحرك المالي الدقيق (Fixed Calculation Engine) ---

// أ. مستحقات العملاء (Receivables)
// المعادلة: (أرصدة افتتاحية للعملاء + إجمالي الفواتير) - (المقبوضات من العملاء فقط)
$sql_clients_calc = "
    SELECT 
        (SELECT IFNULL(SUM(opening_balance), 0) FROM clients) as open_bal,
        (SELECT IFNULL(SUM(total_amount), 0) FROM invoices) as total_sales,
        (SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE type='in' AND client_id > 0) as total_collected
";
$c_calc = $conn->query($sql_clients_calc)->fetch_assoc();
$total_receivables = ($c_calc['open_bal'] + $c_calc['total_sales']) - $c_calc['total_collected'];


// ب. التزامات الموردين (Payables)
// المعادلة: (أرصدة افتتاحية للموردين + إجمالي المشتريات) - (المدفوعات للموردين فقط)
$sql_suppliers_calc = "
    SELECT 
        (SELECT IFNULL(SUM(opening_balance), 0) FROM suppliers) as open_bal,
        (SELECT IFNULL(SUM(total_amount), 0) FROM purchase_invoices) as total_purchases,
        (SELECT IFNULL(SUM(amount), 0) FROM financial_receipts WHERE type='out' AND supplier_id > 0) as total_paid
";
$s_calc = $conn->query($sql_suppliers_calc)->fetch_assoc();
$total_payables = ($s_calc['open_bal'] + $s_calc['total_purchases']) - $s_calc['total_paid'];


// ج. القوائم
$clients_list = $conn->query("SELECT * FROM clients ORDER BY name ASC");
$suppliers_list = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
?>

<style>
    :root {
        --royal-gold: #d4af37;
        --royal-bg: #0b0b0b;
        --royal-panel: #141414;
        --royal-green: #27ae60;
        --royal-red: #c0392b;
    }

    /* Hero Dashboard */
    .finance-hero {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .hero-card {
        background: var(--royal-panel);
        border: 1px solid #333;
        border-radius: 15px;
        padding: 25px;
        position: relative;
        overflow: hidden;
        transition: 0.3s;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .hero-card:hover { transform: translateY(-5px); border-color: var(--royal-gold); }
    
    .hero-card::after { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
    .card-rec::after { background: var(--royal-green); }
    .card-pay::after { background: var(--royal-red); }

    .hero-label { font-size: 0.9rem; color: #888; display: block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
    .hero-num { font-size: 2.2rem; font-weight: bold; color: #fff; font-family: 'Cairo', sans-serif; }
    .hero-icon { position: absolute; bottom: 10px; left: 20px; font-size: 4rem; opacity: 0.05; }

    /* Lists Style */
    .section-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 25px;
    }

    .list-card {
        background: var(--royal-panel);
        border: 1px solid #333;
        border-radius: 12px;
        overflow: hidden;
        display: flex; flex-direction: column;
        height: 600px; /* ارتفاع ثابت للقائمة */
    }

    .list-header {
        background: rgba(255,255,255,0.03);
        padding: 15px 20px;
        border-bottom: 1px solid #333;
        display: flex; justify-content: space-between; align-items: center;
    }
    .list-title { margin: 0; color: var(--royal-gold); font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }

    .list-body { overflow-y: auto; padding: 10px; flex: 1; }

    .data-row {
        display: flex; justify-content: space-between; align-items: center;
        background: #000; border: 1px solid #222;
        padding: 12px 15px; border-radius: 8px; margin-bottom: 8px;
        transition: 0.2s;
    }
    .data-row:hover { border-color: #444; background: #0a0a0a; }

    .entity-name { font-weight: bold; color: #eee; font-size: 0.95rem; }
    .entity-meta { font-size: 0.75rem; color: #666; margin-top: 3px; }
    
    .confirm-badge { color: var(--royal-green); background: rgba(39, 174, 96, 0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 4px; }

    .action-group { display: flex; gap: 5px; }
    .act-btn {
        width: 32px; height: 32px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        border: 1px solid #444; background: #1a1a1a; color: #888;
        cursor: pointer; transition: 0.2s; text-decoration: none;
    }
    .act-btn:hover { color: #fff; border-color: var(--royal-gold); background: var(--royal-gold); color: #000; }
    .act-whatsapp:hover { background: #25D366; border-color: #25D366; color: #fff; }
    .act-copy:hover { background: #3498db; border-color: #3498db; color: #fff; }

</style>

<div class="container">

    <div class="finance-hero">
        <div class="hero-card card-rec">
            <span class="hero-label">إجمالي مستحقاتنا (عملاء)</span>
            <div class="hero-num" style="color:var(--royal-green);">
                <?php echo number_format($total_receivables, 2); ?> <small style="font-size:1rem;">EGP</small>
            </div>
            <i class="fa-solid fa-hand-holding-dollar hero-icon"></i>
        </div>

        <div class="hero-card card-pay">
            <span class="hero-label">إجمالي التزاماتنا (موردين)</span>
            <div class="hero-num" style="color:var(--royal-red);">
                <?php echo number_format($total_payables, 2); ?> <small style="font-size:1rem;">EGP</small>
            </div>
            <i class="fa-solid fa-file-invoice-dollar hero-icon"></i>
        </div>
    </div>

    <div class="section-container">
        
        <div class="list-card">
            <div class="list-header">
                <h3 class="list-title"><i class="fa-solid fa-users"></i> بوابة العملاء</h3>
                <small style="color:#666;"><?php echo $clients_list->num_rows; ?> عميل</small>
            </div>
            <div class="list-body">
                <?php while($c = $clients_list->fetch_assoc()): 
                    $link = get_finance_link('client', $c['id'], $c['access_token']);
                    $wa_msg = urlencode("مرحباً {$c['name']}،\nيرجى التكرم بمراجعة كشف الحساب والمصادقة عليه عبر الرابط:\n$link");
                ?>
                <div class="data-row">
                    <div>
                        <div class="entity-name"><?php echo $c['name']; ?></div>
                        <div class="entity-meta">
                            <?php if(!empty($c['last_balance_confirm'])): ?>
                                <span class="confirm-badge"><i class="fa-solid fa-check-circle"></i> صودق: <?php echo date('d/m', strtotime($c['last_balance_confirm'])); ?></span>
                            <?php else: ?>
                                <span style="color:#e74c3c;">• لم تتم المصادقة</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="action-group">
                        <a href="statement.php?client_id=<?php echo $c['id']; ?>" class="act-btn" title="كشف حساب داخلي"><i class="fa-solid fa-file-lines"></i></a>
                        <button onclick="copyToClipboard('<?php echo $link; ?>')" class="act-btn act-copy" title="نسخ رابط البوابة"><i class="fa-solid fa-link"></i></button>
                        <a href="https://wa.me/<?php echo $c['phone']; ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="act-btn act-whatsapp" title="إرسال واتساب"><i class="fa-brands fa-whatsapp"></i></a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="list-card">
            <div class="list-header">
                <h3 class="list-title"><i class="fa-solid fa-truck-field"></i> بوابة الموردين</h3>
                <small style="color:#666;"><?php echo $suppliers_list->num_rows; ?> مورد</small>
            </div>
            <div class="list-body">
                <?php while($s = $suppliers_list->fetch_assoc()): 
                    $link = get_finance_link('supplier', $s['id'], $s['access_token']);
                    $wa_msg = urlencode("السادة {$s['name']}،\nمرفق رابط المطابقة المالية:\n$link");
                ?>
                <div class="data-row">
                    <div>
                        <div class="entity-name"><?php echo $s['name']; ?></div>
                        <div class="entity-meta">
                            <?php if(!empty($s['last_balance_confirm'])): ?>
                                <span class="confirm-badge"><i class="fa-solid fa-check-circle"></i> صودق: <?php echo date('d/m', strtotime($s['last_balance_confirm'])); ?></span>
                            <?php else: ?>
                                <span style="color:#666;">• بانتظار المطابقة</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="action-group">
                        <a href="statement_supplier.php?supplier_id=<?php echo $s['id']; ?>" class="act-btn" title="كشف حساب داخلي"><i class="fa-solid fa-file-lines"></i></a>
                        <button onclick="copyToClipboard('<?php echo $link; ?>')" class="act-btn act-copy" title="نسخ رابط البوابة"><i class="fa-solid fa-link"></i></button>
                        <a href="https://wa.me/<?php echo $s['phone']; ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="act-btn act-whatsapp" title="إرسال واتساب"><i class="fa-brands fa-whatsapp"></i></a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('تم نسخ رابط البوابة بنجاح! 📋');
    }, function(err) {
        console.error('فشل النسخ: ', err);
    });
}
</script>

<?php include 'footer.php'; ?>