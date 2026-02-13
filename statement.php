<?php
// statement.php - (Royal Statement V11.2 - Perfect Print)
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. التحقق من العميل
if(!isset($_GET['client_id']) || empty($_GET['client_id'])){
    echo "<div class='container' style='padding:50px; text-align:center;'>⛔ لم يتم تحديد العميل.</div>";
    require 'footer.php'; exit;
}

$client_id = intval($_GET['client_id']);
$date_from = $_GET['from'] ?? date('Y-01-01'); 
$date_to   = $_GET['to']   ?? date('Y-m-d');   

// 2. جلب بيانات العميل
$client_q = $conn->query("SELECT * FROM clients WHERE id = $client_id");
if(!$client_q || $client_q->num_rows == 0) die("<div class='container'>⛔ العميل غير موجود.</div>");
$client = $client_q->fetch_assoc();

// --- تجهيز رابط البوابة ---
if(empty($client['access_token'])) {
    $new_token = bin2hex(random_bytes(16));
    $conn->query("UPDATE clients SET access_token = '$new_token' WHERE id = $client_id");
    $client['access_token'] = $new_token;
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = str_replace('/modules', '', $base_url); 
$portal_link = $base_url . "/financial_review.php?token=" . $client['access_token'] . "&type=client";

// 3. المحرك المحاسبي
// أ. الرصيد الافتتاحي
$sql_open = "
    SELECT 
        (SELECT IFNULL(SUM(opening_balance), 0) FROM clients WHERE id = $client_id)
        +
        (SELECT IFNULL(SUM(total_amount),0) FROM invoices WHERE client_id = $client_id AND date(created_at) < '$date_from') 
        - 
        (SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE client_id = $client_id AND type='in' AND trans_date < '$date_from') 
    AS opening_balance
";
$res_open = $conn->query($sql_open);
$opening_balance = ($res_open && $res_open->num_rows > 0) ? ($res_open->fetch_object()->opening_balance ?? 0) : 0;

// ب. حركات الفترة
$sql = "
    SELECT * FROM (
        -- الفواتير (مدين - Debit)
        SELECT 
            created_at as t_date, 'invoice' as type, id as ref_id, total_amount as debit, 0 as credit, 'فاتورة مبيعات' as description 
        FROM invoices 
        WHERE client_id = $client_id AND date(created_at) BETWEEN '$date_from' AND '$date_to'

        UNION ALL

        -- الإيصالات (دائن - Credit)
        SELECT 
            trans_date as t_date, 'receipt' as type, id as ref_id, 0 as debit, amount as credit, description 
        FROM financial_receipts 
        WHERE client_id = $client_id AND type='in' AND trans_date BETWEEN '$date_from' AND '$date_to'
    ) AS ledger
    ORDER BY t_date ASC, ref_id ASC
";
$transactions = $conn->query($sql);

// Helper for WhatsApp
function get_wa_link($phone, $text) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11 && substr($phone, 0, 2) == '01') { $phone = '2' . $phone; }
    return "https://wa.me/$phone?text=" . urlencode($text);
}
?>

