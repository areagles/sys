<?php
// warehouses.php - (Inventory Management V2.1 - Expert Review Update)
ob_start();
ini_set('display_errors', 0); // Hide errors from end-user in production
error_reporting(E_ALL);

require 'auth.php';
require 'config.php';

$my_role = $_SESSION['role'] ?? 'guest';
if (!in_array($my_role, ['admin', 'manager'])) {
    // We will redirect to dashboard instead of showing a plain text error
    header('Location: dashboard.php?error=unauthorized');
    exit();
}

$error = '';
$success = '';

// Map success codes from URL to messages
if (isset($_GET['success'])) {
    $success_codes = [
        '1' => 'تمت إضافة المخزن بنجاح.',
        '2' => 'تم تحديث بيانات المخزن بنجاح.',
        '3' => 'تم حذف المخزن بنجاح.'
    ];
    $success = $success_codes[$_GET['success']] ?? '';
}
if (isset($_GET['error'])) {
    $error_codes = [
        '1' => 'لا يمكن حذف المخزن الوحيد المتبقي.',
        '2' => 'لا يمكن حذف المخزن لوجود كميات من المنتجات به. يرجى نقل المخزون أولاً.',
        '3' => 'اسم المخزن مطلوب.'
    ];
    $error = $error_codes[$_GET['error']] ?? 'حدث خطأ غير معروف.';
}

// --- Handle form submissions (Add/Edit) using PRG Pattern ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (empty($name)) {
        header('Location: warehouses.php?error=3'); exit();
    }

    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO warehouses (name, location, is_active) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $location, $is_active);
        if ($stmt->execute()) {
            header('Location: warehouses.php?success=1'); exit();
        }
    } else { // edit
        $stmt = $conn->prepare("UPDATE warehouses SET name=?, location=?, is_active=? WHERE id=?");
        $stmt->bind_param("ssii", $name, $location, $is_active, $id);
        if ($stmt->execute()) {
            header('Location: warehouses.php?success=2'); exit();
        }
    }
    // If execution fails, show error
    $error = 'DB Error: ' . $stmt->error;
    $stmt->close();
}

// --- Handle deletion ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);
    $count_wh = $conn->query("SELECT COUNT(*) as count FROM warehouses")->fetch_assoc()['count'];
    if ($count_wh <= 1) {
        header('Location: warehouses.php?error=1'); exit();
    }
    
    $stock_check = $conn->prepare("SELECT id FROM product_stock WHERE warehouse_id = ? AND quantity > 0 LIMIT 1");
    $stock_check->bind_param("i", $id_to_delete);
    $stock_check->execute();
    if ($stock_check->get_result()->num_rows > 0) {
        header('Location: warehouses.php?error=2'); exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM warehouses WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        header('Location: warehouses.php?success=3'); exit();
    }
    $error = 'DB Error: ' . $stmt->error;
}

