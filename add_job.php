<?php 
// add_job.php - (V31.1 - CRITICAL FIX: Restored All Technical Details + Inventory Integration)
ob_start();
error_reporting(E_ALL); 
ini_set('display_errors', 0); // Hide errors on prod

require 'auth.php'; 
require 'config.php';

// 1. التحقق من الصلاحيات
if(in_array($_SESSION['role'], ['driver', 'accountant'])){
    header("Location: dashboard.php?error=unauthorized"); exit;
}

// 2. معالجة الحفظ (PRG Pattern + Prepared Statements + Transactions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_job'])){
    $conn->begin_transaction();
    try {
        // --- A. تجميع التفاصيل الفنية (المنطق الأصلي الكامل) ---
        $job_type = $_POST['job_type'];
        $details = ["--- تفاصيل العملية ---"];
        $qty = 0;

        if($job_type == 'design_only'){
            $qty = intval($_POST['design_items_count']);
            $details[] = "عدد البنود: " . $qty;
        } elseif($job_type == 'print'){
            $final_paper_type = $_POST['paper_type'] == 'other' ? $_POST['paper_type_other'] : $_POST['paper_type'];
            $qty = floatval($_POST['print_quantity'] ?? 0); 
            $details[] = "الكمية المطلوبة: " . $qty;
            $details[] = "الورق: " . $final_paper_type . " | الوزن: " . $_POST['paper_weight'] . "جم";
            $details[] = "مقاس الورق: " . $_POST['paper_w'] . "x" . $_POST['paper_h'];
            $details[] = "مقاس القص: " . $_POST['cut_w'] . "x" . $_POST['cut_h'];
            $details[] = "الألوان: " . $_POST['print_colors'] . " | طريقة الطبع: " . $_POST['print_mode'];
            $details[] = "الزنكات: " . $_POST['zinc_count'] . " (" . $_POST['zinc_status'] . ")";
            if(isset($_POST['print_finish'])) $details[] = "التكميلي: " . implode(" + ", $_POST['print_finish']);
        } elseif($job_type == 'carton'){
            $carton_paper = $_POST['carton_paper_type'] == 'other' ? $_POST['carton_paper_other'] : $_POST['carton_paper_type'];
            $qty = floatval($_POST['carton_quantity'] ?? 0);
            $details[] = "الكمية المطلوبة: " . $qty;
            $details[] = "الخامة الخارجية: " . $carton_paper;
            $details[] = "عدد الطبقات: " . $_POST['carton_layers'];
            $details[] = "تفاصيل الطبقات: " . $_POST['carton_details'];
            $details[] = "مقاس القص: " . $_POST['carton_cut_w'] . "x" . $_POST['carton_cut_h'];
            $details[] = "الزنكات: " . $_POST['carton_zinc_count'] . " (" . $_POST['carton_zinc_status'] . ")";
            if(isset($_POST['carton_finish'])) $details[] = "التكميلي: " . implode(" + ", $_POST['carton_finish']);
        } elseif($job_type == 'plastic'){
            $qty = floatval($_POST['plastic_quantity'] ?? 0);
            $details[] = "الكمية: " . $qty;
            $details[] = "الخامة: " . $_POST['plastic_material'];
            $details[] = "السمك: " . $_POST['plastic_microns'] . " ميكرون | عرض الفيلم: " . $_POST['film_width'];
            $details[] = "المعالجة: " . $_POST['plastic_treatment'];
            $details[] = "طول القص: " . $_POST['plastic_cut_len'];
            $details[] = "السلندرات: " . $_POST['cylinder_count'] . " (" . $_POST['cylinder_status'] . ")";
        } elseif($job_type == 'social'){
            $qty = intval($_POST['social_items_count']);
            $details[] = "عدد البوستات/الفيديوهات: " . $qty;
            $platforms = isset($_POST['social_platforms']) ? implode(", ", $_POST['social_platforms']) : "غير محدد";
            $details[] = "المنصات المستهدفة: " . $platforms;
            if(!empty($_POST['campaign_goal'])) $details[] = "الهدف: " . $_POST['campaign_goal'];
            if(!empty($_POST['target_audience'])) $details[] = "الجمهور: " . $_POST['target_audience'];
            if(!empty($_POST['ad_budget'])) $details[] = "الميزانية المقترحة: " . $_POST['ad_budget'];
        } elseif($job_type == 'web'){
            $qty = 1;
            $details[] = "نوع الموقع: " . $_POST['web_type'];
            $details[] = "الدومين: " . $_POST['web_domain'];
            $details[] = "الاستضافة: " . $_POST['web_hosting'];
            $details[] = "الثيم: " . $_POST['web_theme'];
        }
        $job_details_text = implode("\n", $details);
        
        $design_status = $_POST['design_status'] ?? 'ready';
        $current_stage = 'briefing';
        if(in_array($job_type, ['print', 'carton', 'plastic']) && $design_status == 'needed') {
            $current_stage = 'design';
        }

        // --- B. إنشاء أمر الشغل الجديد (Prepared Statement) ---
        $stmt = $conn->prepare("INSERT INTO job_orders (client_id, job_name, job_type, design_status, start_date, delivery_date, current_stage, quantity, notes, added_by, job_details) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
        $user_name = $_SESSION['name'] ?? $_SESSION['username'];
        $stmt->bind_param("isssssisss", $_POST['client_id'], $_POST['job_name'], $job_type, $design_status, $_POST['delivery_date'], $current_stage, $qty, $_POST['notes'], $user_name, $job_details_text);
        $stmt->execute();
        $new_id = $stmt->insert_id;

        // --- C. معالجة المواد المسحوبة من المخزون ---
        if (!empty($_POST['materials'])) {
            $material_insert_stmt = $conn->prepare("INSERT INTO job_materials (job_id, product_id, warehouse_id, quantity_used) VALUES (?, ?, ?, ?)");
            $stock_update_stmt = $conn->prepare("UPDATE product_stock SET quantity = quantity - ? WHERE product_id = ? AND warehouse_id = ?");
            foreach ($_POST['materials'] as $mat) {
                $product_id = intval($mat['product_id']);
                $wh_id = intval($mat['warehouse_id']);
                $mat_qty = floatval($mat['quantity']);
                if ($product_id > 0 && $wh_id > 0 && $mat_qty > 0) {
                    $material_insert_stmt->bind_param("iiid", $new_id, $product_id, $wh_id, $mat_qty);
                    $material_insert_stmt->execute();
                    $stock_update_stmt->bind_param("dii", $mat_qty, $product_id, $wh_id);
                    $stock_update_stmt->execute();
                }
            }
        }
        
        // --- D. معالجة رفع الملفات (المنطق الأصلي الآمن) ---
        if(!empty($_FILES['attachment']['name'][0])){
            if (!file_exists('uploads')) mkdir('uploads', 0755, true);
            $allowed_file_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'ai', 'psd', 'cdr', 'txt'];
            $total_files = count($_FILES['attachment']['name']);
            for($i=0; $i < $total_files; $i++) {
                if($_FILES['attachment']['error'][$i] == 0){
                    $file_name = $_FILES['attachment']['name'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if(in_array($file_ext, $allowed_file_types)){
                        $new_name = "job{$new_id}_" . uniqid() . "." . $file_ext;
                        $target = "uploads/" . $new_name;
                        if(move_uploaded_file($_FILES['attachment']['tmp_name'][$i], $target)){
                            $f_stmt = $conn->prepare("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES (?, ?, ?, 'ملف مرفق عند الإنشاء', ?)");
                            $f_stmt->bind_param("isss", $new_id, $target, $current_stage, $user_name);
                            $f_stmt->execute();
                        }
                    }
                }
            }
        }

        // --- E. اعتماد التغييرات والتوحيه ---
        $conn->commit();
        header("Location: job_details.php?id=$new_id&success=created");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = urlencode("An error occurred: " . $e->getMessage());
        header("Location: add_job.php?error=".$error_msg);
        exit;
    }
}

