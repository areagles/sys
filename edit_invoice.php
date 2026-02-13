<?php
// edit_invoice.php - (النسخة الإمبراطورية: تحديث حقول التاريخ)
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'auth.php'; 
require 'config.php'; 
require 'header.php';

/* ==================================================
   1. دالة المزامنة الذكية (Smart Sync) - (كما هي)
   ================================================== */
function syncInvoiceStatus($conn, $inv_id) {
    $inv_id = intval($inv_id);
    
    $res = $conn->query("SELECT total_amount, due_date FROM invoices WHERE id = $inv_id");
    $invoice = $res->fetch_assoc();
    if (!$invoice) return;

    $paid = (float)$conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE invoice_id = $inv_id AND type = 'in'")->fetch_row()[0];
    $expense = (float)$conn->query("SELECT IFNULL(SUM(amount),0) FROM financial_receipts WHERE invoice_id = $inv_id AND type = 'out'")->fetch_row()[0];

    $final_total = $invoice['total_amount'] + $expense;
    $remaining = round($final_total - $paid, 2);
    
    $today = date('Y-m-d');
    if ($remaining <= 0) {
        $status = 'paid'; 
    } elseif ($paid > 0) {
        $status = 'partially_paid'; 
    } elseif ($today <= $invoice['due_date']) {
        $status = 'deferred'; 
    } else {
        $status = 'overdue'; 
    }

    $conn->query("UPDATE invoices SET paid_amount = '$paid', remaining_amount = '$remaining', status = '$status' WHERE id = $inv_id");
}

/* ==================================================
   2. معالجة الحفظ (POST) - (كما هي)
   ================================================== */
$inv = ['client_id'=>'', 'inv_date'=>date('Y-m-d'), 'due_date'=>date('Y-m-d'), 'notes'=>'', 'items_json'=>'[]', 'tax'=>0, 'discount'=>0];
$edit_mode = false;

