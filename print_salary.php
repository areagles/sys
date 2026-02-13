<?php
// print_salary.php - طباعة بيان الراتب
require 'auth.php'; 
require 'config.php';

$id = intval($_GET['id']);
$sql = "SELECT p.*, u.full_name, u.role FROM payroll_sheets p JOIN users u ON p.employee_id = u.id WHERE p.id=$id";
$res = $conn->query($sql);
if(!$res || $res->num_rows==0) die("بيان الراتب غير موجود");
$row = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بيان راتب - <?php echo $row['full_name']; ?></title>
    <style>
        body { font-family: 'Tahoma', sans-serif; padding: 40px; }
        .slip-box { border: 2px solid #000; padding: 20px; max-width: 600px; margin: auto; }
        .header { text-align: center; border-bottom: 2px solid #000; margin-bottom: 20px; padding-bottom: 10px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px dotted #ccc; padding-bottom: 5px; }
        .val { font-weight: bold; }
        .net-area { background: #eee; padding: 10px; font-size: 1.5rem; text-align: center; margin-top: 20px; border: 1px solid #000; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; margin-bottom:20px;">
        <button onclick="window.print()">🖨️ طباعة البيان</button>
    </div>

    <div class="slip-box">
        <div class="header">
            <h2>بيان صرف راتب (Pay Slip)</h2>
            <p>Arab Eagles Co.</p>
        </div>
        
        <div class="row"><span>الموظف:</span> <span class="val"><?php echo $row['full_name']; ?></span></div>
        <div class="row"><span>الوظيفة:</span> <span class="val"><?php echo $row['role']; ?></span></div>
        <div class="row"><span>عن شهر:</span> <span class="val" dir="ltr"><?php echo $row['month_year']; ?></span></div>
        
        <br>
        
        <div class="row"><span>الراتب الأساسي:</span> <span class="val"><?php echo number_format($row['basic_salary'], 2); ?></span></div>
        <div class="row" style="color:green;"><span>+ إضافي / مكافآت:</span> <span class="val"><?php echo number_format($row['bonus'], 2); ?></span></div>
        <div class="row" style="color:red;"><span>- خصومات / سلف:</span> <span class="val"><?php echo number_format($row['deductions'], 2); ?></span></div>
        
        <div class="net-area">
            صافي الراتب: <strong><?php echo number_format($row['net_salary'], 2); ?> EGP</strong>
        </div>

        <br>
        <div class="row"><span>الحالة:</span> <span class="val"><?php echo $row['status']=='paid'?'تم الصرف ✅':'معلق / جزئي'; ?></span></div>
        <div class="row"><span>المدفوع فعلياً:</span> <span class="val"><?php echo number_format($row['paid_amount'], 2); ?></span></div>
        
        <div style="margin-top:40px; display:flex; justify-content:space-between;">
            <div>توقيع المحاسب</div>
            <div>توقيع المستلم</div>
        </div>
    </div>
</body>
</html>