<?php
// edit_job.php - (V4.1 - CRITICAL FIX & Inventory Integration)
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production security

require 'auth.php';
require 'config.php';

// =========================================================
// 0. بروتوكول الإصلاح الذاتي لقاعدة البيانات (V2)
// =========================================================
$job_orders_schema = [
    'price' => "DECIMAL(10,2) DEFAULT 0.00",
    'paid'  => "DECIMAL(10,2) DEFAULT 0.00",
    'quantity' => "INT(11) DEFAULT 0",
    'job_details' => "TEXT"
];
foreach ($job_orders_schema as $col => $def) {
    if (!$conn->query("SHOW COLUMNS FROM job_orders LIKE '$col'")->num_rows) {
        $conn->query("ALTER TABLE job_orders ADD COLUMN $col $def");
    }
}

$job_materials_sql = "CREATE TABLE IF NOT EXISTS `job_materials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL,
    `quantity_used` DECIMAL(10, 2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `job_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($job_materials_sql);
// =========================================================

// 1. التحقق من الصلاحيات والرابط
if(in_array($_SESSION['role'], ['driver', 'worker'])) { header("Location: dashboard.php?error=unauthorized"); exit; }
if(!isset($_GET['id']) || empty($_GET['id'])) { header("Location: dashboard.php"); exit; }
$id = intval($_GET['id']);

// 2. معالجة الحفظ (PRG Pattern + Prepared Statements + Transactions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $conn->begin_transaction();
    try {
        // A. Update main job order details
        $stmt = $conn->prepare("UPDATE job_orders SET job_name=?, client_id=?, delivery_date=?, price=?, paid=?, quantity=?, notes=?, job_details=?, current_stage=? WHERE id=?");
        $stmt->bind_param("sisddisssi", $_POST['job_name'], $_POST['client_id'], $_POST['delivery_date'], $_POST['price'], $_POST['paid'], $_POST['quantity'], $_POST['notes'], $_POST['job_details'], $_POST['current_stage'], $id);
        $stmt->execute();

        // B. Process materials - Restore old quantities, then deduct new ones
        $old_materials_q = $conn->execute_query("SELECT * FROM job_materials WHERE job_id = ?", [$id]);
        foreach ($old_materials_q as $item) {
            $conn->execute_query("UPDATE product_stock SET quantity = quantity + ? WHERE product_id = ? AND warehouse_id = ?", [$item['quantity_used'], $item['product_id'], $item['warehouse_id']]);
        }
        $conn->execute_query("DELETE FROM job_materials WHERE job_id = ?", [$id]);

        if (!empty($_POST['materials'])) {
            $material_insert_stmt = $conn->prepare("INSERT INTO job_materials (job_id, product_id, warehouse_id, quantity_used) VALUES (?, ?, ?, ?)");
            $stock_update_stmt = $conn->prepare("UPDATE product_stock SET quantity = quantity - ? WHERE product_id = ? AND warehouse_id = ?");
            foreach ($_POST['materials'] as $mat) {
                $product_id = intval($mat['product_id']);
                $wh_id = intval($mat['warehouse_id']);
                $qty = floatval($mat['quantity']);
                if ($product_id > 0 && $wh_id > 0 && $qty > 0) {
                    $material_insert_stmt->bind_param("iiid", $id, $product_id, $wh_id, $qty);
                    $material_insert_stmt->execute();
                    $stock_update_stmt->bind_param("dii", $qty, $product_id, $wh_id);
                    $stock_update_stmt->execute();
                }
            }
        }

        $conn->commit();
        header("Location: job_details.php?id=$id&success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = urlencode("An error occurred: " . $e->getMessage());
        header("Location: edit_job.php?id=$id&error=".$error_msg);
        exit;
    }
}

// 3. جلب كل البيانات اللازمة للعرض
$job = $conn->execute_query("SELECT * FROM job_orders WHERE id = ?", [$id])->fetch_assoc();
if (!$job) die("Order not found");

$clients = $conn->query("SELECT id, name FROM clients ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("SELECT p.id, p.name, p.sku, ps.warehouse_id, w.name as warehouse_name, ps.quantity as stock_quantity FROM products p JOIN product_stock ps ON p.id = ps.product_id JOIN warehouses w ON ps.warehouse_id = w.id WHERE ps.quantity >= 0 ORDER BY p.name, w.name")->fetch_all(MYSQLI_ASSOC);
$used_materials = $conn->execute_query("SELECT jm.*, p.name, p.sku, w.name as warehouse_name FROM job_materials jm JOIN products p ON jm.product_id = p.id JOIN warehouses w ON jm.warehouse_id = w.id WHERE jm.job_id = ?", [$id])->fetch_all(MYSQLI_ASSOC);
$stages = ['briefing'=>'📝 أمر التشغيل', 'design'=>'🎨 التصميم', 'client_rev'=>'⏳ مراجعة العميل', 'pre_press'=>'⚙️ التجهيز الفني', 'printing'=>'🖨️ الطباعة / الإنتاج', 'finishing'=>'✨ التشطيب', 'die_cutting'=>'✂️ التكسير', 'gluing'=>'🧴 اللصق', 'delivery'=>'🚚 التسليم', 'completed'=>'✅ منتهي'];

require 'header.php';
?>

<style>
    :root { --gold: #d4af37; --bg: #121212; --panel: #1e1e1e; --input-bg: #0a0a0a; --border: #333; --danger: #e74c3c; --success: #2ecc71; }
    .edit-card { background: var(--panel); border: 1px solid var(--border); border-top: 4px solid var(--gold); border-radius: 10px; padding: 30px; max-width: 1000px; margin: 30px auto; }
    .section-title { color: var(--gold); font-size: 1.1rem; border-bottom: 1px dashed var(--border); padding-bottom: 10px; margin: 25px 0 15px 0; display: flex; align-items: center; gap: 10px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #aaa; }
    input, select, textarea { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid var(--border); color: #fff; border-radius: 6px; font-family: 'Cairo'; box-sizing: border-box; }
    .btn-save { background: linear-gradient(45deg, var(--gold), #b8860b); color: #000; border: none; padding: 15px 40px; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 1.1rem; width: 100%; margin-top: 30px; }
    .royal-alert.error { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;}
    #materials_list { list-style: none; padding: 0; margin-top: 15px; }
    .material-item { display: grid; grid-template-columns: 1fr 100px 80px; gap: 10px; align-items: center; background: #111; padding: 10px; border-radius: 6px; margin-bottom: 8px; font-size: 0.9rem; }
    .material-item .remove-material { color: var(--danger); cursor: pointer; text-align: center; }
    .add-material-form { display: grid; grid-template-columns: 1fr 1fr 120px 100px; gap: 10px; align-items: flex-end; margin-top: 20px; padding: 15px; background: #000; border-radius: 8px; }
    .btn-add-material { background: var(--success); color: #fff; border:none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; }
</style>

<div class="edit-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0; color:#fff;">✏️ تعديل أمر الشغل #<?php echo $job['id']; ?></h2>
        <a href="job_details.php?id=<?php echo $id; ?>" style="color:#aaa; text-decoration:none;">↩️ إلغاء وعودة</a>
    </div>
    <?php if(isset($_GET['error'])) echo "<div class='royal-alert error'>".htmlspecialchars(urldecode($_GET['error']))."</div>"; ?>

    <form method="POST">
        <div class="section-title"><i class="fa-solid fa-file-signature"></i> البيانات الأساسية</div>
        <div class="grid-2">
            <div><label>اسم العملية</label><input type="text" name="job_name" value="<?php echo htmlspecialchars($job['job_name']); ?>" required></div>
            <div><label>العميل</label><select name="client_id">
                <?php foreach($clients as $c) { $sel = ($c['id'] == $job['client_id']) ? 'selected' : ''; echo "<option value='{$c['id']}' $sel>{$c['name']}</option>"; } ?>
            </select></div>
        </div>
        <div class="grid-2" style="margin-top:15px;">
            <div><label>تاريخ التسليم</label><input type="date" name="delivery_date" value="<?php echo $job['delivery_date']; ?>" required></div>
            <div><label>الكمية المطلوبة</label><input type="number" name="quantity" value="<?php echo $job['quantity']; ?>"></div>
        </div>

        <div class="section-title"><i class="fa-solid fa-microchip"></i> التفاصيل الفنية</div>
        <textarea name="job_details" rows="8" style="font-family:monospace; line-height:1.6;"><?php echo htmlspecialchars($job['job_details']); ?></textarea>
        
        <div class="section-title"><i class="fa-solid fa-boxes-stacked"></i> المواد المستخدمة من المخزون</div>
        <div id="materials_container">
            <ul id="materials_list"></ul>
        </div>
        <div class="add-material-form">
            <div><label>اختيار المنتج</label><select id="new_material_product"><option value="">-- اختر منتج --</option></select></div>
            <div><label>المخزن</label><select id="new_material_warehouse"><option value="">-- اختر منتج أولاً --</option></select></div>
            <div><label>الكمية</label><input type="number" id="new_material_quantity" step="0.01" placeholder="0.00"></div>
            <button type="button" id="add_material_btn" class="btn-add-material"><i class="fa fa-plus"></i> إضافة</button>
        </div>

        <div class="section-title"><i class="fa-solid fa-coins"></i> الملف المالي</div>
        <div class="grid-2">
            <div><label>إجمالي السعر</label><input type="number" step="0.01" name="price" style="color:var(--success);" value="<?php echo $job['price']; ?>"></div>
            <div><label>المدفوع</label><input type="number" step="0.01" name="paid" style="color:var(--success);" value="<?php echo $job['paid']; ?>"></div>
        </div>

        <div class="section-title"><i class="fa-solid fa-sliders"></i> التحكم والمرحلة</div>
        <div><label>المرحلة الحالية</label><select name="current_stage" style="border-color: #e67e22;">
            <?php foreach($stages as $key => $label) { $sel = ($key == $job['current_stage']) ? 'selected' : ''; echo "<option value=\"$key\" $sel>$label</option>"; } ?>
        </select></div>
        <div style="margin-top:15px;"><label>ملاحظات إدارية</label><textarea name="notes" rows="3"><?php echo htmlspecialchars($job['notes']); ?></textarea></div>

        <button type="submit" name="update_job" class="btn-save">💾 حفظ كل التعديلات</button>
    </form>
</div>

<script>
// Data passed from PHP
const allProducts = <?php echo json_encode(array_values($products), JSON_UNESCAPED_UNICODE); ?>;
const usedMaterials = <?php echo json_encode($used_materials, JSON_UNESCAPED_UNICODE); ?>;
let materialIndex = 0;

// DOM Elements
const productSelect = document.getElementById('new_material_product');
const warehouseSelect = document.getElementById('new_material_warehouse');

// --- Functions to manage material list --- //
function addMaterialToList(material) {
    const list = document.getElementById('materials_list');
    const item = document.createElement('li');
    item.className = 'material-item';
    item.innerHTML = `
        <span><strong>${material.productName}</strong><br><small>من: ${material.warehouseName}</small></span>
        <span>${material.quantity}</span>
        <span class="remove-material" onclick="this.parentElement.remove()">✖</span>
        <input type="hidden" name="materials[${materialIndex}][product_id]" value="${material.productId}">
        <input type="hidden" name="materials[${materialIndex}][warehouse_id]" value="${material.warehouseId}">
        <input type="hidden" name="materials[${materialIndex}][quantity]" value="${material.quantity}">
    `;
    list.appendChild(item);
    materialIndex++;
}

function updateWarehouseOptions() {
    const productId = productSelect.value;
    warehouseSelect.innerHTML = '<option value="">-- اختر مخزن --</option>';
    if (productId) {
        const available = allProducts.filter(p => p.id == productId);
        available.forEach(item => {
            warehouseSelect.innerHTML += `<option value="${item.warehouse_id}">${item.warehouse_name} (المتاح: ${item.stock_quantity})</option>`;
        });
    }
}

function populateProductSelect() {
    const uniqueProducts = [...new Map(allProducts.map(item => [item['id'], item])).values()];
    uniqueProducts.forEach(p => {
        productSelect.innerHTML += `<option value="${p.id}">${p.name} (${p.sku})</option>`;
    });
}

// --- Event Listeners --- //
productSelect.addEventListener('change', updateWarehouseOptions);

document.getElementById('add_material_btn').addEventListener('click', function(){
    const productId = productSelect.value;
    const warehouseId = warehouseSelect.value;
    const quantity = parseFloat(document.getElementById('new_material_quantity').value);
    
    if (!productId || !warehouseId || !quantity || quantity <= 0) {
        alert('يرجى اختيار منتج ومخزن وكمية صحيحة.'); return;
    }

    const product = allProducts.find(p => p.id == productId);
    const warehouse = allProducts.find(p => p.id == productId && p.warehouse_id == warehouseId);

    if (quantity > parseFloat(warehouse.stock_quantity)) {
        if (!confirm('تحذير: الكمية المطلوبة أكبر من المتاح في المخزن. هل تريد المتابعة على أي حال؟')) {
            return;
        }
    }

    addMaterialToList({
        productId: productId,
        warehouseId: warehouseId,
        quantity: quantity,
        productName: product.name,
        warehouseName: warehouse.warehouse_name
    });
    document.getElementById('new_material_quantity').value = '';
});

// --- Initial Population --- //
document.addEventListener('DOMContentLoaded', function() {
    populateProductSelect();
    usedMaterials.forEach(mat => {
        addMaterialToList({
            productId: mat.product_id,
            warehouseId: mat.warehouse_id,
            quantity: mat.quantity_used,
            productName: mat.name,
            warehouseName: mat.warehouse_name
        });
    });
});
</script>

<?php include 'footer.php'; ob_end_flush(); ?>