if(isset($_GET['id'])){
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM invoices WHERE id=$id");
    if($res->num_rows > 0) { 
        $inv = $res->fetch_assoc(); 
        $edit_mode = true; 
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $client = $_POST['client_id'];
    $date = $_POST['inv_date'];
    $due = $_POST['due_date'];
    $notes = $conn->real_escape_string($_POST['notes']);
    
    $items = [];
    $sub_total = 0;
    if(isset($_POST['item_desc'])){
        for($i=0; $i<count($_POST['item_desc']); $i++){
            $qty = floatval($_POST['item_qty'][$i]);
            $price = floatval($_POST['item_price'][$i]);
            $total = $qty * $price;
            $sub_total += $total;
            $items[] = ['desc'=>$_POST['item_desc'][$i], 'qty'=>$qty, 'price'=>$price, 'total'=>$total];
        }
    }
    
    $json = json_encode($items, JSON_UNESCAPED_UNICODE);
    $discount = floatval($_POST['discount']);
    $tax = floatval($_POST['tax_amount']);
    $grand_total = ($sub_total - $discount) + $tax;

    if($edit_mode){
        $sql = "UPDATE invoices SET client_id='$client', inv_date='$date', due_date='$due', sub_total='$sub_total', tax='$tax', discount='$discount', total_amount='$grand_total', items_json='$json', notes='$notes' WHERE id={$inv['id']}";
        if(!$conn->query($sql)) die("Error Updating: " . $conn->error);
        $target_id = $inv['id'];
    } else {
        $sql = "INSERT INTO invoices (client_id, inv_date, due_date, sub_total, tax, discount, total_amount, items_json, notes, paid_amount, remaining_amount, status) 
                VALUES ('$client', '$date', '$due', '$sub_total', '$tax', '$discount', '$grand_total', '$json', '$notes', 0, '$grand_total', 'deferred')";
        if(!$conn->query($sql)) die("Error Inserting: " . $conn->error);
        $target_id = $conn->insert_id;
    }
    
    syncInvoiceStatus($conn, $target_id);
    header("Location: print_invoice.php?id=$target_id"); exit;
}
?>

<style>
    :root {
        --royal-gold: #d4af37;
        --royal-gold-hover: #b8860b;
        --dark-bg: #121212;
        --card-bg: #1e1e1e;
        --input-bg: #2c2c2c;
        --border-color: #3a3a3a;
        --text-main: #e0e0e0;
        --text-muted: #a0a0a0;
        --danger: #ff4d4d;
        --info: #17a2b8;
    }

    body { background-color: var(--dark-bg); color: var(--text-main); font-family: 'Tajawal', sans-serif; }
    
    /* Layout */
    .container { max-width: 1200px; margin: 30px auto; padding: 0 15px; }
    .royal-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }

    /* Header */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .page-title { color: var(--royal-gold); font-size: 1.8rem; font-weight: 700; margin: 0; }

    /* Grid & Inputs */
    .invoice-meta-grid { 
        display: grid; 
        grid-template-columns: 2fr 1fr 1fr; 
        gap: 40px; 
        align-items: end;
    }
    
    .form-group label { display: block; color: var(--royal-gold); margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
    
    .form-control { 
        width: 100%; 
        background: var(--input-bg); 
        border: 1px solid var(--border-color); 
        color: #fff; 
        padding: 12px; 
        border-radius: 6px; 
        transition: 0.3s; 
    }
    .form-control:focus { border-color: var(--royal-gold); outline: none; }

    /* Date Picker Specific Style */
    input[type="date"] {
        cursor: pointer; /* يجعل المؤشر يد عند المرور */
        position: relative;
    }
    /* محاولة توحيد شكل الرزنامة للمتصفحات المختلفة */
    input[type="date"]::-webkit-calendar-picker-indicator {
        background: transparent;
        bottom: 0;
        color: transparent;
        cursor: pointer;
        height: auto;
        left: 0;
        position: absolute;
        right: 0;
        top: 0;
        width: auto;
    }

    /* Table */
    .items-table { width: 100%; border-collapse: separate; border-spacing: 0 5px; }
    .items-table th { text-align: right; color: #888; padding: 10px; border-bottom: 1px solid #444; }
    .items-table td { background: #252525; padding: 5px 10px; border-top: 1px solid #333; border-bottom: 1px solid #333; }
    .items-table td:first-child { border-radius: 0 6px 6px 0; border-right: 1px solid #333; }
    .items-table td:last-child { border-radius: 6px 0 0 6px; border-left: 1px solid #333; text-align: center; }

    .table-input { width: 100%; background: transparent; border: none; color: #fff; padding: 8px; text-align: center; font-size: 1rem; }
    .table-input:focus { background: #333; border-radius: 4px; outline: none; }

    /* Buttons */
    .btn-royal { background: linear-gradient(45deg, var(--royal-gold), #b8860b); border: none; padding: 10px 25px; color: #000; font-weight: bold; border-radius: 6px; cursor: pointer; }
    .btn-add-row { width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 2px dashed var(--border-color); color: var(--text-muted); border-radius: 8px; cursor: pointer; transition: 0.3s; margin-top: 10px; }
    .btn-add-row:hover { border-color: var(--royal-gold); color: var(--royal-gold); }

    /* Action Buttons */
    .actions-cell { display: flex; gap: 5px; justify-content: center; }
    .btn-icon { width: 32px; height: 32px; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .btn-del { background: rgba(255, 77, 77, 0.1); color: var(--danger); }
    .btn-del:hover { background: var(--danger); color: #fff; }
    .btn-dup { background: rgba(23, 162, 184, 0.1); color: var(--info); }
    .btn-dup:hover { background: var(--info); color: #fff; }

    /* Totals */
    .totals-section { background: #000; padding: 20px; border-radius: 8px; border: 1px solid #333; }
    .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; color: #ccc; align-items: center; }
    .grand-total { border-top: 1px solid #333; margin-top: 10px; padding-top: 10px; font-size: 1.3rem; color: var(--royal-gold); font-weight: bold; }
</style>

<div class="container" dir="rtl">
    <form method="POST" id="invoiceForm">
        
        <div class="page-header">
            <h2 class="page-title"><i class="fa-solid fa-file-invoice-dollar"></i> <?php echo $edit_mode ? 'تعديل الفاتورة #'.$inv['id'] : 'إنشاء فاتورة جديدة'; ?></h2>
            <button type="submit" class="btn-royal big-save-btn"><i class="fa-solid fa-floppy-disk"></i> حفظ وطباعة</button>
        </div>

        <div class="royal-card invoice-body">
            
            <div class="invoice-meta-grid">
                <div class="form-group">
                    <label><i class="fa-solid fa-user-tie"></i> العميل</label>
                    <select name="client_id" required class="form-control">
                        <option value="">-- اختر العميل --</option>
                        <?php 
                        $c_list = $conn->query("SELECT id, name FROM clients ORDER BY name ASC");
                        while($r=$c_list->fetch_assoc()){
                            $sel = ($r['id'] == $inv['client_id']) ? 'selected' : '';
                            echo "<option value='{$r['id']}' $sel>{$r['name']}</option>"; 
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>تاريخ الفاتورة</label>
                    <input type="date" name="inv_date" value="<?php echo $inv['inv_date']; ?>" class="form-control" onclick="try{this.showPicker()}catch(e){}">
                </div>
                
                <div class="form-group">
                    <label>تاريخ الاستحقاق</label>
                    <input type="date" name="due_date" value="<?php echo $inv['due_date']; ?>" class="form-control" onclick="try{this.showPicker()}catch(e){}">
                </div>
            </div>

            <hr style="border-color:#333; margin: 30px 0;">

            <table class="items-table">
                <thead>
                    <tr>
                        <th width="40%">البيان</th>
                        <th width="15%" style="text-align:center;">الكمية</th>
                        <th width="15%" style="text-align:center;">السعر</th>
                        <th width="20%" style="text-align:center;">الإجمالي</th>
                        <th width="10%">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="items_container"></tbody>
            </table>
            
            <button type="button" onclick="addItem()" class="btn-add-row">+ إضافة بند جديد</button>

            <div class="invoice-footer" style="display:flex; flex-wrap:wrap; gap:30px; margin-top:30px;">
                <div style="flex:2; min-width:300px;">
                    <label style="color:var(--royal-gold);">ملاحظات</label>
                    <textarea name="notes" class="form-control" style="height:120px; margin-top:5px;"><?php echo $inv['notes']; ?></textarea>
                </div>
                <div class="totals-section" style="flex:1; min-width:250px;">
                    <div class="total-row"><span>المجموع</span> <span id="sub_total">0.00</span></div>
                    <div class="total-row">
                        <span>خصم</span> 
                        <input type="number" name="discount" id="discount" value="<?php echo $inv['discount']; ?>" oninput="calcTotals()" style="width:80px; background:#222; border:1px solid #444; color:#fff; text-align:center; border-radius:4px;">
                    </div>
                    <div class="total-row">
                        <span>
                            <input type="checkbox" id="tax_check" onchange="calcTotals()" <?php echo ($inv['tax']>0)?'checked':''; ?> style="accent-color:var(--royal-gold);"> 
                            ضريبة (14%)
                        </span>
                        <input type="hidden" name="tax_amount" id="tax_amount" value="<?php echo $inv['tax']; ?>">
                        <span id="tax_display">0.00</span>
                    </div>
                    <div class="total-row grand-total">
                        <span>الإجمالي النهائي</span> <span id="grand_total">0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let itemsData = <?php echo $inv['items_json'] ?: '[]'; ?>;

function addItem(desc='', qty=1, price=0) {
    let tbody = document.getElementById('items_container');
    let tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="item_desc[]" value="${desc}" placeholder="وصف الصنف" class="table-input" style="text-align:right;"></td>
        <td><input type="number" name="item_qty[]" value="${qty}" oninput="calcRow(this)" step="0.01" class="table-input"></td>
        <td><input type="number" name="item_price[]" value="${price}" oninput="calcRow(this)" step="0.01" class="table-input"></td>
        <td><input type="text" class="table-input row-total" readonly value="${(qty*price).toFixed(2)}" style="color:var(--royal-gold);"></td>
        <td>
            <div class="actions-cell">
                <button type="button" onclick="duplicateRow(this)" title="تكرار الصف" class="btn-icon btn-dup"><i class="fa-solid fa-copy"></i></button>
                <button type="button" onclick="removeRow(this)" title="حذف الصف" class="btn-icon btn-del"><i class="fa-solid fa-trash"></i></button>
            </div>
        </td>
    `;
    tbody.appendChild(tr);
    calcTotals();
}

function duplicateRow(btn) {
    let tr = btn.closest('tr');
    let desc = tr.querySelector('[name="item_desc[]"]').value;
    let qty = tr.querySelector('[name="item_qty[]"]').value;
    let price = tr.querySelector('[name="item_price[]"]').value;
    addItem(desc, qty, price);
}

function removeRow(btn) {
    if(confirm('هل تريد حذف هذا البند؟')) {
        btn.closest('tr').remove();
        calcTotals();
    }
}

function calcRow(el) {
    let tr = el.closest('tr');
    let qty = parseFloat(tr.querySelector('[name="item_qty[]"]').value) || 0;
    let price = parseFloat(tr.querySelector('[name="item_price[]"]').value) || 0;
    tr.querySelector('.row-total').value = (qty * price).toFixed(2);
    calcTotals();
}

function calcTotals() {
    let sub = 0;
    document.querySelectorAll('.row-total').forEach(e => sub += parseFloat(e.value));
    let disc = parseFloat(document.getElementById('discount').value) || 0;
    let taxable = sub - disc;
    if(taxable < 0) taxable = 0;
    
    let tax = 0;
    if(document.getElementById('tax_check').checked) tax = taxable * 0.14;
    
    document.getElementById('sub_total').innerText = sub.toFixed(2);
    document.getElementById('tax_display').innerText = tax.toFixed(2);
    document.getElementById('tax_amount').value = tax.toFixed(2);
    document.getElementById('grand_total').innerText = (taxable + tax).toFixed(2);
}

window.onload = () => {
    if(itemsData.length > 0) { itemsData.forEach(i => addItem(i.desc, i.qty, i.price)); } else { addItem(); }
};
</script>
<?php include 'footer.php'; ob_end_flush(); ?>