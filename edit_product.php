<?php
// edit_product.php - (Inventory Management V2.0)
ob_start();
require 'auth.php';
require 'config.php';

$my_role = $_SESSION['role'] ?? 'guest';
if (!in_array($my_role, ['admin', 'manager', 'purchasing'])) {
    die("Access Denied. You do not have permission to manage products.");
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = $product_id > 0;
$product = [];
$page_title = $is_edit ? "تعديل بيانات المنتج" : "إضافة منتج جديد للمخزون";
$warehouses = $conn->query("SELECT * FROM warehouses  WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// --- Fetch existing product data if in edit mode ---
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        die("Product not found.");
    }
    $stmt->close();
}

// --- Handle form submission (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Extract and sanitize data
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $sale_price = floatval($_POST['sale_price']);
    $purchase_price = floatval($_POST['purchase_price']);
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    $image_path = $product['image_path'] ?? ''; // Keep old image by default

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/products/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $safe_filename = ($sku ? preg_replace('/[^a-zA-Z0-9_-]/', '-', $sku) : uniqid()) . '.' . $file_extension;
        $target_file = $target_dir . $safe_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        } else {
            $error = "Sorry, there was an error uploading your file.";
        }
    }

    if (!isset($error)) {
        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE products SET name=?, sku=?, description=?, category=?, sale_price=?, purchase_price=?, low_stock_threshold=?, image_path=? WHERE id=?");
            $stmt->bind_param("ssssddisi", $name, $sku, $description, $category, $sale_price, $purchase_price, $low_stock_threshold, $image_path, $product_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, sku, description, category, sale_price, purchase_price, low_stock_threshold, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssddis", $name, $sku, $description, $category, $sale_price, $purchase_price, $low_stock_threshold, $image_path);
        }

        if ($stmt->execute()) {
            $new_product_id = $is_edit ? $product_id : $stmt->insert_id;
            
            // Handle initial stock for new products
            if (!$is_edit && isset($_POST['initial_stock']) && floatval($_POST['initial_stock']) > 0) {
                $initial_stock = floatval($_POST['initial_stock']);
                $warehouse_id = intval($_POST['warehouse_id']);
                
                $stock_stmt = $conn->prepare("INSERT INTO product_stock (product_id, warehouse_id, quantity) VALUES (?, ?, ?)");
                $stock_stmt->bind_param("iid", $new_product_id, $warehouse_id, $initial_stock);
                $stock_stmt->execute();
                $stock_stmt->close();

                $move_stmt = $conn->prepare("INSERT INTO stock_movements (product_id, warehouse_id, quantity_change, type, notes, user_id) VALUES (?, ?, ?, 'initial', 'Initial stock entry', ?)");
                $move_stmt->bind_param("iidi", $new_product_id, $warehouse_id, $initial_stock, $_SESSION['user_id']);
                $move_stmt->execute();
                $move_stmt->close();
            }

            header("Location: inventory.php");
            exit();
        } else {
            $error = "Error executing query: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle product deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && $is_edit) {
    // First, delete the image file if it exists
    if (!empty($product['image_path']) && file_exists($product['image_path'])) {
        unlink($product['image_path']);
    }
    // Then, delete the product from DB (stock and movements are cascaded)
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    header("Location: inventory.php");
    exit();
}

require 'header.php';
?>

<style>
    /* --- Form Styles Inspired by header.php --- */
    .form-container { 
        background: var(--panel-bg); 
        padding: 30px 40px;
        border-radius: 12px; 
        border: 1px solid var(--border-color);
    }
    .form-header { text-align: center; margin-bottom: 30px; }
    .form-title { color: var(--gold-primary); font-size: 1.8rem; font-weight: 700; margin: 0; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
    .form-group { margin-bottom: 0; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group label {
        display: block; color: #ccc; margin-bottom: 10px; font-weight: 600; font-size: 0.9rem;
    }
    .form-control {
        width: 100%;
        background: var(--bg-dark);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 12px 15px;
        border-radius: 8px;
        font-family: 'Cairo', sans-serif;
        font-size: 1rem;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    .form-control:focus { 
        outline: none; border-color: var(--gold-primary); 
        box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
    }
    textarea.form-control { resize: vertical; min-height: 100px; }

    .btn-submit { 
        width: 100%; background: var(--gold-gradient); color: #000; padding: 15px; border: none; 
        border-radius: 8px; font-size: 1.1rem; font-weight: 700; cursor: pointer; 
        transition: 0.3s; margin-top: 20px;
    }
    .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4); }

    .delete-link { color: #e74c3c; text-decoration: none; display: inline-block; margin-top: 20px; font-weight: bold; }
    
    /* Image Upload Preview */
    .image-upload-wrapper { display: flex; align-items: center; gap: 20px; background: var(--bg-dark); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); }
    #imagePreview { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #000; }
    .image-upload-info { flex-grow: 1; }
    .image-upload-info label { cursor: pointer; color: var(--gold-primary); font-weight: bold; }
    #image { display: none; }

    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h1 class="form-title"><?php echo $page_title; ?></h1>
        </div>

        <?php if (isset($error)): ?>
            <p style="color: #e74c3c; background: rgba(231, 76, 60, 0.1); padding: 10px; border-radius: 8px; margin-bottom: 20px;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>صورة المنتج</label>
                    <div class="image-upload-wrapper">
                        <img id="imagePreview" src="<?php echo !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'assets/img/placeholder.png'; ?>" alt="Product Image Preview">
                        <div class="image-upload-info">
                            <label for="image">اختر صورة...</label>
                            <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(event)">
                            <p style="font-size: 0.8rem; color: #888; margin: 5px 0 0 0;">اختر صورة واضحة للمنتج. الأبعاد المربعة تبدو أفضل.</p>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="name">اسم المنتج</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="sku">الرمز (SKU)</label>
                    <input type="text" id="sku" name="sku" class="form-control" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" placeholder="مثال: PAPER-A4-WHITE">
                </div>

                <div class="form-group full-width">
                    <label for="description">الوصف</label>
                    <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="sale_price">سعر البيع (ج.م)</label>
                    <input type="number" step="0.01" id="sale_price" name="sale_price" class="form-control" value="<?php echo floatval($product['sale_price'] ?? 0); ?>" required>
                </div>

                <div class="form-group">
                    <label for="purchase_price">سعر الشراء (ج.م)</label>
                    <input type="number" step="0.01" id="purchase_price" name="purchase_price" class="form-control" value="<?php echo floatval($product['purchase_price'] ?? 0); ?>">
                </div>

                <div class="form-group">
                    <label for="category">الفئة</label>
                    <input type="text" id="category" name="category" class="form-control" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>" placeholder="مثال: مواد خام, منتج نهائي">
                </div>

                <div class="form-group">
                    <label for="low_stock_threshold">حد التنبيه للكمية المنخفضة</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control" value="<?php echo intval($product['low_stock_threshold'] ?? 10); ?>" required>
                </div>
                
                <?php if (!$is_edit): ?>
                <div class="form-group">
                    <label for="initial_stock">الكمية المبدئية</label>
                    <input type="number" step="0.01" id="initial_stock" name="initial_stock" class="form-control" placeholder="أدخل الكمية المتوفرة حالياً">
                </div>

                <div class="form-group">
                    <label for="warehouse_id">تخزين في</label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control">
                        <?php foreach($warehouses as $wh): ?>
                            <option value="<?php echo $wh['id']; ?>"><?php echo htmlspecialchars($wh['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group full-width">
                    <button type="submit" class="btn-submit">💾 <?php echo $is_edit ? 'حفظ التعديلات' : 'إضافة المنتج'; ?></button>
                </div>
            </div>
        </form>

        <?php if ($is_edit): ?>
            <div style="text-align: center; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <a href="edit_product.php?id=<?php echo $product_id; ?>&action=delete" class="delete-link" onclick="return confirm('تحذير!\nهل أنت متأكد تماماً من حذف هذا المنتج؟\nسيتم حذف كل سجلات المخزون المتعلقة به. هذا الإجراء لا يمكن التراجع عنه.');">
                    <i class="fa-solid fa-trash"></i> حذف المنتج نهائياً
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const preview = document.getElementById('imagePreview');
        preview.src = reader.result;
    }
    reader.readAsDataURL(event.target.files[0]);
}
</script>

<?php include 'footer.php'; ob_end_flush(); ?>
