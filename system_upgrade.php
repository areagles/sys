// =================================================================
// 🚀 التحديث الشامل لدعم الكسور العشرية (Decimal Precision Upgrade)
// =================================================================
echo "<h3>🔧 جاري تحويل النظام لدعم الكسور والأرقام العشرية...</h3>";

// قائمة الجداول والحقول التي يجب تحويلها لتقبل الكسور
$tables_map = [
    // أوامر الشغل (الكميات والأسعار)
    'job_orders' => ['price', 'paid', 'quantity'], 
    
    // الفواتير والحسابات
    'invoices' => ['sub_total', 'tax', 'discount', 'total_amount', 'paid_amount', 'remaining_amount'],
    
    // عروض الأسعار وبنودها
    'quotes' => ['total_amount'],
    'quote_items' => ['quantity', 'price', 'total'],
    
    // المشتريات
    'purchase_invoices' => ['sub_total', 'tax', 'discount', 'total_amount', 'paid_amount', 'remaining_amount'],
    
    // الموردين والعملاء (الأرصدة)
    'clients' => ['opening_balance', 'current_balance'],
    'suppliers' => ['opening_balance', 'current_balance'],
    
    // الرواتب والعهد
    'payroll_sheets' => ['basic_salary', 'bonus', 'deductions', 'net_salary', 'paid_amount', 'remaining_amount'],
    'financial_receipts' => ['amount']
];

foreach ($tables_map as $table => $columns) {
    // 1. التأكد من وجود الجدول أولاً
    $tbl_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($tbl_check->num_rows > 0) {
        foreach ($columns as $col) {
            // 2. التأكد من وجود العمود داخل الجدول
            $col_check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($col_check->num_rows > 0) {
                // 3. تحويل العمود إلى DECIMAL(10,2) ليقبل الكسور (مثال: 150.50)
                // DECIMAL(10,2) تعني 10 أرقام إجمالاً، منهم 2 بعد العلامة العشرية
                $sql = "ALTER TABLE `$table` MODIFY COLUMN `$col` DECIMAL(10,2) DEFAULT 0.00";
                
                if ($conn->query($sql)) {
                    echo "<div style='color:green;'>✅ تم تحديث جدول <b>$table</b> حقل <b>$col</b> ليقبل الكسور.</div>";
                } else {
                    echo "<div style='color:red;'>❌ فشل تحديث $table ($col): " . $conn->error . "</div>";
                }
            }
        }
    }
}

echo "<h3 style='color:blue;'>🎉 تم الانتهاء! النظام الآن يقبل القروش والكسور في جميع التعاملات.</h3>";