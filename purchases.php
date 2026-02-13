<?php
// purchases.php - أرشيف المشتريات (Fix: Force Calculation Logic)
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. إعداد فلاتر البحث
$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$date_from = isset($_GET['from']) ? $_GET['from'] : '';
$date_to   = isset($_GET['to'])   ? $_GET['to']   : '';

// الشرط: سندات صرف (out)
$where = "WHERE r.type='out'";

// إضافة شروط البحث
if(!empty($search)){
    // البحث يشمل اسم المورد أو الوصف
    $where .= " AND (r.description LIKE '%$search%' OR s.name LIKE '%$search%')";
}
if(!empty($date_from) && !empty($date_to)){
    $where .= " AND r.trans_date BETWEEN '$date_from' AND '$date_to'";
}

/* 2. الاستعلام الأساسي (جلب الحركات فقط)
   قمنا بتبسيط الاستعلام لضمان سرعة الجلب، وسنقوم بالحساب الدقيق في الأسفل
*/
$sql = "SELECT r.*, 
        s.name as supplier_name, 
        s.category as sup_cat,
        s.opening_balance,
        s.id as real_supplier_id
        FROM financial_receipts r 
        LEFT JOIN suppliers s ON r.supplier_id = s.id 
        $where 
        ORDER BY r.trans_date DESC, r.id DESC";
$res = $conn->query($sql);

// 3. إجمالي الفلترة (للعرض العلوي)
$sql_sum = "SELECT SUM(amount) FROM financial_receipts r LEFT JOIN suppliers s ON r.supplier_id = s.id $where";
$total_filtered = $conn->query($sql_sum)->fetch_row()[0] ?? 0;
?>

