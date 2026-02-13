<?php
// edit_quote.php - (Royal Edition V2.0 - Fixed Design & Decimal Support)
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

if(!isset($_GET['id'])) { header("Location: quotes.php"); exit; }
$id = intval($_GET['id']);

// جلب البيانات
$quote = $conn->query("SELECT * FROM quotes WHERE id=$id")->fetch_assoc();
$items = $conn->query("SELECT * FROM quote_items WHERE quote_id=$id");

// معالجة التحديث
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = intval($_POST['client_id']);
    $date = $_POST['date'];
    $valid = $_POST['valid_until'];
    $notes = $conn->real_escape_string($_POST['notes']);

    // 1. حساب الإجمالي الجديد
    $grand_total = 0;
    if(isset($_POST['item_name'])){
        for($i=0; $i<count($_POST['item_name']); $i++){
            $grand_total += (floatval($_POST['qty'][$i]) * floatval($_POST['price'][$i]));
        }
    }

    // 2. تحديث الجدول الرئيسي
    $conn->query("UPDATE quotes SET client_id='$client_id', created_at='$date', valid_until='$valid', total_amount='$grand_total', notes='$notes' WHERE id=$id");

    // 3. حذف البنود القديمة بالكامل
    $conn->query("DELETE FROM quote_items WHERE quote_id=$id");

    // 4. إدراج البنود الجديدة
    if(isset($_POST['item_name'])){
        for($i=0; $i<count($_POST['item_name']); $i++){
            $iname = $conn->real_escape_string($_POST['item_name'][$i]);
            $iqty = floatval($_POST['qty'][$i]);
            $iprice = floatval($_POST['price'][$i]);
            $itotal = $iqty * $iprice;
            
            $conn->query("INSERT INTO quote_items (quote_id, item_name, quantity, price, total) VALUES ($id, '$iname', '$iqty', '$iprice', '$itotal')");
        }
    }

    header("Location: quotes.php?msg=updated"); exit;
}
?>

