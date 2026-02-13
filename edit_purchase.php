<?php
// edit_purchase.php - تعديل فاتورة مشتريات (Royal Version & Logic Fix)
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. التحقق من وجود الفاتورة
if(!isset($_GET['id'])) header("Location: purchase_invoices.php");
$id = intval($_GET['id']);

$inv = $conn->query("SELECT * FROM purchase_invoices WHERE id=$id")->fetch_assoc();
if(!$inv) die("<div class='container' style='margin-top:50px; color:red; text-align:center;'>عفواً، الفاتورة غير موجودة.</div>");

$items = json_decode($inv['items_json'] ?? '[]', true);

// 2. معالجة الحفظ
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $inv_date = $_POST['inv_date'];
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // أ) تجميع البنود وحساب الإجمالي الجديد
    $sub_total = 0;
    $new_items = [];
    
    if(isset($_POST['item_name'])){
        for($i=0; $i<count($_POST['item_name']); $i++){
            $desc = $_POST['item_name'][$i];
            $qty = floatval($_POST['qty'][$i]);
            $price = floatval($_POST['price'][$i]);
            
            if(!empty($desc) && $qty > 0){
                $total = $qty * $price;
                $sub_total += $total;
                $new_items[] = ['desc'=>$desc, 'qty'=>$qty, 'price'=>$price, 'total'=>$total];
            }
        }
    }
    
    // ب) حساب الصوافي
    $tax = floatval($_POST['tax']);
    $discount = floatval($_POST['discount']);
    $new_grand_total = ($sub_total + $tax) - $discount;
    if($new_grand_total < 0) $new_grand_total = 0;

    $json = json_encode($new_items, JSON_UNESCAPED_UNICODE);

    // ج) المنطق المحاسبي (تحديث المتبقي والحالة)
    // 1. حساب الفرق لتحديث رصيد المورد
    $diff = $new_grand_total - $inv['total_amount'];
    
    // 2. حساب المتبقي الجديد بناءً على ما تم دفعه سابقاً
    $paid_amount = $inv['paid_amount']; // المدفوع لا يتغير من هذه الصفحة
    $new_remaining = $new_grand_total - $paid_amount;
    
    // 3. تحديد الحالة الجديدة تلقائياً
    $new_status = 'unpaid';
    if($new_remaining <= 0) {
        $new_remaining = 0;
        $new_status = 'paid';
    } elseif($paid_amount > 0) {
        $new_status = 'partially_paid';
    }

    // د) تنفيذ التحديث
    $sql = "UPDATE purchase_invoices SET 
            inv_date='$inv_date', 
            total_amount='$new_grand_total', 
            remaining_amount='$new_remaining',
            status='$new_status',
            items_json='$json', 
            notes='$notes' 
            WHERE id=$id";
            
    if($conn->query($sql)){
        // تحديث رصيد المورد (إذا كان النظام يعتمد على العمود Static)
        if($diff != 0){
            $supplier_id = $inv['supplier_id'];
            $conn->query("UPDATE suppliers SET current_balance = current_balance + ($diff) WHERE id=$supplier_id");
        }
        
        echo "<script>
                alert('✅ تم تحديث الفاتورة وإعادة احتساب المديونية بنجاح'); 
                window.location.href='purchase_invoices.php';
              </script>";
        exit;
    } else {
        $msg = "خطأ: " . $conn->error;
    }
}
?>

