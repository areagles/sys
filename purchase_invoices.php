<?php
// purchase_invoices.php - سجل فواتير المشتريات (متصل بالمحرك المالي)
require 'auth.php'; require 'config.php'; require 'header.php';

// حساب الإجماليات للمؤشرات العلوية
$totals = $conn->query("SELECT 
    SUM(total_amount) as grand_total, 
    SUM(paid_amount) as grand_paid, 
    SUM(remaining_amount) as grand_rem 
    FROM purchase_invoices")->fetch_assoc();
?>

<div class="container" style="margin-top:30px;">
    
    <div style="display:flex; gap:20px; margin-bottom:20px;">
        <div style="flex:1; background:#222; padding:20px; border-radius:10px; border-right:4px solid #3498db; color:#fff;">
            <div style="font-size:0.9rem; color:#aaa;">إجمالي المشتريات</div>
            <div style="font-size:1.5rem; font-weight:bold;"><?php echo number_format($totals['grand_total'], 2); ?></div>
        </div>
        <div style="flex:1; background:#222; padding:20px; border-radius:10px; border-right:4px solid #2ecc71; color:#fff;">
            <div style="font-size:0.9rem; color:#aaa;">المدفوع للموردين</div>
            <div style="font-size:1.5rem; font-weight:bold; color:#2ecc71;"><?php echo number_format($totals['grand_paid'], 2); ?></div>
        </div>
        <div style="flex:1; background:#222; padding:20px; border-radius:10px; border-right:4px solid #e74c3c; color:#fff;">
            <div style="font-size:0.9rem; color:#aaa;">المديونية (الآجل)</div>
            <div style="font-size:1.5rem; font-weight:bold; color:#e74c3c;"><?php echo number_format($totals['grand_rem'], 2); ?></div>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="color:var(--gold);">📦 سجل فواتير المشتريات</h2>
        <a href="add_purchase.php" class="btn-royal" style="background:var(--gold); color:#000; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;">+ فاتورة جديدة</a>
    </div>

    <div style="background:#1a1a1a; padding:20px; border-radius:10px; border:1px solid #333;">
        <table style="width:100%; border-collapse:collapse; color:#fff;">
            <thead>
                <tr style="border-bottom:2px solid #333; color:var(--gold);">
                    <th style="padding:10px; text-align:right;">#</th>
                    <th style="padding:10px; text-align:right;">المورد</th>
                    <th style="padding:10px; text-align:right;">التاريخ</th>
                    <th style="padding:10px; text-align:right;">الإجمالي</th>
                    <th style="padding:10px; text-align:right;">المدفوع</th>
                    <th style="padding:10px; text-align:right;">المتبقي</th>
                    <th style="padding:10px; text-align:right;">الحالة</th>
                    <th style="padding:10px; text-align:right;">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT p.*, s.name as supplier_name 
                        FROM purchase_invoices p 
                        JOIN suppliers s ON p.supplier_id = s.id 
                        ORDER BY p.inv_date DESC, p.id DESC";
                $res = $conn->query($sql);
                
                if($res->num_rows > 0):
                    while($row = $res->fetch_assoc()):
                        // تنسيق الحالة
                        $st = $row['status'];
                        $badge_color = ($st=='paid')?'#2ecc71':(($st=='partially_paid')?'#f1c40f':'#e74c3c');
                        $st_ar = ($st=='paid')?'خالصة':(($st=='partially_paid')?'جزئي':'آجل');
                ?>
                <tr style="border-bottom:1px solid #333;">
                    <td style="padding:15px; color:#666;">#<?php echo $row['id']; ?></td>
                    <td style="font-weight:bold;"><?php echo $row['supplier_name']; ?></td>
                    <td><?php echo $row['inv_date']; ?></td>
                    <td style="font-weight:bold; font-size:1.1rem;"><?php echo number_format($row['total_amount'], 2); ?></td>
                    <td style="color:#2ecc71;"><?php echo number_format($row['paid_amount'], 2); ?></td>
                    <td style="color:#e74c3c; font-weight:bold;"><?php echo number_format($row['remaining_amount'], 2); ?></td>
                    <td><span style="background:<?php echo $badge_color; ?>20; color:<?php echo $badge_color; ?>; padding:5px 10px; border-radius:15px; font-size:0.8rem;"><?php echo $st_ar; ?></span></td>
                    <td>
                        <a href="finance.php?def_type=out&def_cat=supplier&supplier_id=<?php echo $row['supplier_id']; ?>&invoice_id=<?php echo $row['id']; ?>&amount=<?php echo $row['remaining_amount']; ?>" 
                           title="سداد دفعة" style="color:#2ecc71; margin-left:10px;">
                           <i class="fa-solid fa-money-bill-wave"></i>
                        </a>
                        <a href="edit_purchase.php?id=<?php echo $row['id']; ?>" style="color:var(--gold); margin-left:10px;"><i class="fa-solid fa-pen"></i></a>
                        <a href="print_purchase.php?id=<?php echo $row['id']; ?>" target="_blank" style="color:#fff;"><i class="fa-solid fa-print"></i></a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:#666;">لا توجد فواتير مشتريات.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>