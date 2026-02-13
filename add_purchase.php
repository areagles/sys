<?php
// add_purchase.php - تسجيل فاتورة مشتريات (Royal Design V2.0)
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// --- معالجة الحفظ ---
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $supplier_id = intval($_POST['supplier_id']);
    $inv_date = $_POST['inv_date'];
    $due_date = $_POST['due_date'];
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // تجميع الأصناف
    $items = [];
    $sub_total = 0;
    if(isset($_POST['item_name'])){
        for($i=0; $i<count($_POST['item_name']); $i++){
            $desc = $conn->real_escape_string($_POST['item_name'][$i]);
            $qty = floatval($_POST['qty'][$i]);
            $price = floatval($_POST['price'][$i]);
            
            if(!empty($desc) && $qty > 0) {
                $total = $qty * $price;
                $sub_total += $total;
                $items[] = ['desc'=>$desc, 'qty'=>$qty, 'price'=>$price, 'total'=>$total];
            }
        }
    }
    
    if(empty($items)) {
        echo "<script>alert('❌ لا يمكن حفظ فاتورة فارغة!');</script>";
    } else {
        $json = json_encode($items, JSON_UNESCAPED_UNICODE);
        $tax = floatval($_POST['tax']);
        $discount = floatval($_POST['discount']);
        $grand_total = ($sub_total + $tax) - $discount;

        // 1. إنشاء الفاتورة
        $sql = "INSERT INTO purchase_invoices (supplier_id, inv_date, due_date, sub_total, tax, discount, total_amount, remaining_amount, status, items_json, notes) 
                VALUES ('$supplier_id', '$inv_date', '$due_date', '$sub_total', '$tax', '$discount', '$grand_total', '$grand_total', 'unpaid', '$json', '$notes')";
        
        if($conn->query($sql)){
            $inv_id = $conn->insert_id;
            // 2. تحديث رصيد المورد (دائن)
            $conn->query("UPDATE suppliers SET current_balance = current_balance + $grand_total WHERE id=$supplier_id");
            
            echo "<script>
                alert('✅ تم حفظ الفاتورة بنجاح رقم #$inv_id'); 
                window.location.href='purchase_invoices.php';
            </script>";
        } else {
            echo "<div class='alert-box error'>خطأ في قاعدة البيانات: " . $conn->error . "</div>";
        }
    }
}
?>