<style>
    :root { --gold: #d4af37; --bg-dark: #0f0f0f; --card-bg: #1a1a1a; }
    body { background-color: var(--bg-dark); color: #fff; font-family: 'Cairo'; }

    .royal-card { background: var(--card-bg); padding: 30px; border-radius: 15px; border: 1px solid #333; border-top: 4px solid var(--gold); margin-top: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    
    input, textarea, select { width: 100%; background: #050505; border: 1px solid #444; color: #fff; padding: 12px; border-radius: 6px; box-sizing: border-box; }
    input:focus, textarea:focus { border-color: var(--gold); outline: none; }
    label { color: var(--gold); margin-bottom: 5px; display: block; font-weight: bold; font-size: 0.9rem; }

    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th { text-align: right; color: #aaa; padding: 10px; border-bottom: 1px solid #333; font-size: 0.9rem; }
    td { padding: 5px; border-bottom: 1px solid #222; }
    
    .row-total { font-weight: bold; color: var(--gold); border: none; background: transparent; text-align: left; }
    
    .btn-action { cursor: pointer; padding: 5px 10px; border-radius: 5px; font-weight: bold; border: none; }
    .btn-add { background: #333; color: #fff; width: auto; margin-top: 10px; }
    .btn-save { background: linear-gradient(45deg, var(--gold), #b8860b); color: #000; width: 100%; padding: 15px; margin-top: 20px; font-size: 1.1rem; }
    .btn-del { color: #e74c3c; background: transparent; font-size: 1.2rem; }

    .totals-area { background: #222; padding: 20px; border-radius: 10px; margin-top: 20px; border: 1px solid #333; width: 300px; margin-right: auto; }
    .totals-row { display: flex; justify-content: space-between; margin-bottom: 10px; align-items: center; }
</style>

<div class="container">
    <div class="royal-card">
        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:20px;">
            <h2 style="color:var(--gold); margin:0;">✏️ تعديل فاتورة مشتريات #<?php echo $id; ?></h2>
            <a href="purchase_invoices.php" style="color:#aaa; text-decoration:none;"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
        </div>

        <form method="POST" id="invoiceForm">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div>
                    <label>تاريخ الفاتورة</label>
                    <input type="date" name="inv_date" value="<?php echo $inv['inv_date']; ?>" required>
                </div>
                <div>
                    <label>المورد (للقراءة فقط)</label>
                    <input type="text" value="<?php 
                        $sid = $inv['supplier_id']; 
                        echo $conn->query("SELECT name FROM suppliers WHERE id=$sid")->fetch_object()->name ?? 'مورد محذوف'; 
                    ?>" readonly style="opacity:0.6; cursor:not-allowed;">
                </div>
            </div>

            <table id="items_table">
                <thead>
                    <tr>
                        <th width="40%">الصنف / البيان</th>
                        <th width="15%">الكمية</th>
                        <th width="20%">السعر</th>
                        <th width="20%">الإجمالي</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($items): foreach($items as $item): ?>
                    <tr>
                        <td><input type="text" name="item_name[]" value="<?php echo $item['desc']; ?>" required placeholder="اسم الصنف"></td>
                        <td><input type="number" name="qty[]" value="<?php echo $item['qty']; ?>" step="0.01" oninput="calc(this)" placeholder="0"></td>
                        <td><input type="number" name="price[]" value="<?php echo $item['price']; ?>" step="0.01" oninput="calc(this)" placeholder="0.00"></td>
                        <td><input type="text" readonly class="row-total" value="<?php echo $item['total']; ?>"></td>
                        <td style="text-align:center;"><button type="button" class="btn-action btn-del" onclick="removeRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            
            <button type="button" class="btn-action btn-add" onclick="addRow()">+ إضافة بند جديد</button>

            <div class="totals-area">
                <div class="totals-row">
                    <span>الضريبة / رسوم:</span>
                    <input type="number" name="tax" value="<?php echo $inv['tax'] ?? 0; ?>" oninput="calcAll()" style="width:100px; padding:5px;">
                </div>
                <div class="totals-row">
                    <span>خصم:</span>
                    <input type="number" name="discount" value="<?php echo $inv['discount'] ?? 0; ?>" oninput="calcAll()" style="width:100px; padding:5px;">
                </div>
                <div style="border-top:1px solid #444; margin:10px 0;"></div>
                <div class="totals-row" style="font-size:1.2rem; font-weight:bold; color:var(--gold);">
                    <span>الإجمالي النهائي:</span>
                    <span id="grand_total"><?php echo number_format($inv['total_amount'], 2); ?></span>
                </div>
                <div style="text-align:center; font-size:0.8rem; color:#aaa; margin-top:5px;">
                    (مدفوع سابقاً: <?php echo number_format($inv['paid_amount'], 2); ?>)
                </div>
            </div>

            <div style="margin-top:20px;">
                <label>ملاحظات</label>
                <textarea name="notes" rows="2" placeholder="أي تفاصيل إضافية..."><?php echo $inv['notes']; ?></textarea>
            </div>

            <button type="submit" class="btn-action btn-save">
                <i class="fa-solid fa-save"></i> حفظ التعديلات وتحديث الحسابات
            </button>
        </form>
    </div>
</div>

<script>
// إضافة صف جديد
function addRow(){
    let tbody = document.querySelector('#items_table tbody');
    let tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="item_name[]" required placeholder="اسم الصنف"></td>
        <td><input type="number" name="qty[]" value="1" step="0.01" oninput="calc(this)"></td>
        <td><input type="number" name="price[]" value="0" step="0.01" oninput="calc(this)"></td>
        <td><input type="text" readonly class="row-total" value="0.00"></td>
        <td style="text-align:center;"><button type="button" class="btn-action btn-del" onclick="removeRow(this)">✕</button></td>
    `;
    tbody.appendChild(tr);
}

// حذف صف
function removeRow(btn){
    btn.closest('tr').remove();
    calcAll();
}

// حساب صف واحد
function calc(input){
    let tr = input.closest('tr');
    let q = parseFloat(tr.querySelector('input[name="qty[]"]').value) || 0;
    let p = parseFloat(tr.querySelector('input[name="price[]"]').value) || 0;
    tr.querySelector('.row-total').value = (q * p).toFixed(2);
    calcAll();
}

// حساب الإجمالي الكلي
function calcAll(){
    let sub = 0;
    document.querySelectorAll('.row-total').forEach(el => {
        sub += parseFloat(el.value) || 0;
    });

    let tax = parseFloat(document.querySelector('input[name="tax"]').value) || 0;
    let disc = parseFloat(document.querySelector('input[name="discount"]').value) || 0;

    let total = (sub + tax) - disc;
    if(total < 0) total = 0;

    document.getElementById('grand_total').innerText = total.toFixed(2);
}
</script>

<?php include 'footer.php'; ob_end_flush(); ?>