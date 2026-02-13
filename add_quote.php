<?php
// add_quote.php - النسخة الملكية النهائية (تصحيح التنسيق الإجباري)
ob_start();
require 'auth.php'; 
require 'config.php'; 
require 'header.php';

// 1. التأكد من وجود الجدول
$conn->query("CREATE TABLE IF NOT EXISTS `quote_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. مصفوفة الشروط الذكية
$default_terms = [
    'general' => [
        'title' => '📜 شروط عامة (أساسية)',
        'items' => [
            'validity' => 'هذا العرض سارٍ لمدة {DAYS} أيام من تاريخه نظراً لتقلبات السوق.',
            'payment' => 'شروط الدفع: 50% دفعة مقدمة عند التعميد، و 50% عند الاستلام.',
            'tax' => 'الأسعار الموضحة لا تشمل ضريبة القيمة المضافة (14%) ما لم يذكر خلاف ذلك.'
        ]
    ],
    'print' => [
        'title' => '🖨️ فنيات الطباعة والتصنيع',
        'items' => [
            'colors' => 'يسمح بتفاوت طفيف في درجات الألوان بنسبة (5%-10%) لاختلاف شاشات العرض عن الطباعة.',
            'tolerance' => 'في المطبوعات والمصنعات، تخضع الكمية لنسبة زيادة أو نقص (±10%) ويحاسب العميل على الفعلي.',
            'storage' => 'تحتسب رسوم أرضيات في حال تأخر العميل عن استلام البضاعة لأكثر من 15 يوم.'
        ]
    ],
    'digital' => [
        'title' => '💻 الحلول الرقمية والبرمجة',
        'items' => [
            'content' => 'العميل مسؤول عن توفير المحتوى (نصوص، صور، شعار) بجودة عالية.',
            'rights' => 'حقوق الملكية الفكرية تؤول للعميل بعد سداد كامل المستحقات.'
        ]
    ]
];

// 3. معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = intval($_POST['client_id']);
    $date = $_POST['date'];
    $valid = $_POST['valid_until'];
    $manual_notes = $_POST['manual_notes'];
    
    // تجميع الشروط
    $terms_text = "";
    if(isset($_POST['terms']) && is_array($_POST['terms'])){
        $terms_text .= "\n\n--- الشروط والأحكام ---\n";
        foreach($_POST['terms'] as $term_content){
            if(!empty(trim($term_content))){
                $terms_text .= "• " . trim($term_content) . "\n";
            }
        }
        $terms_text .= "\n* يعتبر تحويل الدفعة المقدمة بمثابة موافقة نهائية على العرض.";
    }

    $final_notes = $conn->real_escape_string($manual_notes . $terms_text);
    $token = bin2hex(random_bytes(16));

    // حساب الإجمالي
    $grand_total = 0;
    if(isset($_POST['item_name'])){
        for($i=0; $i<count($_POST['item_name']); $i++){
            $grand_total += ($_POST['qty'][$i] * $_POST['price'][$i]);
        }
    }

    // الحفظ في قاعدة البيانات
    $sql = "INSERT INTO quotes (client_id, created_at, valid_until, total_amount, status, notes, access_token) 
            VALUES ('$client_id', '$date', '$valid', '$grand_total', 'pending', '$final_notes', '$token')";
    
    if($conn->query($sql)){
        $quote_id = $conn->insert_id;
        if(isset($_POST['item_name'])){
            for($i=0; $i<count($_POST['item_name']); $i++){
                $name = $conn->real_escape_string($_POST['item_name'][$i]);
                $qty = floatval($_POST['qty'][$i]);
                $price = floatval($_POST['price'][$i]);
                $total = $qty * $price;
                $conn->query("INSERT INTO quote_items (quote_id, item_name, quantity, price, total) VALUES ($quote_id, '$name', $qty, $price, $total)");
            }
        }
        header("Location: view_quote.php?id=$quote_id"); exit;
    }
}
?>