<style>
    :root { --gold: #d4af37; --dark-bg: #121212; --panel-bg: #1e1e1e; --border: #333; }
    
    .royal-card {
        background: var(--panel-bg); border: 1px solid var(--border);
        border-radius: 12px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        margin-top: 20px;
    }
    
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
    .page-title { color: var(--gold); margin: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }

    /* Form Elements */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .input-group label { display: block; color: #aaa; margin-bottom: 8px; font-size: 0.9rem; }
    .form-control {
        width: 100%; padding: 12px; background: #0a0a0a; border: 1px solid #444;
        color: #fff; border-radius: 8px; font-family: inherit; transition: 0.3s;
    }
    .form-control:focus { border-color: var(--gold); outline: none; box-shadow: 0 0 5px rgba(212, 175, 55, 0.2); }

    /* Table Styling */
    .table-container { overflow-x: auto; background: #151515; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; min-width: 700px; }
    th { background: #222; color: var(--gold); padding: 15px; text-align: center; border-bottom: 2px solid #333; font-weight: bold; }
    td { padding: 10px; border-bottom: 1px solid #222; }
    
    /* Inputs inside table */
    .table-input {
        width: 100%; background: transparent; border: none; border-bottom: 1px solid #444;
        color: #fff; text-align: center; padding: 8px; font-size: 1rem;
    }
    .table-input:focus { border-bottom-color: var(--gold); outline: none; }
    .text-left { text-align: right !important; } /* For Item Name */

    /* Totals Section */
    .totals-wrapper { display: flex; justify-content: flex-end; margin-top: 20px; }
    .totals-card { background: #151515; border: 1px solid var(--gold); border-radius: 10px; padding: 20px; width: 350px; }
    .total-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; color: #ccc; }
    .total-input { width: 80px; background: #000; border: 1px solid #444; color: #fff; padding: 5px; text-align: center; border-radius: 4px; }
    .grand-total { font-size: 1.4rem; color: var(--gold); border-top: 1px solid #333; padding-top: 10px; margin-top: 10px; font-weight: bold; }

    .btn-add { background: #222; color: #fff; border: 1px dashed #555; width: 100%; padding: 12px; cursor: pointer; border-radius: 8px; transition: 0.3s; }
    .btn-add:hover { background: #333; border-color: var(--gold); color: var(--gold); }
    
    .btn-save {
        background: linear-gradient(45deg, var(--gold), #b8860b); color: #000; font-weight: bold;
        border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; width: 100%;
        font-size: 1.1rem; transition: 0.3s; margin-top: 20px;
    }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212,175,55,0.3); }
    .del-btn { color: #e74c3c; cursor: pointer; transition: 0.3s; font-size: 1.1rem; }
    .del-btn:hover { transform: scale(1.2); }
</style>

<div class="container">
    <div class="royal-card">
        <div class="page-header">
            <h2 class="page-title"><i class="fa-solid fa-file-invoice-dollar"></i> تسجيل فاتورة مشتريات (وارد)</h2>
            <a href="purchase_invoices.php" style="color:#aaa; text-decoration:none;"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
        </div>
        
        <form method="POST">
            <div class="form-grid">
                <div class="input-group">
                    <label><i class="fa-solid fa-truck-field"></i> المورد</label>
                    <select name="supplier_id" class="form-control" required>
                        <option value="">-- اختر المورد --</option>
                        <?php 
                        $s_res = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
                        while($s = $s_res->fetch_assoc()) echo "<option value='{$s['id']}'>{$s['name']}</option>";
                        ?>
                    </select>
                </div>
                <div class="input-group">
                    <label><i class="fa-regular fa-calendar"></i> تاريخ الفاتورة</label>
                    <input type="date" name="inv_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="input-group">
                    <label><i class="fa-solid fa-hourglass-half"></i> تاريخ الاستحقاق</label>
                    <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="40%" style="text-align:right; padding-right:20px;">الصنف / البيان</th>
                            <th width="15%">الكمية</th>
                            <th width="15%">السعر</th>
                            <th width="20%">الإجمالي</th>
                            <th width="10%">حذف</th>
                        </tr>
                    </thead>
                    <tbody id="items_area">
                        <tr>
                            <td><input type="text" name="item_name[]" required placeholder="اسم المنتج..." class="table-input text-left" style="padding-right:10px;"></td>
                            <td><input type="number" step="0.01" name="qty[]" value="1" oninput="calc(this)" class="table-input"></td>
                            <td><input type="number" step="0.01" name="price[]" value="0" oninput="calc(this)" class="table-input"></td>
                            <td><input type="text" readonly class="row-total table-input" value="0.00" style="color:var(--gold); font-weight:bold;"></td>
                            <td style="text-align:center;"><i class="fa-solid fa-trash-can del-btn" onclick="deleteRow(this)"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <button type="button" onclick="addRow()" class="btn-add"><i class="fa-solid fa-plus"></i> إضافة سطر جديد</button>

            <div class="totals-wrapper">
                <div class="totals-card">
                    <div class="total-row">
                        <span>المجموع الفرعي:</span>
                        <span id="sub_total" style="font-weight:bold;">0.00</span>
                    </div>
                    <div class="total-row">
                        <span>(+) ضريبة / مصاريف:</span>
                        <input type="number" step="0.01" name="tax" value="0" oninput="calcAll()" class="total-input">
                    </div>
                    <div class="total-row">
                        <span>(-) خصم مكتسب:</span>
                        <input type="number" step="0.01" name="discount" value="0" oninput="calcAll()" class="total-input">
                    </div>
                    <div class="total-row grand-total">
                        <span>الصافي النهائي:</span>
                        <span id="grand_total">0.00 EGP</span>
                    </div>
                </div>
            </div>

            <div class="input-group" style="margin-top:20px;">
                <label><i class="fa-solid fa-note-sticky"></i> ملاحظات إضافية</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="اكتب أي تفاصيل أخرى هنا..."></textarea>
            </div>

            <button type="submit" class="btn-save"><i class="fa-solid fa-save"></i> حفظ الفاتورة وترحيل للحساب</button>
        </form>
    </div>
</div>

<script>
function addRow(){
    let tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="item_name[]" required placeholder="اسم المنتج..." class="table-input text-left" style="padding-right:10px;"></td>
        <td><input type="number" step="0.01" name="qty[]" value="1" oninput="calc(this)" class="table-input"></td>
        <td><input type="number" step="0.01" name="price[]" value="0" oninput="calc(this)" class="table-input"></td>
        <td><input type="text" readonly class="row-total table-input" value="0.00" style="color:var(--gold); font-weight:bold;"></td>
        <td style="text-align:center;"><i class="fa-solid fa-trash-can del-btn" onclick="deleteRow(this)"></i></td>
    `;
    document.getElementById('items_area').appendChild(tr);
}

function deleteRow(btn) {
    let rows = document.querySelectorAll('#items_area tr');
    if(rows.length > 1) {
        btn.closest('tr').remove();
        calcAll();
    } else {
        alert('يجب أن تحتوي الفاتورة على صنف واحد على الأقل.');
    }
}

function calc(el){
    let tr = el.closest('tr');
    let q = parseFloat(tr.querySelector('[name="qty[]"]').value) || 0;
    let p = parseFloat(tr.querySelector('[name="price[]"]').value) || 0;
    tr.querySelector('.row-total').value = (q * p).toFixed(2);
    calcAll();
}

function calcAll(){
    let sub = 0;
    document.querySelectorAll('.row-total').forEach(e => sub += parseFloat(e.value));
    document.getElementById('sub_total').innerText = sub.toFixed(2);
    
    let tax = parseFloat(document.querySelector('[name="tax"]').value) || 0;
    let disc = parseFloat(document.querySelector('[name="discount"]').value) || 0;
    
    let grand = (sub + tax) - disc;
    document.getElementById('grand_total').innerText = grand.toFixed(2) + ' EGP';
}
</script>

<?php include 'footer.php'; ?>