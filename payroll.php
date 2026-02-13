<?php
// payroll.php - أرشيف الرواتب
require 'auth.php'; require 'config.php'; require 'header.php';
?>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="color:var(--gold);">💰 سجل الرواتب الشهرية</h2>
        <a href="add_payroll.php" class="btn-royal">+ إعداد راتب جديد</a>
    </div>

    <div class="royal-card">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid #333; color:#aaa;">
                    <th>الشهر</th>
                    <th>الموظف</th>
                    <th>الأساسي</th>
                    <th>الإضافي</th>
                    <th>الخصم</th>
                    <th>الصافي</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT p.*, u.full_name 
                        FROM payroll_sheets p 
                        JOIN users u ON p.employee_id = u.id 
                        ORDER BY p.month_year DESC, p.id DESC";
                $res = $conn->query($sql);
                
                if($res->num_rows > 0):
                    while($row = $res->fetch_assoc()):
                ?>
                <tr style="border-bottom:1px solid #222;">
                    <td style="direction:ltr;"><?php echo $row['month_year']; ?></td>
                    <td style="font-weight:bold; color:#fff;"><?php echo $row['full_name']; ?></td>
                    <td><?php echo number_format($row['basic_salary']); ?></td>
                    <td style="color:#2ecc71;"><?php echo number_format($row['bonus']); ?></td>
                    <td style="color:#e74c3c;"><?php echo number_format($row['deductions']); ?></td>
                    <td style="color:var(--gold); font-weight:bold; font-size:1.1rem;"><?php echo number_format($row['net_salary']); ?></td>
                    <td>
                        <?php if($row['status'] == 'paid'): ?>
                            <span style="color:#2ecc71;">✅ تم الصرف</span>
                        <?php else: ?>
                            <span style="color:#f39c12;">⏳ بانتظار الصرف</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($row['status'] == 'pending'): ?>
                            <a href="finance.php?def_type=out&def_cat=salary&amount=<?php echo $row['net_salary']; ?>&desc=راتب شهر <?php echo $row['month_year']; ?> للموظف <?php echo $row['full_name']; ?>" class="btn-royal" style="padding:5px 10px; font-size:0.8rem;">اصرف الآن</a>
                        <?php else: ?>
                            <span style="color:#888;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:#666;">لا توجد بيانات رواتب.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>