<style>
    :root { --gold: #d4af37; --dark-bg: #0f0f0f; --panel-bg: #1a1a1a; }
    body { background-color: var(--dark-bg); color: #fff; font-family: 'Cairo'; }
    
    .table-container { background: var(--panel-bg); border-radius: 12px; overflow: hidden; border: 1px solid #333; margin-top: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    table { width: 100%; border-collapse: collapse; }
    th { background: #111; color: var(--gold); padding: 15px; text-align: right; border-bottom: 2px solid #333; }
    td { padding: 15px; border-bottom: 1px solid #222; vertical-align: middle; }
    tr:hover { background: #222; }
    
    .btn-action { color: #fff; margin-left: 10px; text-decoration: none; font-size: 1.1rem; transition: 0.2s; }
    .btn-action:hover { color: var(--gold); transform: scale(1.2); }
    
    .btn-add { background: var(--gold); color: #000; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; transition:0.3s; }
    .btn-add:hover { opacity: 0.9; transform: translateY(-2px); }

    .total-box { background: linear-gradient(45deg, #b92b27, #1565C0); padding: 15px 25px; border-radius: 10px; color: white; font-weight: bold; display: inline-block; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    
    .search-inputs { display:flex; gap:10px; flex-wrap:wrap; background:#222; padding:15px; border-radius:10px; border:1px solid #333; }
    .search-inputs input { padding:10px; background:#000; border:1px solid #444; color:#fff; border-radius:5px; flex:1; }
    .search-inputs button { padding:10px 25px; background:var(--gold); color:#000; border:none; border-radius:5px; cursor:pointer; font-weight:bold; }

    .sup-info { font-size: 0.75rem; color: #aaa; margin-top: 4px; display: block; }
    .balance-tag { background: #333; padding: 2px 6px; border-radius: 4px; border: 1px solid #444; color: #fff; font-size: 0.75rem; }
</style>

<div class="container" style="margin-top:40px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
        <h2 style="color:var(--gold); margin:0;"><i class="fa-solid fa-cart-shopping"></i> سجل المدفوعات (الموردين)</h2>
        <div class="total-box">
            إجمالي المعروض: <?php echo number_format($total_filtered, 2); ?> ج.م 
            

[Image of money stack icon]

        </div>
        <a href="finance.php?def_type=out&def_cat=supplier" class="btn-add">
            <i class="fa-solid fa-plus"></i> تسجيل عملية جديدة
        </a>
    </div>

    <form method="GET" class="search-inputs" style="margin-top:20px;">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="اسم المورد، التفاصيل...">
        <input type="date" name="from" value="<?php echo $date_from; ?>" placeholder="من" title="من تاريخ">
        <input type="date" name="to" value="<?php echo $date_to; ?>" placeholder="إلى" title="إلى تاريخ">
        <button type="submit"><i class="fa-solid fa-filter"></i> عرض السجلات</button>
        <?php if($search || $date_from): ?>
            <a href="purchases.php" style="padding:10px 15px; background:#333; color:#fff; text-decoration:none; border-radius:5px;">إلغاء</a>
        <?php endif; ?>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th>التاريخ</th>
                    <th>الجهة / المورد</th>
                    <th>تفاصيل العملية</th>
                    <th>المبلغ المدفوع</th>
                    <th>الملف المالي للمورد</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if($res && $res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): ?>
                        <?php 
                            // ============================================================
                            //  🔥 الحساب القسري (Force Calculation) لكل صف 🔥
                            // ============================================================
                            $paid_total = 0;
                            $current_bal = 0;
                            
                            // 1. هل هذه العملية مرتبطة بمورد؟
                            if(!empty($row['real_supplier_id'])) {
                                $sid = intval($row['real_supplier_id']);
                                
                                // 2. اذهب واحسب "كل" الفلوس التي دفعناها لهذا المورد تحديداً من جدول السندات
                                // نبحث عن supplier_id ونوع الحركة out
                                $q_calc = $conn->query("SELECT SUM(amount) FROM financial_receipts WHERE supplier_id = $sid AND type = 'out'");
                                $paid_total = $q_calc->fetch_row()[0] ?? 0;
                                
                                // 3. حساب الرصيد
                                $current_bal = $row['opening_balance'] - $paid_total;
                            }
                        ?>
                    <tr>
                        <td style="color:#666;">#<?php echo $row['id']; ?></td>
                        <td><?php echo $row['trans_date']; ?></td>
                        <td>
                            <?php if(!empty($row['real_supplier_id'])): ?>
                                <span style="color:var(--gold); font-weight:bold; font-size:1.05rem;">
                                    <i class="fa-solid fa-user-tie"></i> <?php echo $row['supplier_name']; ?>
                                </span>
                                <?php if(!empty($row['sup_cat'])) echo "<br><span class='sup-info'>({$row['sup_cat']})</span>"; ?>
                            <?php else: ?>
                                <span style="color:#aaa;">
                                    <i class="fa-solid fa-box-open"></i> مصروف عام / غير محدد
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['description']; ?>
                            <?php if($row['invoice_id']) echo " <span style='color:#e74c3c; font-size:0.8rem;'>(فاتورة #{$row['invoice_id']})</span>"; ?>
                        </td>
                        <td style="font-weight:bold; color:#e74c3c; font-size:1.1rem;" dir="ltr">
                            - <?php echo number_format($row['amount'], 2); ?>
                        </td>
                        <td>
                            <?php if(!empty($row['real_supplier_id'])): ?>
                                <div style="font-size:0.8rem; line-height:1.7;">
                                    <span class="balance-tag" style="border-color:#2ecc71; color:#2ecc71;">
                                        <i class="fa-solid fa-check"></i> مجموع ما تم سداده: <?php echo number_format($paid_total); ?>
                                    </span>
                                    <br>
                                    <span style="color: <?php echo $current_bal <= 0 ? '#2ecc71' : '#e74c3c'; ?>; font-weight:bold;">
                                        المتبقي له: <?php echo number_format($current_bal, 2); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <span style="color:#444; font-size:0.8rem;">-- لا ينطبق --</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="finance.php?edit=<?php echo $row['id']; ?>" class="btn-action" title="تعديل"><i class="fa-solid fa-pen"></i></a>
                            <a href="finance.php?del=<?php echo $row['id']; ?>" onclick="return confirm('تأكيد الحذف؟')" class="btn-action" style="color:#e74c3c;" title="حذف"><i class="fa-solid fa-trash-can"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#666;">لا توجد سجلات.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ob_end_flush(); ?>