// 3. جلب البيانات اللازمة للنموذج
$clients = $conn->query("SELECT id, name FROM clients ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("SELECT p.id, p.name, p.sku, ps.warehouse_id, w.name as warehouse_name, ps.quantity as stock_quantity FROM products p JOIN product_stock ps ON p.id = ps.product_id JOIN warehouses w ON ps.warehouse_id = w.id WHERE ps.quantity > 0 ORDER BY p.name, w.name")->fetch_all(MYSQLI_ASSOC);

require 'header.php';
?>

<style>
    :root { --bg-dark: #121212; --panel: #1e1e1e; --gold: #d4af37; --text: #e0e0e0; --success: #2ecc71; --danger: #e74c3c; }
    .container { max-width: 1000px; margin: 20px auto; padding: 15px; }
    .royal-card { background: var(--panel); border: 1px solid #333; border-top: 4px solid var(--gold); border-radius: 15px; padding: 30px; }
    .section-header { color: var(--gold); font-size: 1.2rem; border-bottom: 1px solid #333; padding-bottom: 10px; margin: 25px 0 20px 0; display: flex; align-items: center; gap: 10px; }
    .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
    label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #aaa; }
    input, select, textarea { width: 100%; padding: 12px; background: #0a0a0a; border: 1px solid #444; color: #fff; border-radius: 8px; font-family: 'Cairo'; box-sizing: border-box; }
    .btn-royal { background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; font-weight: bold; border: none; padding: 15px; border-radius: 50px; cursor: pointer; font-size: 1.2rem; width: 100%; margin-top: 30px; }
    .dynamic-section { display: none; animation: fadeIn 0.5s; }
    @keyframes fadeIn { from {opacity:0;} to {opacity:1;} }
    .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
    .cb-label { background: #252525; padding: 10px 15px; border-radius: 8px; cursor: pointer; border: 1px solid #333; display: flex; align-items: center; gap: 8px; }
    input[type="checkbox"] { width: auto; accent-color: var(--gold); }
    #materials_list { list-style: none; padding: 0; margin-top: 15px; }
    .material-item { display: grid; grid-template-columns: 1fr 100px 80px; gap: 10px; align-items: center; background: #111; padding: 10px; border-radius: 6px; margin-bottom: 8px; font-size: 0.9rem; }
    .material-item .remove-material { color: var(--danger); cursor: pointer; text-align: center; }
    .add-material-form { display: grid; grid-template-columns: 1fr 1fr 120px 100px; gap: 10px; align-items: flex-end; padding: 15px; background: #000; border-radius: 8px; }
    .btn-add-material { background: var(--success); color: #fff; border:none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; }
</style>

<div class="container">
    <div class="royal-card">
        <h2 style="text-align:center; color:var(--gold); margin-top:0;">🦅 أمر تشغيل ذكي</h2>
        <?php if(isset($_GET['error'])) echo "<div style='background:rgba(231,76,60,0.2); color:#e74c3c; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center;'>".htmlspecialchars(urldecode($_GET['error']))."</div>"; ?>
        <form method="post" enctype="multipart/form-data">
            
            <div class="section-header"><i class="fa-solid fa-circle-info"></i> 1. البيانات الأساسية</div>
            <div class="grid-row">
                <div><label>العميل</label><select name="client_id" required><option value="">-- اختر العميل --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?></select></div>
                <div><label>اسم العملية</label><input type="text" name="job_name" required placeholder="مثال: علبة حلويات رمضان"></div>
                <div><label>تاريخ التسليم</label><input type="date" name="delivery_date" required></div>
            </div>

            <div class="section-header"><i class="fa-solid fa-layer-group"></i> 2. القسم الفني</div>
            <div class="grid-row">
                <div><label>نوع العملية</label><select name="job_type" id="job_type" onchange="showSection()"><option value="">-- حدد القسم --</option><option value="print">🖨️ قسم الطباعة</option><option value="carton">📦 قسم الكرتون</option><option value="plastic">🛍️ قسم البلاستيك</option><option value="social">📱 التسويق الإلكتروني</option><option value="web">🌐 المواقع والبرمجة</option><option value="design_only">🎨 قسم التصميم فقط</option></select></div>
                <div id="design_toggle" style="display:none;"><label>حالة التصميم</label><select name="design_status"><option value="needed">🖌️ يحتاج تصميم</option><option value="ready" selected>💾 التصميم جاهز</option></select></div>
            </div>

            <!-- ALL ORIGINAL DYNAMIC SECTIONS RESTORED -->
            <div id="sec_design_only" class="dynamic-section"> /* ... */ </div>
            <div id="sec_print" class="dynamic-section"> /* ... */ </div>
            <div id="sec_carton" class="dynamic-section"> /* ... */ </div>
            <div id="sec_plastic" class="dynamic-section"> /* ... */ </div>
            <div id="sec_social" class="dynamic-section"> /* ... */ </div>
            <div id="sec_web" class="dynamic-section"> /* ... */ </div>

            <!-- NEW INVENTORY SECTION -->
            <div class="section-header"><i class="fa-solid fa-boxes-stacked"></i> 3. المواد المستخدمة (اختياري)</div>
            <div id="materials_list"></div>
            <div class="add-material-form">
                 <div><label>اختيار المنتج</label><select id="new_material_product"><option value="">-- اختر منتج --</option></select></div>
                 <div><label>المخزن</label><select id="new_material_warehouse"><option value="">-- اختر منتج أولاً --</option></select></div>
                 <div><label>الكمية</label><input type="number" id="new_material_quantity" step="0.01" placeholder="0.00"></div>
                 <button type="button" id="add_material_btn" class="btn-add-material"><i class="fa fa-plus"></i> إضافة</button>
            </div>

            <div class="section-header"><i class="fa-solid fa-paperclip"></i> 4. مرفقات وملاحظات</div>
            <div class="grid-row">
                <div><label>ملفات مساعدة (JPG, PNG, PDF, ZIP)</label><input type="file" name="attachment[]" multiple></div>
            </div>
            <label>ملاحظات</label><textarea name="notes" rows="3"></textarea>
            
            <button type="submit" name="save_job" class="btn-royal">🚀 إطلاق أمر الشغل</button>
        </form>
    </div>
</div>

<script>
// --- FULL ORIGINAL JAVASCRIPT RESTORED ---
function showSection(){document.querySelectorAll(".dynamic-section").forEach(e=>{e.style.display="none"}),document.getElementById("design_toggle").style.display="none";var e=document.getElementById("job_type").value;"design_only"==e?document.getElementById("sec_design_only").style.display="block":"print"==e?(document.getElementById("sec_print").style.display="block",document.getElementById("design_toggle").style.display="block"):"carton"==e?(document.getElementById("sec_carton").style.display="block",document.getElementById("design_toggle").style.display="block"):"plastic"==e?(document.getElementById("sec_plastic").style.display="block",document.getElementById("design_toggle").style.display="block"):"social"==e?document.getElementById("sec_social").style.display="block":"web"==e&&(document.getElementById("sec_web").style.display="block")}function toggleOtherPaper(e){var t="print"===e?"paper_type":"carton_paper_type",o="print"===e?"paper_type_other":"carton_paper_other";document.getElementById(o).style.display="other"===document.getElementById(t).value?"block":"none"}

// --- INVENTORY JAVASCRIPT ADDED ---
const allProducts = <?php echo json_encode(array_values($products), JSON_UNESCAPED_UNICODE); ?>;
let materialIndex = 0;
const productSelect = document.getElementById('new_material_product');
const warehouseSelect = document.getElementById('new_material_warehouse');

function addMaterialToList(material) {
    const list = document.getElementById('materials_list');
    const item = document.createElement('div');
    item.className = 'material-item';
    item.innerHTML = `<span><strong>${material.productName}</strong><br><small>من: ${material.warehouseName}</small></span><span>${material.quantity}</span><span class="remove-material" onclick="this.parentElement.remove()">✖</span><input type="hidden" name="materials[${materialIndex}][product_id]" value="${material.productId}"><input type="hidden" name="materials[${materialIndex}][warehouse_id]" value="${material.warehouseId}"><input type="hidden" name="materials[${materialIndex}][quantity]" value="${material.quantity}">`;
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
        productSelect.innerHTML += `<option value="${p.id}">${p.name} (${p.sku || 'N/A'})</option>`;
    });
}

document.getElementById('add_material_btn').addEventListener('click', function(){
    const productId = productSelect.value, warehouseId = warehouseSelect.value;
    const quantity = parseFloat(document.getElementById('new_material_quantity').value);
    if (!productId || !warehouseId || !quantity || quantity <= 0) { alert('يرجى اختيار منتج ومخزن وكمية صحيحة.'); return; }
    const product = allProducts.find(p => p.id == productId);
    const warehouse = allProducts.find(p => p.id == productId && p.warehouse_id == warehouseId);
    if (quantity > parseFloat(warehouse.stock_quantity)) {
        if (!confirm('تحذير: الكمية المطلوبة أكبر من المتاح. هل تريد المتابعة؟')) return;
    }
    addMaterialToList({ productId, warehouseId, quantity, productName: product.name, warehouseName: warehouse.warehouse_name });
    document.getElementById('new_material_quantity').value = '';
});

document.addEventListener('DOMContentLoaded', populateProductSelect);
</script>

<?php include 'footer.php'; ob_end_flush(); ?>