<style>
    :root { --gold: #d4af37; --dark: #050505; --panel: #151515; --border: #333; }
    
    .royal-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    
    .royal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid var(--gold); padding-bottom: 15px; }
    .royal-header h2 { color: var(--gold); margin: 0; font-family: 'Cairo', sans-serif; font-weight: 700; }
    
    .royal-card { background: var(--panel); padding: 25px; border-radius: 15px; border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    
    label { color: #888; font-size: 0.9rem; margin-bottom: 8px; display: block; }
    .royal-input { width: 100%; background: #0a0a0a; border: 1px solid #444; color: #fff; padding: 12px; border-radius: 8px; font-family: 'Cairo'; outline: none; transition: 0.3s; }
    .royal-input:focus { border-color: var(--gold); box-shadow: 0 0 10px rgba(212, 175, 55, 0.2); }
    
    /* Table Styling */
    .table-responsive { overflow-x: auto; background: #0a0a0a; border-radius: 10px; border: 1px solid #333; padding: 5px; }
    .royal-table { width: 100%; border-collapse: collapse; }
    .royal-table th { color: var(--gold); text-align: right; padding: 15px; border-bottom: 1px solid #333; font-size: 0.9rem; }
    .royal-table td { padding: 10px; border-bottom: 1px solid #222; vertical-align: middle; }
    
    /* Inputs inside table */
    .table-input { width: 100%; background: transparent; border: none; border-bottom: 1px solid #333; color: #eee; padding: 8px; text-align: center; font-weight: bold; }
    .table-input:focus { border-bottom-color: var(--gold); outline: none; }
    .table-input.text-start { text-align: right; }
    
    /* Buttons */
    .btn-royal { background: linear-gradient(45deg, var(--gold), #b8860b); color: #000; padding: 12px 30px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 10px; font-size: 1rem; transition: 0.3s; }
    .btn-royal:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4); }
    
    .btn-add-row { background: #333; color: #fff; padding: 8px 20px; border-radius: 20px; border: 1px solid #555; cursor: pointer; font-size: 0.85rem; margin-top: 15px; display: inline-block; }
    .btn-add-row:hover { border-color: var(--gold); color: var(--gold); }

    .del-row { color: #e74c3c; cursor: pointer; font-size: 1.2rem; transition: 0.2s; }
    .del-row:hover { transform: scale(1.2); }

    .total-display { font-size: 1.5rem; color: var(--gold); font-weight: bold; text-align: left; margin-top: 20px; }
</style>

<div class="royal-container">
    <form method="POST">
        <div class="royal-header">
            <h2><i class="fa-solid fa-file-pen"></i> تعديل عرض السعر #<?php echo $id; ?></h2>
            <button type="submit" class="btn-royal"><i class="fa-solid fa-floppy-disk"></i> حفظ التعديلات</button>
        </div>

        <div class="royal-card">
            <div class="form-grid">
                <div>
                    <label>العميل</label>
                    <select name="client_id" class="royal-input" required>
                        <?php 
                        $cli = $conn->query("SELECT id, name FROM clients");
                        while($c = $cli->fetch_assoc()){
                            $sel = ($c['id'] == $quote['client_id']) ? 'selected' : '';
                            echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label>تاريخ العرض</label>
                    <input type="date" name="date" class="royal-input" value="<?php echo date('Y-m-d', strtotime($quote['created_at'])); ?>" required>
                </div>
                <div>
                    <label>صالح حتى</label>
                    <input type="date" name="valid_until" class="royal-input" value="<?php echo $quote['valid_until']; ?>" required>
                </div>
            </div>

            <div class="table-responsive">
                <table class="royal-table">
                    <thead>
                        <tr>
                            <th width="40%">البيان / الصنف</th>
                            <th width="15%" style="text-align:center">الكمية</th>
                            <th width="20%" style="text-align:center">السعر</th>
                            <th width="20%" style="text-align:center">الإجمالي</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody id="items_container">
                        <?php while($item = $items->fetch_assoc()): ?>
                        <tr>
                            <td><input type="text" name="item_name[]" value="<?php echo htmlspecialchars($item['item_name']); ?>" required class="table-input text-start" placeholder="اسم المنتج..."></td>
                            <td><input type="number" step="0.01" name="qty[]" value="<?php echo $item['quantity']; ?>" class="table-input" oninput="calc(this)"></td>
                            <td><input type="number" step="0.01" name="price[]" value="<?php echo $item['price']; ?>" class="table-input" oninput="calc(this)"></td>
                            <td><input type="text" readonly class="table-input row-total" value="<?php echo $item['total']; ?>" style="color:var(--gold);"></td>
                            <td style="text-align:center;"><i class="fa-solid fa-xmark del-row" onclick="removeRow(this)"></i></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" onclick="addRow()" class="btn-add-row"><i class="fa-solid fa-plus"></i> إضافة بند جديد</button>

            <div class="total-display">
                الإجمالي: <span id="grand_total"><?php echo number_format($quote['total_amount'], 2); ?></span> EGP
            </div>

            <div style="margin-top:30px;">
                <label>ملاحظات وشروط</label>
                <textarea name="notes" class="royal-input" rows="3"><?php echo $quote['notes']; ?></textarea>
            </div>
        </div>
    </form>
</div>

<script>
function addRow() {
    let tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="item_name[]" required class="table-input text-start" placeholder="اسم المنتج..."></td>
        <td><input type="number" step="0.01" name="qty[]" value="1" class="table-input" oninput="calc(this)"></td>
        <td><input type="number" step="0.01" name="price[]" value="0" class="table-input" oninput="calc(this)"></td>
        <td><input type="text" readonly class="table-input row-total" value="0.00" style="color:var(--gold);"></td>
        <td style="text-align:center;"><i class="fa-solid fa-xmark del-row" onclick="removeRow(this)"></i></td>
    `;
    document.getElementById('items_container').appendChild(tr);
}

function removeRow(btn) {
    btn.closest('tr').remove();
    calcTotal();
}

function calc(el) {
    let tr = el.closest('tr');
    let q = parseFloat(tr.querySelector('[name="qty[]"]').value) || 0;
    let p = parseFloat(tr.querySelector('[name="price[]"]').value) || 0;
    let total = q * p;
    tr.querySelector('.row-total').value = total.toFixed(2);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('.row-total').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    // تنسيق الرقم ليظهر بشكل جميل
    document.getElementById('grand_total').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<?php include 'footer.php'; ob_end_flush(); ?>