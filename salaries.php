<?php
// salaries.php - مسيرات الرواتب (محدث: ربط ID المسير بالدفع)
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// تحديث الأعمدة لضمان عدم حدوث خطأ
$conn->query("ALTER TABLE payroll_sheets ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(10,2) DEFAULT 0.00");
$conn->query("ALTER TABLE payroll_sheets ADD COLUMN IF NOT EXISTS remaining_amount DECIMAL(10,2) DEFAULT 0.00");
$conn->query("UPDATE payroll_sheets SET remaining_amount = net_salary WHERE remaining_amount = 0 AND status = 'pending'");

$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$where = "WHERE 1=1";
if(!empty($search)){
    $where .= " AND (u.full_name LIKE '%$search%' OR p.month_year LIKE '%$search%')";
}

// جلب الرواتب
$sql = "SELECT p.*, u.full_name as emp_name 
        FROM payroll_sheets p 
        LEFT JOIN users u ON p.employee_id = u.id 
        $where 
        ORDER BY p.month_year DESC, p.id DESC";
$res = $conn->query($sql);
?>

<style>
    :root { --gold: #d4af37; --dark-bg: #0f0f0f; --panel-bg: #1a1a1a; }
    body { background-color: var(--dark-bg); color: #fff; font-family: 'Cairo'; }
    .table-container { background: var(--panel-bg); border-radius: 12px; overflow: hidden; border: 1px solid #333; margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #111; color: var(--gold); padding: 15px; text-align: right; border-bottom: 2px solid #333; }
    td { padding: 15px; border-bottom: 1px solid #222; vertical-align: middle; }
    
    .badge { padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: bold; }
    .badge-paid { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
    .badge-partially_paid { background: rgba(241, 196, 15, 0.2); color: #f1c40f; }
    .badge-pending { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
    
    .btn-action { text-decoration: none; margin-left: 5px; font-size: 1.1rem; transition: 0.3s; }
    .btn-action:hover { transform: scale(1.2); }
</style>

<div class="container" style="margin-top:40px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2 style="color:var(--gold); margin:0;">💰 مسيرات الرواتب الشهرية</h2>
        <a href="add_payroll.php" class="btn-royal">+ إعداد راتب جديد</a>
    </div>

    <form method="GET" style="margin-top:20px; display:flex; gap:10px;">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="بحث..." style="width:100%; padding:10px; background:#000; border:1px solid #333; color:#fff; border-radius:5px;">
        <button class="btn-royal" style="padding:10px 20px;">بحث</button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>الشهر</th>
                    <th>الموظف</th>
                    <th>صافي الراتب</th>
                    <th>المدفوع</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if($res && $res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): 
                        // تصحيح الأرقام القديمة
                        if($row['status'] == 'pending' && $row['remaining_amount'] == 0) $row['remaining_amount'] = $row['net_salary'];
                        
                        $st_class = "badge-".$row['status'];
                        $st_map = [
                            'pending' => '⏳ معلق',
                            'partially_paid' => '🌗 جزئي',
                            'paid' => '✅ مدفوع'
                        ];
                    ?>
                    <tr>
                        <td style="direction:ltr; font-family:monospace;"><?php echo $row['month_year']; ?></td>
                        <td style="font-weight:bold;"><?php echo $row['emp_name']; ?></td>
                        <td style="color:var(--gold); font-weight:bold;"><?php echo number_format($row['net_salary'], 2); ?></td>
                        <td style="color:#2ecc71;"><?php echo number_format($row['paid_amount'], 2); ?></td>
                        <td style="color:#e74c3c;"><?php echo number_format($row['remaining_amount'], 2); ?></td>
                        <td><span class="badge <?php echo $st_class; ?>"><?php echo $st_map[$row['status']] ?? $row['status']; ?></span></td>
                        <td>
                            <?php if($row['status'] != 'paid'): ?>
                                <a href="finance.php?def_type=out&def_cat=salary&emp_id=<?php echo $row['employee_id']; ?>&payroll_id=<?php echo $row['id']; ?>&amount=<?php echo $row['remaining_amount']; ?>&desc=دفعة راتب شهر <?php echo $row['month_year']; ?>" 
                                   class="btn-action" title="صرف دفعة" style="color:#2ecc71;">
                                   💸
                                </a>
                            <?php else: ?>
                                <span style="color:green;">✔</span>
                            <?php endif; ?>
                            
                            <a href="print_salary.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-action" title="طباعة" style="color:#fff;">🖨️</a>
                            <a href="edit_payroll.php?id=<?php echo $row['id']; ?>" class="btn-action" title="تعديل" style="color:var(--gold);">✏️</a>
                            
                            <?php if($_SESSION['role'] == 'admin'): ?>
                            <a href="invoices.php?del_type=salary&del_id=<?php echo $row['id']; ?>" onclick="return confirm('حذف المسير؟')" class="btn-action" style="color:#e74c3c;">🗑️</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:#666;">لا توجد مسيرات رواتب مسجلة.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ob_end_flush(); ?>