<style>
    /* 1. إعادة ضبط الهوية قسرياً */
    :root {
        --bg-dark: #050505;
        --card-bg: #121212;
        --input-bg: #1a1a1a;
        --gold: #d4af37;
        --gold-glow: rgba(212, 175, 55, 0.3);
        --text-main: #ffffff;
        --text-sub: #888888;
        --border: #333;
    }

    body {
        background-color: var(--bg-dark) !important;
        color: var(--text-main) !important;
        font-family: 'Cairo', sans-serif !important;
    }

    /* إصلاح مشاكل الهيدر القديم إن وجدت */
    .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    h1, h2, h3, h4, label { color: var(--text-main) !important; }

    /* 2. تصميم الكروت الفخم */
    .royal-card {
        background: linear-gradient(145deg, #151515, #0d0d0d);
        border: 1px solid var(--border);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        position: relative;
        overflow: hidden;
    }
    .royal-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px;
        background: linear-gradient(90deg, transparent, var(--gold), transparent);
    }

    /* 3. حقول الإدخال */
    .form-control, select.form-control {
        width: 100%;
        background-color: var(--input-bg) !important;
        border: 1px solid var(--border) !important;
        color: #fff !important;
        padding: 12px 15px;
        border-radius: 8px;
        transition: 0.3s;
        font-size: 0.95rem;
    }
    .form-control:focus {
        border-color: var(--gold) !important;
        box-shadow: 0 0 15px var(--gold-glow) !important;
        outline: none;
    }
    label { color: var(--text-sub) !important; margin-bottom: 8px; display: block; font-weight: 600; }

    /* 4. الجدول */
    .items-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .items-table th { text-align: right; color: var(--gold); padding: 10px; font-weight: bold; border-bottom: 1px solid var(--border); }
    .items-table td { background: var(--input-bg); padding: 5px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
    .items-table td:first-child { border-radius: 0 8px 8px 0; border-right: 3px solid var(--gold); }
    .items-table td:last-child { border-radius: 8px 0 0 8px; }
    
    .input-clean { 
        width: 100%; background: transparent; border: none; color: #fff; 
        padding: 10px; font-size: 1rem; outline: none;
    }
    
    /* 5. شبكة الشروط (Interactive Terms Grid) */
    .terms-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
    
    .term-card {
        background: #0f0f0f;
        border: 1px solid #222;
        border-radius: 12px;
        transition: 0.3s;
    }
    .term-card:hover { border-color: #444; }
    
    .term-header {
        background: #1a1a1a;
        padding: 12px 15px;
        border-radius: 12px 12px 0 0;
        display: flex; justify-content: space-between; align-items: center;
        border-bottom: 1px solid #222;
    }
    .term-title { color: #ccc !important; font-size: 0.95rem; font-weight: 700; margin: 0; }
    
    .term-body { padding: 15px; }
    .disabled-section { opacity: 0.3; pointer-events: none; filter: grayscale(1); }

    .term-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
    .term-textarea {
        width: 100%; background: transparent; border: 1px dashed #333;
        color: #aaa; padding: 8px; border-radius: 6px; font-size: 0.85rem;
        min-height: 40px; resize: vertical; transition: 0.3s;
    }
    .term-textarea:focus { border-color: var(--gold); color: #fff; background: #000; }
    .term-textarea:disabled { border: none; }

    /* 6. الأزرار (Buttons) */
    .btn-royal {
        background: linear-gradient(135deg, #d4af37, #b8860b);
        color: #000 !important;
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 1rem;
        cursor: pointer;
        box-shadow: 0 5px 15px var(--gold-glow);
        transition: transform 0.2s, box-shadow 0.2s;
        text-decoration: none; display: inline-block;
    }
    .btn-royal:hover { transform: translateY(-3px); box-shadow: 0 10px 25px var(--gold-glow); }
    
    .btn-outline {
        background: transparent; border: 1px solid var(--gold); color: var(--gold) !important;
        padding: 8px 20px; border-radius: 50px; font-size: 0.9rem; text-decoration: none;
    }
    .btn-outline:hover { background: var(--gold); color: #000 !important; }

    .btn-add {
        width: 100%; background: rgba(255,255,255,0.03); border: 1px dashed #444;
        color: var(--text-sub); padding: 12px; border-radius: 8px; cursor: pointer; transition: 0.3s;
    }
    .btn-add:hover { border-color: var(--gold); color: var(--gold); background: rgba(212,175,55,0.05); }

    /* 7. السويتش (Toggle Switch) */
    .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 20px; }
    .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--gold); }
    input:checked + .slider:before { transform: translateX(20px); }
</style>

<div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
        <div>
            <h2 style="font-size:1.8rem; margin:0; display:flex; align-items:center; gap:10px;">
                <i class="fa-solid fa-file-invoice-dollar" style="color:var(--gold);"></i> عرض سعر جديد
            </h2>
            <p style="color:var(--text-sub); margin:5px 0 0 0;">إصدار عرض مالي احترافي للعملاء</p>
        </div>
        <a href="quotes.php" class="btn-outline"><i class="fa-solid fa-arrow-right"></i> القائمة</a>
    </div>

    <form method="POST">
        
        <div class="royal-card">
            <h3 style="border-bottom:1px solid #333; padding-bottom:15px; margin-top:0; font-size:1.1rem;">
                <i class="fa-solid fa-user-tag"></i> بيانات العرض الأساسية
            </h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
                <div>
                    <label>اختار العميل</label>
                    <select name="client_id" required class="form-control">
                        <option value="">-- بحث عن عميل --</option>
                        <?php 
                        $clients = $conn->query("SELECT * FROM clients");
                        while($c = $clients->fetch_assoc()) echo "<option value='{$c['id']}'>{$c['name']}</option>";
                        ?>
                    </select>
                </div>
                <div>
                    <label>تاريخ العرض</label>
                    <input type="date" name="date" id="start_date" value="<?php echo date('Y-m-d'); ?>" class="form-control" required onchange="calcValidity()">
                </div>
                <div>
                    <label>تاريخ الانتهاء</label>
                    <input type="date" name="valid_until" id="end_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" class="form-control" required onchange="calcValidity()">
                </div>
            </div>
        </div>

        <div class="royal-card">
            <h3 style="border-bottom:1px solid #333; padding-bottom:15px; margin-top:0; font-size:1.1rem;">
                <i class="fa-solid fa-cart-flatbed"></i> تفاصيل البنود
            </h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="40%">البيان</th>
                        <th width="15%" style="text-align:center;">الكمية</th>
                        <th width="15%" style="text-align:center;">السعر</th>
                        <th width="20%" style="text-align:center;">الإجمالي</th>
                        <th width="10%"></th>
                    </tr>
                </thead>
                <tbody id="items_container">
                    <tr>
                        <td><input type="text" name="item_name[]" required class="input-clean" placeholder="اكتب اسم الخدمة أو المنتج..."></td>
                        <td><input type="number" name="qty[]" value="1" step="0.01" class="input-clean center" style="text-align:center;" oninput="calc(this)"></td>
                        <td><input type="number" name="price[]" value="0" step="0.01" class="input-clean center" style="text-align:center;" oninput="calc(this)"></td>
                        <td><input type="text" readonly class="input-clean center row-total" style="text-align:center; color:var(--gold);" value="0.00"></td>
                        <td style="text-align:center;"></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" onclick="addRow()" class="btn-add" style="margin-top:15px;">
                <i class="fa-solid fa-plus-circle"></i> إضافة بند جديد
            </button>
        </div>

        <div class="royal-card" style="background:#0a0a0a;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:20px;">
                <h3 style="margin:0; font-size:1.1rem; color:var(--gold);"><i class="fa-solid fa-scale-balanced"></i> تخصيص الشروط والأحكام</h3>
                <span style="font-size:0.8rem; color:#666;">قم بتفعيل الأقسام المطلوبة</span>
            </div>

            <div style="margin-bottom:20px;">
                <label>ملاحظة افتتاحية (تظهر أعلى الشروط)</label>
                <textarea name="manual_notes" class="form-control" rows="2" placeholder="مثال: الأسعار لا تشمل مصاريف الشحن..."></textarea>
            </div>

            <div class="terms-grid">
                <?php foreach($default_terms as $key => $section): ?>
                <div class="term-card">
                    <div class="term-header">
                        <span class="term-title"><?php echo $section['title']; ?></span>
                        <label class="switch">
                            <input type="checkbox" onchange="toggleSection(this, 'sec_<?php echo $key; ?>')" <?php echo ($key == 'general') ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <div id="sec_<?php echo $key; ?>" class="term-body <?php echo ($key == 'general') ? '' : 'disabled-section'; ?>">
                        <?php foreach($section['items'] as $k => $text): ?>
                            <div class="term-row">
                                <input type="checkbox" id="chk_<?php echo $key.$k; ?>" onchange="toggleTerm(this, 'txt_<?php echo $key.$k; ?>')" checked style="margin-top:5px; accent-color:var(--gold);">
                                <textarea name="terms[]" id="txt_<?php echo $key.$k; ?>" class="term-textarea <?php echo ($k=='validity')?'validity-term':''; ?>" 
                                          data-original="<?php echo $text; ?>"><?php echo $text; ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="background:#111; padding:20px; border-radius:15px; border:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
            <div style="display:flex; align-items:center; gap:20px;">
                <span style="color:#888;">الإجمالي النهائي:</span>
                <span style="font-size:2.2rem; font-weight:900; color:var(--gold); line-height:1;" id="grand_total">0.00</span>
                <span style="color:var(--gold);">EGP</span>
            </div>
            <button type="submit" class="btn-royal">
                <i class="fa-solid fa-save"></i> حفظ وإصدار العرض
            </button>
        </div>

    </form>
</div>

<script>
// 1. منطق الجدول
function addRow() {
    let tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="item_name[]" required class="input-clean" placeholder="..."></td>
        <td><input type="number" name="qty[]" value="1" step="0.01" class="input-clean center" style="text-align:center;" oninput="calc(this)"></td>
        <td><input type="number" name="price[]" value="0" step="0.01" class="input-clean center" style="text-align:center;" oninput="calc(this)"></td>
        <td><input type="text" readonly class="input-clean center row-total" style="text-align:center; color:var(--gold);" value="0.00"></td>
        <td style="text-align:center;"><i class="fa-solid fa-trash-can" onclick="this.closest('tr').remove(); calcTotal();" style="cursor:pointer; color:#e74c3c; font-size:1.1rem;"></i></td>
    `;
    document.getElementById('items_container').appendChild(tr);
}

function calc(el) {
    let tr = el.closest('tr');
    let q = parseFloat(tr.querySelector('[name="qty[]"]').value) || 0;
    let p = parseFloat(tr.querySelector('[name="price[]"]').value) || 0;
    tr.querySelector('.row-total').value = (q * p).toFixed(2);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('.row-total').forEach(e => total += parseFloat(e.value));
    document.getElementById('grand_total').innerText = total.toFixed(2);
}

// 2. منطق الشروط
function toggleSection(chk, secId) {
    let sec = document.getElementById(secId);
    if(chk.checked) {
        sec.classList.remove('disabled-section');
        sec.querySelectorAll('textarea').forEach(t => {
            // إعادة تفعيل الـ textareas المفعلة بالـ checkboxes الداخلية
            let checkboxId = t.id.replace('txt_', 'chk_');
            if(document.getElementById(checkboxId).checked) t.disabled = false;
        });
    } else {
        sec.classList.add('disabled-section');
        sec.querySelectorAll('textarea').forEach(t => t.disabled = true);
    }
}

function toggleTerm(chk, txtId) {
    let txt = document.getElementById(txtId);
    txt.disabled = !chk.checked;
    if(chk.checked) txt.focus();
}

// 3. الذكاء الزمني
function calcValidity() {
    let start = new Date(document.getElementById('start_date').value);
    let end = new Date(document.getElementById('end_date').value);
    if(start && end) {
        let diffTime = end - start;
        let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
        if(diffDays < 0) diffDays = 0;
        
        let validityBox = document.querySelector('.validity-term');
        if(validityBox) {
            let originalText = validityBox.getAttribute('data-original');
            validityBox.value = originalText.replace('{DAYS}', diffDays);
        }
    }
}

window.onload = calcValidity;
</script>

<?php include 'footer.php'; ob_end_flush(); ?>