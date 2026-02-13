<?php
// edit_payroll.php - تعديل مسير راتب
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

$id = intval($_GET['id']);
$row = $conn->query("SELECT * FROM payroll_sheets WHERE id=$id")->fetch_assoc();

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $basic = floatval($_POST['basic']);
    $bonus = floatval($_POST['bonus']);
    $deduct = floatval($_POST['deduct']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    $net = ($basic + $bonus) - $deduct;
    // تحديث المتبقي بناء على ما تم دفعه سابقاً
    $remaining = $net - $row['paid_amount'];
    if($remaining < 0) $remaining = 0; // حماية
    
    $sql = "UPDATE payroll_sheets SET basic_salary='$basic', bonus='$bonus', deductions='$deduct', net_salary='$net', remaining_amount='$remaining', notes='$notes' WHERE id=$id";
    
    if($conn->query($sql)){
        echo "<script>alert('✅ تم تعديل الراتب بنجاح'); window.location.href='invoices.php?tab=salaries';</script>";
    }
}
?>

<div class="container">
    <div class="royal-card" style="max-width:500px; margin:auto;">
        <h2 style="color:var(--gold);">تعديل راتب</h2>
        <form method="POST">
            <label>الراتب الأساسي</label>
            <input type="number" name="basic" id="basic" value="<?php echo $row['basic_salary']; ?>" oninput="calcNet()" step="0.01">
            
            <label>إضافي</label>
            <input type="number" name="bonus" id="bonus" value="<?php echo $row['bonus']; ?>" oninput="calcNet()" step="0.01">
            
            <label>خصومات</label>
            <input type="number" name="deduct" id="deduct" value="<?php echo $row['deductions']; ?>" oninput="calcNet()" step="0.01">
            
            <div style="background:#222; padding:10px; margin:10px 0; text-align:center;">
                الصافي الجديد: <span id="net" style="color:var(--gold); font-weight:bold;"><?php echo $row['net_salary']; ?></span>
            </div>
            
            <label>ملاحظات</label>
            <textarea name="notes"><?php echo $row['notes']; ?></textarea>
            
            <button type="submit" class="btn-royal" style="width:100%; margin-top:10px;">حفظ</button>
        </form>
    </div>
</div>

<script>
function calcNet(){
    let b = parseFloat(document.getElementById('basic').value) || 0;
    let bo = parseFloat(document.getElementById('bonus').value) || 0;
    let d = parseFloat(document.getElementById('deduct').value) || 0;
    document.getElementById('net').innerText = (b + bo - d).toFixed(2);
}
</script>
<?php include 'footer.php'; ob_end_flush(); ?>