$warehouses = $conn->query("SELECT * FROM warehouses ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

require 'header.php';
?>

<style>
    /* --- Styles refactored from inline to CSS block --- */
    .page-title-gold { color: var(--gold-primary); margin-bottom: 30px; }
    .page-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
    .form-panel, .list-panel { background: var(--panel-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); }
    .list-panel { padding: 0; overflow: hidden; }
    .panel-title { color: var(--gold-primary); font-size: 1.5rem; font-weight: 700; margin-bottom: 25px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; color: #ccc; margin-bottom: 10px; font-weight: 600; }
    .form-control { width: 100%; background: var(--bg-dark); border: 1px solid var(--border-color); color: var(--text-main); padding: 12px 15px; border-radius: 8px; }
    .form-control:focus { outline: none; border-color: var(--gold-primary); }
    .form-check { display: flex; align-items: center; gap: 10px; }
    .btn-submit { width: 100%; background: var(--gold-gradient); color: #000; padding: 12px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 15px; }
    .btn-cancel { background: #555 !important; display: none; }
    .list-header { padding: 20px 30px; background: #111; border-bottom: 1px solid var(--border-color); }
    .list-header .panel-title { margin: 0; font-size: 1.2rem; }
    .wh-list { list-style: none; padding: 0; margin: 0; }
    .wh-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; border-bottom: 1px solid var(--border-color); }
    .wh-item:last-child { border-bottom: none; }
    .wh-item:hover { background: #1f1f1f; }
    .wh-name { font-weight: bold; color: #fff; }
    .wh-location { font-size: 0.9rem; color: #888; }
    .wh-actions a { color: #ccc; margin: 0 5px; text-decoration: none; }
    .wh-actions a:hover { color: var(--gold-primary); }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; border: 1px solid transparent; }
    .alert-danger { background-color: rgba(231, 76, 60, 0.1); color: #e74c3c; border-color: #e74c3c; }
    .alert-success { background-color: rgba(46, 204, 113, 0.1); color: #2ecc71; border-color: #2ecc71; }
    @media (max-width: 992px) { .page-grid { grid-template-columns: 1fr; } }
</style>

<div class="container">
    <h1 class="page-title-gold"><i class="fa-solid fa-warehouse"></i> إدارة المخازن</h1>
    
    <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>

    <div class="page-grid">
        <div class="list-panel">
            <div class="list-header">
                <h2 class="panel-title">المخازن الحالية</h2>
            </div>
            <ul class="wh-list">
                <?php if (empty($warehouses)): ?>
                    <li style="text-align:center; padding: 40px; color: #888;">لم يتم إضافة أي مخازن بعد.</li>
                <?php else: ?>
                    <?php foreach ($warehouses as $wh): ?>
                    <li class="wh-item" data-id="<?php echo $wh['id']; ?>" data-name="<?php echo htmlspecialchars($wh['name']); ?>" data-location="<?php echo htmlspecialchars($wh['location']); ?>" data-active="<?php echo $wh['is_active']; ?>">
                        <div>
                            <span class="wh-name"><?php echo htmlspecialchars($wh['name']); ?> <?php echo $wh['is_active'] ? '' : '<span style="color:#e74c3c; font-size:0.8em;">(غير نشط)</span>'; ?></span>
                            <p class="wh-location"><?php echo htmlspecialchars($wh['location'] ? $wh['location'] : 'لا يوجد موقع محدد'); ?></p>
                        </div>
                        <div class="wh-actions">
                            <a href="#" class="edit-btn"><i class="fa-solid fa-pen-to-square"></i> تعديل</a>
                            <a href="warehouses.php?action=delete&id=<?php echo $wh['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا المخزن؟ لا يمكن التراجع عن هذا الإجراء.');"><i class="fa-solid fa-trash"></i> حذف</a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="form-panel">
            <form method="POST" id="warehouseForm">
                <h2 class="panel-title" id="formTitle">إضافة مخزن جديد</h2>
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="0">

                <div class="form-group">
                    <label for="name">اسم المخزن</label>
                    <input type="text" name="name" id="formName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="location">الموقع (اختياري)</label>
                    <input type="text" name="location" id="formLocation" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-check"><input type="checkbox" name="is_active" id="formActive" value="1" checked><span>نشط</span></label>
                </div>

                <button type="submit" class="btn-submit" id="submitButton"><i class="fa-solid fa-plus"></i> إضافة المخزن</button>
                <button type="button" class="btn-submit btn-cancel" id="cancelButton" onclick="resetForm()"><i class="fa-solid fa-times"></i> إلغاء التعديل</button>
            </form>
        </div>
    </div>
</div>

<script>
    // This script handles the client-side interaction for editing warehouses without a page reload.
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('warehouseForm');
        const formTitle = document.getElementById('formTitle');
        const formAction = document.getElementById('formAction');
        const formId = document.getElementById('formId');
        const formName = document.getElementById('formName');
        const formLocation = document.getElementById('formLocation');
        const formActive = document.getElementById('formActive');
        const submitButton = document.getElementById('submitButton');
        const cancelButton = document.getElementById('cancelButton');

        // Attach event listeners to all 'edit' buttons
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const item = this.closest('.wh-item');
                
                // Populate the form with data from the selected item
                formTitle.innerText = 'تعديل المخزن';
                formAction.value = 'edit';
                formId.value = item.dataset.id;
                formName.value = item.dataset.name;
                formLocation.value = item.dataset.location;
                formActive.checked = (item.dataset.active == '1');
                submitButton.innerHTML = '<i class="fa-solid fa-save"></i> حفظ التعديلات';
                cancelButton.style.display = 'block';
                
                // Scroll to the form for better UX on long pages
                window.scrollTo({ top: form.offsetTop - 100, behavior: 'smooth' });
                formName.focus();
            });
        });
    });

    // Resets the form to its initial 'Add New' state
    function resetForm() {
        document.getElementById('warehouseForm').reset();
        document.getElementById('formTitle').innerText = 'إضافة مخزن جديد';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '0';
        document.getElementById('submitButton').innerHTML = '<i class="fa-solid fa-plus"></i> إضافة المخزن';
        document.getElementById('cancelButton').style.display = 'none';
    }
</script>

<?php include 'footer.php'; ob_end_flush(); ?>
