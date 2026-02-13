<?php
// add_payroll.php - إعداد مسير راتب موظف
ob_start();
require 'auth.php'; require 'config.php'; require 'header.php';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $emp_id = intval($_POST['employee_id']);
    $month = $_POST['month']; // YYYY-MM
    $basic = floatval($_POST['basic']);
    $bonus = floatval($_POST['bonus']);
    $deduct = floatval($_POST['deduct']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $net = ($basic + $bonus) - $deduct;

    // التأكد من عدم تكرار الراتب لنفس الشهر
    $check = $conn->query("SELECT id FROM payroll_sheets WHERE employee_id=$emp_id AND month_year='$month'");
    if($check->num_rows > 0){
        echo "<script>alert('❌ تم إصدار راتب هذا الشهر لهذا الموظف من قبل!');</script>";
    } else {
        $sql = "INSERT INTO payroll_sheets (employee_id, month_year, basic_salary, bonus, deductions, net_salary, status, notes)
                VALUES ('$emp_id', '$month', '$basic', '$bonus', '$deduct', '$net', 'pending', '$notes')";
        
        if($conn->query($sql)){
            echo "<script>alert('✅ تم اعتماد بيان الراتب بنجاح (بانتظار الصرف)'); window.location.href='payroll.php';</script>";
        }
    }
}
?>

<style>
    :root {
        --royal-gold: #d4af37;
        --royal-gold-dark: #aa8c2c;
        --dark-bg: #0f0f0f;
        --panel-bg: #1a1a1a;
        --text-color: #e0e0e0;
        --input-bg: #252525;
        --border-color: #333;
    }

    body {
        background-color: var(--dark-bg);
        font-family: 'Cairo', sans-serif;
        color: var(--text-color);
    }

    .royal-container {
        padding: 40px 20px;
        min-height: 80vh;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .royal-card {
        background: var(--panel-bg);
        border: 1px solid var(--royal-gold);
        border-radius: 15px;
        padding: 40px;
        width: 100%;
        max-width: 600px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        position: relative;
        overflow: hidden;
    }

    /* شريط ذهبي علوي */
    .royal-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--royal-gold), #fff, var(--royal-gold));
    }

    .page-title {
        color: var(--royal-gold);
        text-align: center;
        margin-bottom: 30px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }

    label {
        color: #b0b0b0;
        font-size: 0.95rem;
        margin-bottom: 8px;
        display: block;
        font-weight: 600;
    }

    /* تنسيق الحقول */
    select, input[type="month"], input[type="number"], textarea {
        width: 100%;
        padding: 12px 15px;
        background-color: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: #fff;
        font-size: 1rem;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        outline: none;
    }

    select:focus, input:focus, textarea:focus {
        border-color: var(--royal-gold);
        box-shadow: 0 0 10px rgba(212, 175, 55, 0.2);
    }

    /* منطقة الحسابات */
    .calc-box {
        background: #111; /* خلفية داكنة جداً للتباين */
        padding: 25px;
        border-radius: 12px;
        border: 1px dashed #444;
        margin-top: 10px;
        margin-bottom: 25px;
        position: relative;
    }

    .calc-row {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .calc-row label {
        width: 140px;
        margin-bottom: 0;
    }

    .calc-row input {
        margin-bottom: 0;
        flex: 1;
        text-align: left;
        font-family: monospace;
        letter-spacing: 1px;
    }

    .net-salary-display {
        background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(0,0,0,0));
        padding: 15px;
        border-radius: 8px;
        border-right: 4px solid var(--royal-gold);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
    }

    .net-salary-text {
        font-size: 1.2rem;
        font-weight: bold;
        color: #fff;
    }

    #net_salary {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--royal-gold);
        text-shadow: 0 0 10px rgba(212, 175, 55, 0.4);
    }

    /* الزر الملكي */
    .btn-royal {
        background: linear-gradient(45deg, var(--royal-gold-dark), var(--royal-gold));
        color: #000;
        border: none;
        padding: 15px;
        font-size: 1.1rem;
        font-weight: bold;
        border-radius: 50px;
        cursor: pointer;
        width: 100%;
        transition: transform 0.2s, box-shadow 0.2s;
        display: block;
        margin-top: 10px;
    }

    .btn-royal:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        color: #fff;
    }

    /* أيقونات داخلية */
    .icon-label { margin-left: 8px; }
</style>

<div class="royal-container">
    <div class="royal-card">
        <h2 class="page-title"><i class="fa-solid fa-file-invoice-dollar icon-label"></i> إعداد مسير راتب</h2>
        
        <form method="POST">
            <label><i class="fa-solid fa-user icon-label"></i> اختر الموظف</label>
            <select name="employee_id" required onchange="getSalary(this)">
                <option value="">-- القائمة --</option>
                <?php 
                // هنا بنفترض إنك ممكن تضيف عمود salary في جدول users مستقبلاً
                $users = $conn->query("SELECT id, full_name FROM users");
                while($u = $users->fetch_assoc()) echo "<option value='{$u['id']}'>{$u['full_name']}</option>";
                ?>
            </select>

            <label><i class="fa-solid fa-calendar-days icon-label"></i> عن شهر</label>
            <input type="month" name="month" value="<?php echo date('Y-m'); ?>" required>

            <div class="calc-box">
                <div class="calc-row">
                    <label><i class="fa-solid fa-money-bill-wave icon-label"></i> الراتب الأساسي</label>
                    <input type="number" name="basic" id="basic" step="0.01" value="0" oninput="calcNet()" required style="border-left: 3px solid #fff;">
                </div>
                
                <div class="calc-row">
                    <label style="color:#2ecc71;"><i class="fa-solid fa-plus-circle icon-label"></i> إضافي / مكافآت</label>
                    <input type="number" name="bonus" id="bonus" step="0.01" value="0" oninput="calcNet()" style="border-color:#2ecc71; color:#2ecc71;">
                </div>
                
                <div class="calc-row">
                    <label style="color:#e74c3c;"><i class="fa-solid fa-minus-circle icon-label"></i> خصومات / سلف</label>
                    <input type="number" name="deduct" id="deduct" step="0.01" value="0" oninput="calcNet()" style="border-color:#e74c3c; color:#e74c3c;">
                </div>

                <hr style="border-color:#333; margin: 20px 0;">
                
                <div class="net-salary-display">
                    <span class="net-salary-text">صافي الراتب المستحق:</span>
                    <span id="net_salary">0.00</span>
                </div>
            </div>

            <label><i class="fa-solid fa-note-sticky icon-label"></i> ملاحظات إدارية</label>
            <textarea name="notes" rows="3" placeholder="أدخل أي ملاحظات إضافية هنا..."></textarea>

            <button type="submit" class="btn-royal">
                <i class="fa-solid fa-check-circle icon-label"></i> حفظ واعتماد البيان
            </button>
        </form>
    </div>
</div>

<script>
function calcNet(){
    let basic = parseFloat(document.getElementById('basic').value) || 0;
    let bonus = parseFloat(document.getElementById('bonus').value) || 0;
    let deduct = parseFloat(document.getElementById('deduct').value) || 0;
    
    // معادلة الصافي
    let total = (basic + bonus - deduct);
    document.getElementById('net_salary').innerText = total.toFixed(2);
}
</script>

<?php include 'footer.php'; ?>