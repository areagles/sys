<?php
// inventory.php - (V2.0 - Multi-Warehouse Dashboard)
ob_start();
require 'auth.php';
require 'config.php';

// --- Fetch all products with their total stock ---
// This query joins products with product_stock and sums the quantities from all warehouses for each product.
$sql = "
    SELECT 
        p.*, 
        COALESCE(SUM(ps.quantity), 0) AS total_quantity
    FROM 
        products p
    LEFT JOIN 
        product_stock ps ON p.id = ps.product_id
    GROUP BY 
        p.id
    ORDER BY 
        p.name ASC;
";

$products = [];
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    // This can happen if tables don't exist yet. Redirect to a setup/error page or show a message.
    // For now, we'll show a friendly message on the page itself.
}

require 'header.php';
?>

<style>
/* --- Styles are the same as V1.0 --- */
:root { --danger-bg: rgba(220, 53, 69, 0.1); --danger-border: #dc3545; }
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
.search-wrapper { position: relative; flex-grow: 1; max-width: 400px; }
#productSearch { width: 100%; padding: 12px 40px 12px 15px; background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-main); font-size: 1rem; }
.search-wrapper i { position: absolute; top: 50%; transform: translateY(-50%); right: 15px; color: #555; }
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.product-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; color: inherit; }
.product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
.product-card.low-stock { background: var(--danger-bg); border-color: var(--danger-border); }
.product-image { width: 100%; height: 180px; background-color: #333; background-size: cover; background-position: center; }
.product-info { padding: 15px; display: flex; flex-direction: column; flex-grow: 1; }
.product-name { font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0 0 5px 0; }
.product-sku { font-size: 0.8rem; color: #888; margin-bottom: 15px; font-family: monospace; }
.product-details { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 15px; border-top: 1px solid var(--border-color); }
.product-quantity { font-size: 1.5rem; font-weight: 800; color: var(--gold); }
.product-card.low-stock .product-quantity { color: var(--danger-border); }
.product-price { font-size: 1.1rem; font-weight: 600; color: #2ecc71; }
.no-products, .db-error { background: var(--card-bg); padding: 40px; text-align: center; border-radius: 12px; border: 2px dashed var(--border-color); grid-column: 1 / -1; }
.no-products h3, .db-error h3 { color: var(--gold); }
</style>

<div class="container" dir="rtl">

    <div class="page-header">
        <h2 class="page-title"><i class="fa-solid fa-boxes-stacked"></i> نظرة عامة على المخزون</h2>
        <div>
            <a href="warehouses.php" class="btn-secondary"><i class="fa-solid fa-warehouse"></i> إدارة المخازن</a>
            <a href="edit_product.php" class="btn-royal"><i class="fa-solid fa-plus"></i> إضافة منتج جديد</a>
        </div>
    </div>

    <div class="search-wrapper">
        <input type="text" id="productSearch" placeholder="ابحث بالاسم أو الكود (SKU)...">
        <i class="fa-solid fa-search"></i>
    </div>
    
    <hr style="border-color: var(--border-color); margin: 25px 0;">
    
    <?php if (!$result): ?>
        <div class="db-error">
            <h3><i class="fa-solid fa-database"></i> خطأ في قاعدة البيانات</h3>
            <p>يبدو أن جداول المخزون غير موجودة. هل قمت بتشغيل ملف الإعداد؟</p>
            <a href="db_setup_inventory.php" class="btn-royal" style="margin-top: 15px;">تشغيل إعداد المخزون الآن</a>
        </div>
    <?php else: ?>
        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
                <div class="no-products">
                    <h3>لم يتم إضافة أي منتجات بعد</h3>
                    <p>ابدأ بإضافة منتجك الأول لتتبع الكميات والأسعار.</p>
                    <a href="edit_product.php" class="btn-royal" style="margin-top: 15px;">+ إضافة منتج جديد</a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): 
                    $is_low = $p['total_quantity'] <= $p['low_stock_threshold'];
                    $default_img = 'assets/img/icon-512x512.png';
                    $img_path = (!empty($p['image_path']) && file_exists($p['image_path'])) ? htmlspecialchars($p['image_path']) : $default_img;
                ?>
                    <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="product-card <?php if ($is_low) echo 'low-stock'; ?>" data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>" data-sku="<?php echo htmlspecialchars(strtolower($p['sku'] ?? '')); ?>">
                        <div class="product-image" style="background-image: url('<?php echo $img_path; ?>?t=<?php echo time(); ?>')"></div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($p['name']); ?></h3>
                            <p class="product-sku">SKU: <?php echo htmlspecialchars($p['sku'] ?? 'N/A'); ?></p>
                            <div class="product-details">
                                <span class="product-quantity"><?php echo floatval($p['total_quantity']); ?></span>
                                <span class="product-price"><?php echo number_format($p['sale_price'], 2); ?> ج.م</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div id="no-results" style="display:none; grid-column: 1 / -1; text-align:center; padding: 40px; color: #888;">
                <h3><i class="fa-solid fa-search"></i> لا توجد نتائج مطابقة لبحثك</h3>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Search script
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearch');
    if (!searchInput) return;

    searchInput.addEventListener('keyup', function() {
        let searchTerm = this.value.toLowerCase();
        let cards = document.querySelectorAll('.product-card');
        let noResults = document.getElementById('no-results');
        let matchFound = false;

        cards.forEach(card => {
            let name = card.dataset.name;
            let sku = card.dataset.sku;
            if (name.includes(searchTerm) || sku.includes(searchTerm)) {
                card.style.display = 'flex';
                matchFound = true;
            } else {
                card.style.display = 'none';
            }
        });

        const grid = document.getElementById('productsGrid');
        const visibleCards = grid.querySelectorAll('.product-card[style*="display: flex"]').length;
        const totalCards = grid.querySelectorAll('.product-card').length;

        if (totalCards > 0 && visibleCards === 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    });
});
</script>

<?php 
include 'footer.php'; 
ob_end_flush(); 
?>