<style>
    :root { --gold: #d4af37; --panel-bg: #1e1e1e; --dark: #0f0f0f; }
    
    /* Layout */
    .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    
    /* Control Bar */
    .control-bar {
        background: var(--panel-bg); padding: 15px; border-radius: 12px; 
        border: 1px solid #333; margin-bottom: 25px; 
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
    }
    
    .filter-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .filter-form input { background: #000; color: #fff; border: 1px solid #444; padding: 10px; border-radius: 6px; }
    
    .share-panel {
        display: flex; gap: 10px; align-items: center; background: #222; padding: 8px 15px; border-radius: 8px; border: 1px solid var(--gold);
    }
    .share-input { background: transparent; border: none; color: var(--gold); font-family: monospace; width: 150px; overflow: hidden; text-overflow: ellipsis; }

    /* Printable Area (A4 Style) */
    .printable-area { 
        background: #fff; color: #000; padding: 40px; 
        border-radius: 5px; min-height: 800px; 
        font-family: 'Times New Roman', serif;
        box-shadow: 0 0 30px rgba(0,0,0,0.5);
    }
    
    .header-print { display: flex; justify-content: space-between; border-bottom: 3px double #000; padding-bottom: 20px; margin-bottom: 30px; }
    .client-info-box { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; }

    /* Table Styles */
    .royal-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-family: 'Cairo', sans-serif; font-size: 0.95rem; }
    .royal-table th { background: #eee; color: #000; padding: 12px; border: 1px solid #000; font-weight: bold; text-align: center; }
    .royal-table td { padding: 10px; border: 1px solid #ccc; text-align: center; vertical-align: middle; }
    .row-debit { color: #c0392b; font-weight: bold; }
    .row-credit { color: #27ae60; font-weight: bold; }
    
    /* Buttons */
    .btn-royal { 
        padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; 
        font-weight: bold; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-family: 'Cairo'; transition: 0.3s;
    }
    .btn-royal:hover { transform: translateY(-2px); }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .control-bar { flex-direction: column; align-items: stretch; }
        .filter-form { flex-direction: column; }
        .filter-form input { width: 100%; box-sizing: border-box; }
        .share-panel { display: none; } /* Hide raw link on mobile */
        
        /* Mobile Stack Table */
        .printable-area { padding: 15px; }
        .header-print { flex-direction: column; text-align: center; gap: 15px; }
        .client-info-box { flex-direction: column; gap: 10px; }
        
        .royal-table, .royal-table thead, .royal-table tbody, .royal-table th, .royal-table td, .royal-table tr { display: block; }
        .royal-table thead { display: none; }
        .royal-table tr { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; padding: 10px; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .royal-table td { border: none; border-bottom: 1px solid #eee; position: relative; padding-left: 50%; text-align: right; }
        .royal-table td:before { position: absolute; left: 10px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: bold; text-align: left; content: attr(data-label); color: #666; }
        .royal-table td:last-child { border-bottom: 0; }
        
        .no-print-mobile { display: none !important; }
    }

    /* Print Specific Fixes */
    @media print { 
        @page { size: A4; margin: 0; }
        body { background: #fff; margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        /* Hide UI */
        .no-print, .no-print-mobile, .main-navbar, footer { display: none !important; }
        
        /* Reset Container */
        .container { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .printable-area { 
            box-shadow: none !important; 
            width: 100% !important; 
            margin: 0 !important; 
            padding: 1.5cm !important; 
            min-height: auto !important;
            border: none !important;
        }

        /* Force Table Display */
        .royal-table { display: table !important; width: 100% !important; border: 1px solid #000; }
        .royal-table thead { display: table-header-group !important; }
        .royal-table tbody { display: table-row-group !important; }
        .royal-table tr { display: table-row !important; border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
        .royal-table th, .royal-table td { 
            display: table-cell !important; 
            text-align: center !important; 
            border: 1px solid #ccc !important;
            padding: 8px !important;
        }
        
        /* Remove Mobile Labels */
        .royal-table td:before { content: none !important; }
        .royal-table td { padding-left: 8px !important; text-align: center !important; }

        /* Headers Layout */
        .header-print { flex-direction: row !important; text-align: right !important; }
        .client-info-box { flex-direction: row !important; }
    }
</style>

<div class="container">

    <div class="control-bar no-print">
        <div style="display:flex; gap:10px;">
            <a href="finance_reports.php" class="btn-royal" style="background:#444;"><i class="fa-solid fa-arrow-right"></i> رجوع للمركز المالي</a>
            <button onclick="window.print()" class="btn-royal" style="background:var(--gold); color:#000;"><i class="fa-solid fa-print"></i> طباعة</button>
        </div>

        <form method="GET" class="filter-form">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <input type="date" name="from" value="<?php echo $date_from; ?>">
            <input type="date" name="to" value="<?php echo $date_to; ?>">
            <button type="submit" class="btn-royal" style="background:#333; border:1px solid var(--gold);"><i class="fa-solid fa-filter"></i></button>
        </form>

        <div class="share-panel no-print-mobile">
            <input type="text" value="<?php echo $portal_link; ?>" id="shareLink" class="share-input" readonly>
            <button onclick="copyLink()" class="btn-royal" style="background:#3498db; padding: 5px 10px; font-size:0.8rem;">نسخ</button>
        </div>

        <a href="<?php echo get_wa_link($client['phone'], "مرحباً {$client['name']}، مرفق كشف الحساب للمراجعة:\n$portal_link"); ?>" target="_blank" class="btn-royal" style="background:#25D366; width:100%; justify-content:center;">
            <i class="fa-brands fa-whatsapp"></i> إرسال للعميل
        </a>
    </div>

    <div class="printable-area">
        
        <div class="header-print">
            <div>
                <h1 style="margin:0; text-transform:uppercase;">ARAB EAGLES</h1>
                <p style="margin:5px 0; color:#555;">Digital Marketing & Printing Solutions</p>
            </div>
            <div style="text-align:right;">
                <div style="border:2px solid #000; padding:5px 20px; font-weight:bold; display:inline-block; margin-bottom:5px;">ACCOUNT STATEMENT<br>كشف حساب عميل</div>
                <div>Date: <?php echo date('d/m/Y'); ?></div>
            </div>
        </div>

        <div class="client-info-box">
            <div>
                <small style="color:#666;">CUSTOMER / العميل:</small>
                <h2 style="margin:5px 0;"><?php echo $client['name']; ?></h2>
                <div><?php echo $client['phone']; ?></div>
            </div>
            <div style="text-align:right;">
                <div><strong>من:</strong> <?php echo $date_from; ?></div>
                <div><strong>إلى:</strong> <?php echo $date_to; ?></div>
            </div>
        </div>

        <table class="royal-table">
            <thead>
                <tr>
                    <th width="15%">التاريخ</th>
                    <th width="10%">النوع</th>
                    <th width="10%">المرجع</th>
                    <th width="35%">البيان</th>
                    <th width="10%">مدين (Debit)</th>
                    <th width="10%">دائن (Credit)</th>
                    <th width="10%">الرصيد</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background:#fffde7; font-weight:bold;">
                    <td data-label="التاريخ"><?php echo $date_from; ?></td>
                    <td data-label="البيان" colspan="3">الرصيد الافتتاحي (ما قبل الفترة)</td>
                    <td data-label="الرصيد" colspan="3" dir="ltr" style="text-align:center;"><?php echo number_format($opening_balance, 2); ?></td>
                </tr>

                <?php 
                $balance = $opening_balance;
                $sum_debit = 0;
                $sum_credit = 0;

                if($transactions && $transactions->num_rows > 0):
                    while($row = $transactions->fetch_assoc()):
                        // العميل: يزيد بالمدين (فاتورة) وينقص بالدائن (سداد)
                        $balance += ($row['debit'] - $row['credit']);
                        $sum_debit += $row['debit'];
                        $sum_credit += $row['credit'];
                ?>
                <tr>
                    <td data-label="التاريخ"><?php echo date('Y-m-d', strtotime($row['t_date'])); ?></td>
                    <td data-label="النوع"><?php echo ($row['type'] == 'invoice') ? 'فاتورة' : 'سداد'; ?></td>
                    <td data-label="المرجع">#<?php echo $row['ref_id']; ?></td>
                    <td data-label="البيان" style="text-align:right;"><?php echo $row['description']; ?></td>
                    <td data-label="مدين" class="row-debit"><?php echo ($row['debit'] > 0) ? number_format($row['debit'], 2) : '-'; ?></td>
                    <td data-label="دائن" class="row-credit"><?php echo ($row['credit'] > 0) ? number_format($row['credit'], 2) : '-'; ?></td>
                    <td data-label="الرصيد" style="font-weight:bold;" dir="ltr"><?php echo number_format($balance, 2); ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" style="padding:30px; color:#999;">لا توجد حركات خلال الفترة المحددة.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#333; color:#fff; font-weight:bold;">
                    <td colspan="4" style="text-align:left; padding-left:20px;">TOTALS</td>
                    <td data-label="مجموع المدين"><?php echo number_format($sum_debit, 2); ?></td>
                    <td data-label="مجموع الدائن"><?php echo number_format($sum_credit, 2); ?></td>
                    <td data-label="الرصيد النهائي" style="background:#000; color:var(--gold);"><?php echo number_format($balance, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if(!empty($client['last_balance_confirm'])): ?>
        <div style="margin-top:20px; border:1px solid #27ae60; background:#f0fff4; padding:10px; text-align:center; color:#27ae60; font-weight:bold;">
            ✅ تمت المصادقة على هذا الرصيد من قبل العميل بتاريخ: <?php echo $client['last_balance_confirm']; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:60px; display:flex; justify-content:space-between; text-align:center; page-break-inside:avoid;">
            <div>_________________<br>المحاسب</div>
            <div>_________________<br>المدير المالي</div>
            <div>_________________<br>اعتماد العميل</div>
        </div>

    </div>
</div>

<script>
function copyLink() {
    var copyText = document.getElementById("shareLink");
    copyText.select();
    document.execCommand("copy");
    alert("تم نسخ رابط البوابة!");
}
</script>

<?php include 'footer.php'; ob_end_flush(); ?>