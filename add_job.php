<?php 
// add_job.php - (Royal Ops V30.0 - Mobile Responsive & Smart Logic)
// تم الحفاظ على جميع الفنيات والمواصفات كما هي
error_reporting(E_ALL); ini_set('display_errors', 1);
require 'auth.php'; require 'config.php'; 
require 'header.php'; 

// --- 1. التحقق من الصلاحيات ---
if($_SESSION['role'] == 'driver' || $_SESSION['role'] == 'accountant'){
    die("<div class='container'><div class='alert-box' style='color:red; text-align:center; padding:50px; background:#1a1a1a; border-radius:10px;'>⛔ عذراً، ليس لديك صلاحية فتح أمر شغل.</div></div>");
}

// --- معالجة الحفظ (Smart Save) ---
if(isset($_POST['save_job'])){
    $client_id = intval($_POST['client_id']);
    $job_name = $conn->real_escape_string($_POST['job_name']);
    $job_type = $_POST['job_type'];
    $delivery_date = $_POST['delivery_date'];
    $notes = $conn->real_escape_string($_POST['notes']);
    $design_status = $_POST['design_status'] ?? 'ready';
    
    // تجميع التفاصيل الفنية (كما هي تماماً)
    $details = [];
    $details[] = "--- تفاصيل العملية ---";
    
    // معالجة نوع الورق
    $final_paper_type = $_POST['paper_type'] ?? '';
    if($final_paper_type == 'other' && !empty($_POST['paper_type_other'])){
        $final_paper_type = $_POST['paper_type_other'];
    }

    $qty = 0; 

    // 1. التصميم فقط
    if($job_type == 'design_only'){
        $qty = intval($_POST['design_items_count']);
        $details[] = "عدد البنود: " . $qty;
    }
    
    // 2. الطباعة
    elseif($job_type == 'print'){
        $qty = floatval($_POST['print_quantity'] ?? 0); 
        $details[] = "الكمية المطلوبة: " . $qty;
        $details[] = "الورق: " . $final_paper_type . " | الوزن: " . $_POST['paper_weight'] . "جم";
        $details[] = "مقاس الورق: " . $_POST['paper_w'] . "x" . $_POST['paper_h'];
        $details[] = "مقاس القص: " . $_POST['cut_w'] . "x" . $_POST['cut_h'];
        $details[] = "الألوان: " . $_POST['print_colors'] . " | طريقة الطبع: " . $_POST['print_mode'];
        $details[] = "الزنكات: " . $_POST['zinc_count'] . " (" . $_POST['zinc_status'] . ")";
        if(isset($_POST['print_finish'])) $details[] = "التكميلي: " . implode(" + ", $_POST['print_finish']);
    }

    // 3. الكرتون
    elseif($job_type == 'carton'){
        $carton_paper = $_POST['carton_paper_type'];
        if($carton_paper == 'other' && !empty($_POST['carton_paper_other'])){
            $carton_paper = $_POST['carton_paper_other'];
        }
        $qty = floatval($_POST['carton_quantity'] ?? 0);
        $details[] = "الكمية المطلوبة: " . $qty;
        $details[] = "الخامة الخارجية: " . $carton_paper;
        $details[] = "عدد الطبقات: " . $_POST['carton_layers'];
        $details[] = "تفاصيل الطبقات: " . $_POST['carton_details'];
        $details[] = "مقاس القص: " . $_POST['carton_cut_w'] . "x" . $_POST['carton_cut_h'];
        $details[] = "الزنكات: " . $_POST['carton_zinc_count'] . " (" . $_POST['carton_zinc_status'] . ")";
        if(isset($_POST['carton_finish'])) $details[] = "التكميلي: " . implode(" + ", $_POST['carton_finish']);
    }

    // 4. البلاستيك
    elseif($job_type == 'plastic'){
        $qty = floatval($_POST['plastic_quantity'] ?? 0);
        $details[] = "الكمية: " . $qty;
        $details[] = "الخامة: " . $_POST['plastic_material'];
        $details[] = "السمك: " . $_POST['plastic_microns'] . " ميكرون | عرض الفيلم: " . $_POST['film_width'];
        $details[] = "المعالجة: " . $_POST['plastic_treatment'];
        $details[] = "طول القص: " . $_POST['plastic_cut_len'];
        $details[] = "السلندرات: " . $_POST['cylinder_count'] . " (" . $_POST['cylinder_status'] . ")";
    }

    // 5. التسويق
    elseif($job_type == 'social'){
        $qty = intval($_POST['social_items_count']);
        $details[] = "عدد البوستات/الفيديوهات: " . $qty;
        
        $platforms = isset($_POST['social_platforms']) ? implode(", ", $_POST['social_platforms']) : "غير محدد";
        $details[] = "المنصات المستهدفة: " . $platforms;
        
        if(!empty($_POST['campaign_goal'])) $details[] = "الهدف: " . $_POST['campaign_goal'];
        if(!empty($_POST['target_audience'])) $details[] = "الجمهور: " . $_POST['target_audience'];
        if(!empty($_POST['ad_budget'])) $details[] = "الميزانية المقترحة: " . $_POST['ad_budget'];
    }

    // 6. المواقع
    elseif($job_type == 'web'){
        $qty = 1;
        $details[] = "نوع الموقع: " . $_POST['web_type'];
        $details[] = "الدومين: " . $_POST['web_domain'];
        $details[] = "الاستضافة: " . $_POST['web_hosting'];
        $details[] = "الثيم: " . $_POST['web_theme'];
    }

    $job_details_text = implode("\n", $details);
    $job_details_text = $conn->real_escape_string($job_details_text);

    // التوجيه الذكي (Smart Routing)
    $current_stage = 'briefing'; 
    if(in_array($job_type, ['design_only', 'social', 'web'])) {
        $current_stage = 'briefing'; 
    } elseif ($job_type == 'plastic') {
        $current_stage = ($design_status == 'needed') ? 'design' : 'cylinders';
    } else {
        $current_stage = ($design_status == 'needed') ? 'design' : 'pre_press';
    }

    // الإدخال الأولي للطلب (للحصول على ID)
    $user = $_SESSION['name'] ?? $_SESSION['username'];
    $sql = "INSERT INTO job_orders 
            (client_id, job_name, job_type, design_status, start_date, delivery_date, current_stage, 
             quantity, notes, added_by, job_details) 
            VALUES 
            ('$client_id', '$job_name', '$job_type', '$design_status', NOW(), '$delivery_date', '$current_stage', 
             '$qty', '$notes', '$user', '$job_details_text')";

    if($conn->query($sql)){
        $new_id = $conn->insert_id;
        
        // رفع الملفات (Smart Upload)
        if(!empty($_FILES['attachment']['name'][0])){
            if (!file_exists('uploads')) mkdir('uploads', 0777, true);
            
            // التعامل مع تعدد الملفات بشكل صحيح
            $total_files = count($_FILES['attachment']['name']);
            for($i=0; $i < $total_files; $i++) {
                if($_FILES['attachment']['error'][$i] == 0){
                    $file_name = $_FILES['attachment']['name'][$i];
                    $target = "uploads/" . time() . "_" . $i . "_" . basename($file_name);
                    
                    if(move_uploaded_file($_FILES['attachment']['tmp_name'][$i], $target)){
                        // إدراج الملف في جدول job_files وربطه بالمرحلة الأولى
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) 
                                      VALUES ($new_id, '$target', '$current_stage', 'ملف مرفق عند الإنشاء', '$user')");
                    }
                }
            }
        }

        echo "<script>
            alert('✅ تم فتح أمر الشغل رقم #$new_id بنجاح وتم توجيهه لقسم: $current_stage');
            window.location.href='job_details.php?id=$new_id';
        </script>";
    } else {
        echo "<script>alert('خطأ: " . $conn->error . "');</script>";
    }
}
?>

<style>
    :root { --bg-dark: #121212; --panel: #1e1e1e; --gold: #d4af37; --text: #e0e0e0; }
    body { background-color: var(--bg-dark); color: var(--text); font-family: 'Cairo', sans-serif; margin: 0; padding-bottom: 50px; }
    
    .container { max-width: 1000px; margin: 0 auto; padding: 15px; }

    .royal-card {
        background: var(--panel);
        border: 1px solid #333;
        border-top: 4px solid var(--gold);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    
    .section-header {
        color: var(--gold);
        font-size: 1.1rem;
        border-bottom: 1px solid #333;
        padding-bottom: 10px;
        margin: 25px 0 15px 0;
        display: flex; align-items: center; gap: 10px;
    }
    
    /* Responsive Grid System */
    .grid-row { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* متجاوب مع الموبايل */
        gap: 15px; 
        margin-bottom: 15px; 
    }
    
    label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #aaa; }
    
    input, select, textarea {
        width: 100%; padding: 12px;
        background: #0a0a0a; border: 1px solid #444; color: #fff;
        border-radius: 8px; font-family: 'Cairo'; transition: 0.3s;
        box-sizing: border-box; /* يمنع الخروج عن الإطار */
    }
    input:focus, select:focus, textarea:focus { border-color: var(--gold); outline: none; box-shadow: 0 0 10px rgba(212, 175, 55, 0.2); }
    
    .btn-royal {
        background: linear-gradient(135deg, var(--gold), #b8860b);
        color: #000; font-weight: bold; border: none;
        padding: 15px; border-radius: 50px;
        cursor: pointer; font-size: 1.1rem; width: 100%; margin-top: 20px;
        transition: transform 0.2s;
        box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
    }
    .btn-royal:hover { transform: translateY(-2px); }
    
    .dynamic-section { display: none; animation: fadeIn 0.5s; }
    @keyframes fadeIn { from {opacity:0; transform:translateY(-10px);} to {opacity:1; transform:translateY(0);} }
    
    /* Checkbox Styling */
    .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
    .cb-label {
        background: #252525; padding: 10px 15px; border-radius: 8px; cursor: pointer; border: 1px solid #333;
        display: flex; align-items: center; gap: 8px; font-size: 0.85rem; transition: 0.3s; flex: 1; min-width: 120px;
    }
    .cb-label:hover { border-color: var(--gold); transform: translateY(-2px); }
    .cb-label i { font-size: 1.1rem; }
    input[type="checkbox"] { width: auto; accent-color: var(--gold); margin: 0; }

    /* Custom Scrollbar for Select on Mobile */
    select { -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23d4af37' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: left 10px center; background-size: 15px; padding-left: 30px; }
</style>

<div class="container">
    <div class="royal-card">
        <h2 style="text-align:center; color:var(--gold); margin-top:0; border-bottom:1px dashed #333; padding-bottom:15px;">🦅 أمر تشغيل ذكي</h2>
        
        <form method="post" enctype="multipart/form-data">
            
            <div class="section-header"><i class="fa-solid fa-circle-info"></i> 1. البيانات الأساسية</div>
            <div class="grid-row">
                <div>
                    <label>العميل</label>
                    <select name="client_id" required>
                        <option value="">-- اختر العميل --</option>
                        <?php 
                        $c_res = $conn->query("SELECT id, name FROM clients ORDER BY name ASC");
                        while($row = $c_res->fetch_assoc()) echo "<option value='{$row['id']}'>{$row['name']}</option>";
                        ?>
                    </select>
                </div>
                <div><label>اسم العملية</label><input type="text" name="job_name" required placeholder="مثال: علبة حلويات رمضان"></div>
                <div><label>تاريخ التسليم</label><input type="date" name="delivery_date" required></div>
            </div>

            <div class="section-header"><i class="fa-solid fa-layer-group"></i> 2. القسم الفني</div>
            <div class="grid-row">
                <div>
                    <label>نوع العملية (القسم)</label>
                    <select name="job_type" id="job_type" onchange="showSection()" style="border-color:var(--gold);">
                        <option value="">-- حدد القسم --</option>
                        <option value="print">🖨️ قسم الطباعة (أوفست/ديجيتال)</option>
                        <option value="carton">📦 قسم الكرتون</option>
                        <option value="plastic">🛍️ قسم البلاستيك</option>
                        <option value="social">📱 التسويق الإلكتروني</option>
                        <option value="web">🌐 المواقع والبرمجة</option>
                        <option value="design_only">🎨 قسم التصميم فقط</option>
                    </select>
                </div>
                
                <div id="design_toggle" style="display:none;">
                    <label>حالة التصميم</label>
                    <select name="design_status">
                        <option value="needed">🖌️ يحتاج تصميم (مرحلة أولى)</option>
                        <option value="ready">💾 التصميم جاهز (تخطي للتجهيز)</option>
                    </select>
                </div>
            </div>

            <div id="sec_design_only" class="dynamic-section">
                <div class="section-header">🎨 تفاصيل طلب التصميم</div>
                <div class="grid-row">
                    <div><label>عدد البنود المطلوبة *</label><input type="number" name="design_items_count" value="1"></div>
                </div>
            </div>

            <div id="sec_print" class="dynamic-section">
                <div class="section-header">📋 مواصفات الطباعة</div>
                <div class="grid-row">
                    <div><label>الكمية المطلوبة (نسخة/فرخ)</label><input type="number" step="any" name="print_quantity" placeholder="العدد المطلوب"></div>
                </div>
                <div class="grid-row">
                    <div>
                        <label>نوع الورق</label>
                        <select name="paper_type" id="paper_type" onchange="toggleOtherPaper('print')">
                            <option value="كوشيه">كوشيه</option>
                            <option value="دوبلكس">دوبلكس</option>
                            <option value="برستول">برستول</option>
                            <option value="طبع">طبع</option>
                            <option value="other">--- أخرى (حدد) ---</option>
                        </select>
                        <input type="text" name="paper_type_other" id="paper_type_other" placeholder="اكتب نوع الورق..." style="display:none; margin-top:5px; border-color:#2ecc71;">
                    </div>
                    <div><label>الوزن (جرام)</label><input type="number" step="any" name="paper_weight"></div>
                    <div><label>عدد الألوان</label><input type="text" name="print_colors"></div>
                </div>
                <div class="grid-row">
                    <div><label>مقاس الورق (سم)</label><div style="display:flex; gap:5px;"><input placeholder="عرض" name="paper_w"><input placeholder="طول" name="paper_h"></div></div>
                    <div><label>مقاس القص (سم)</label><div style="display:flex; gap:5px;"><input placeholder="عرض" name="cut_w"><input placeholder="طول" name="cut_h"></div></div>
                </div>
                <div class="grid-row">
                    <div>
                        <label>طريقة الطباعة</label>
                        <select name="print_mode">
                            <option value="وجه واحد">وجه واحد</option>
                            <option value="وجهين">وجهين</option>
                            <option value="طبع وقلب بنسة">طبع وقلب بنسة</option>
                            <option value="طبع وقلب ديل">طبع وقلب ديل</option>
                        </select>
                    </div>
                    <div><label>عدد الزنكات</label><input type="number" step="any" name="zinc_count"></div>
                    <div><label>حالة الزنكات</label><select name="zinc_status"><option>جديدة</option><option>مستخدمة</option></select></div>
                </div>
                <label>العمليات التكميلية:</label>
                <div class="checkbox-group">
                    <label class="cb-label"><input type="checkbox" name="print_finish[]" value="سلفان لامع"> سلفان لامع</label>
                    <label class="cb-label"><input type="checkbox" name="print_finish[]" value="سلفان مط"> سلفان مط</label>
                    <label class="cb-label"><input type="checkbox" name="print_finish[]" value="سبوت يوفي"> سبوت يوفي</label>
                    <label class="cb-label"><input type="checkbox" name="print_finish[]" value="تكسير"> تكسير</label>
                    <label class="cb-label"><input type="checkbox" name="print_finish[]" value="لصق"> لصق</label>
                </div>
            </div>

            <div id="sec_carton" class="dynamic-section">
                <div class="section-header">📦 مواصفات الكرتون</div>
                <div class="grid-row">
                    <div><label>الكمية المطلوبة (علبة)</label><input type="number" step="any" name="carton_quantity" placeholder="العدد المطلوب"></div>
                </div>
                <div class="grid-row">
                    <div>
                        <label>نوع الورق الخارجي</label>
                        <select name="carton_paper_type" id="carton_paper_type" onchange="toggleOtherPaper('carton')">
                            <option value="كوشيه">كوشيه</option>
                            <option value="دوبلكس">دوبلكس</option>
                            <option value="كرافت">كرافت</option>
                            <option value="other">--- أخرى (حدد) ---</option>
                        </select>
                        <input type="text" name="carton_paper_other" id="carton_paper_other" placeholder="اكتب نوع الورق..." style="display:none; margin-top:5px; border-color:#2ecc71;">
                    </div>
                    <div><label>عدد طبقات الكرتون</label><input type="number" name="carton_layers" placeholder="مثال: 3"></div>
                </div>
                <label>تفاصيل الطبقات والأوزان:</label>
                <textarea name="carton_details" placeholder="اكتب تفاصيل كل طبقة هنا (مثال: E-Flute + كرافت 150جم)"></textarea>
                <div class="grid-row" style="margin-top:15px;">
                    <div><label>مقاس القص النهائي</label><div style="display:flex; gap:5px;"><input placeholder="عرض" name="carton_cut_w"><input placeholder="طول" name="carton_cut_h"></div></div>
                    <div><label>عدد الزنكات</label><input type="number" step="any" name="carton_zinc_count"></div>
                    <div><label>حالة الزنكات</label><select name="carton_zinc_status"><option>جديدة</option><option>مستخدمة</option></select></div>
                </div>
                <label>التشطيب:</label>
                <div class="checkbox-group">
                    <label class="cb-label"><input type="checkbox" name="carton_finish[]" value="سلفان"> سلفان</label>
                    <label class="cb-label"><input type="checkbox" name="carton_finish[]" value="بصمة"> بصمة</label>
                    <label class="cb-label"><input type="checkbox" name="carton_finish[]" value="تكسير"> تكسير</label>
                </div>
            </div>

            <div id="sec_plastic" class="dynamic-section">
                <div class="section-header">🛍️ مواصفات البلاستيك</div>
                <div class="grid-row">
                    <div><label>الكمية المطلوبة (كجم/قطعة)</label><input type="number" step="any" name="plastic_quantity" placeholder="الوزن أو العدد"></div>
                </div>
                <div class="grid-row">
                    <div>
                        <label>نوع الخامة</label>
                        <select name="plastic_material">
                            <option value="HDPE">هاي (HDPE)</option>
                            <option value="LDPE">لو (LDPE)</option>
                            <option value="PP">PP</option>
                        </select>
                    </div>
                    <div><label>السمك (ميكرون)</label><input type="number" step="any" name="plastic_microns"></div>
                    <div><label>عرض الفيلم (سم)</label><input type="text" name="film_width"></div>
                </div>
                <div class="grid-row">
                    <div>
                        <label>المعالجة</label>
                        <select name="plastic_treatment">
                            <option value="بدون">بدون</option>
                            <option value="وجه واحد">وجه واحد</option>
                            <option value="وجهين">وجهين</option>
                        </select>
                    </div>
                    <div><label>طول القص</label><input type="text" name="plastic_cut_len"></div>
                </div>
                <div class="grid-row">
                    <div><label>عدد السلندرات</label><input type="number" step="any" name="cylinder_count"></div>
                    <div><label>حالتها</label><select name="cylinder_status"><option>جديدة</option><option>مستخدمة</option></select></div>
                </div>
            </div>

            <div id="sec_social" class="dynamic-section">
                <div class="section-header">📱 حملة تسويق إلكتروني</div>
                <div class="grid-row">
                    <div>
                        <label>الهدف من الحملة</label>
                        <select name="campaign_goal" style="border-color:var(--gold);">
                            <option value="Awareness">📢 الوعي بالعلامة التجارية (Awareness)</option>
                            <option value="Engagement">👍 التفاعل (Engagement)</option>
                            <option value="Traffic">🌐 زيارات الموقع (Traffic)</option>
                            <option value="Leads">🎯 تجميع بيانات عملاء (Leads)</option>
                            <option value="Sales">💰 مبيعات مباشرة (Sales)</option>
                            <option value="App">📲 تحميل تطبيق (App Promotion)</option>
                        </select>
                    </div>
                    <div>
                        <label>عدد البوستات/الفيديوهات</label>
                        <input type="number" name="social_items_count" value="4">
                    </div>
                </div>

                <label style="margin-bottom:15px; display:block;">المنصات المستهدفة (اختر ما يناسبك):</label>
                <div class="checkbox-group" style="margin-bottom:20px;">
                    <label class="cb-label cb-fb"><input type="checkbox" name="social_platforms[]" value="Facebook"> <i class="fa-brands fa-facebook"></i> فيسبوك</label>
                    <label class="cb-label cb-ig"><input type="checkbox" name="social_platforms[]" value="Instagram"> <i class="fa-brands fa-instagram"></i> انستجرام</label>
                    <label class="cb-label cb-tk"><input type="checkbox" name="social_platforms[]" value="TikTok"> <i class="fa-brands fa-tiktok"></i> تيك توك</label>
                    <label class="cb-label cb-sc"><input type="checkbox" name="social_platforms[]" value="Snapchat"> <i class="fa-brands fa-snapchat"></i> سناب شات</label>
                    <label class="cb-label cb-li"><input type="checkbox" name="social_platforms[]" value="LinkedIn"> <i class="fa-brands fa-linkedin"></i> لينكد إن</label>
                    <label class="cb-label cb-x"><input type="checkbox" name="social_platforms[]" value="X (Twitter)"> <i class="fa-brands fa-x-twitter"></i> إكس (تويتر)</label>
                    <label class="cb-label cb-yt"><input type="checkbox" name="social_platforms[]" value="YouTube"> <i class="fa-brands fa-youtube"></i> يوتيوب</label>
                    <label class="cb-label cb-gl"><input type="checkbox" name="social_platforms[]" value="Google Ads"> <i class="fa-brands fa-google"></i> جوجل أدز</label>
                </div>

                <div class="grid-row">
                    <div><label>الجمهور المستهدف (باختصار)</label><input type="text" name="target_audience" placeholder="مثال: نساء، مهتمين بالموضة، الرياض..."></div>
                    <div><label>الميزانية الإعلانية المقترحة (اختياري)</label><input type="text" name="ad_budget" placeholder="مثال: 5000 ريال"></div>
                </div>
            </div>

            <div id="sec_web" class="dynamic-section">
                <div class="section-header">🌐 تطوير موقع إلكتروني</div>
                <div class="grid-row">
                    <div>
                        <label>نوع الموقع</label>
                        <select name="web_type">
                            <option value="تعريفي">تعريفي (Corporate)</option>
                            <option value="متجر">متجر إلكتروني (E-Commerce)</option>
                            <option value="تطبيق">تطبيق جوال</option>
                        </select>
                    </div>
                    <div><label>النطاق (Domain)</label><input type="text" name="web_domain"></div>
                    <div><label>الاستضافة (Hosting)</label><input type="text" name="web_hosting"></div>
                </div>
                <label>الثيم / الشكل المطلوب</label>
                <textarea name="web_theme" rows="2"></textarea>
            </div>

            <div class="section-header"><i class="fa-solid fa-paperclip"></i> 4. مرفقات وملاحظات</div>
            <div class="grid-row">
                <div><label>ملفات مساعدة (يمكن اختيار أكثر من ملف)</label><input type="file" name="attachment[]" multiple></div>
            </div>
            <label>ملاحظات عامة</label>
            <textarea name="notes" rows="3" placeholder="أي تفاصيل إضافية..."></textarea>

            <button type="submit" name="save_job" class="btn-royal">🚀 إطلاق أمر الشغل</button>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
    function showSection() {
        document.querySelectorAll('.dynamic-section').forEach(el => el.style.display = 'none');
        document.getElementById('design_toggle').style.display = 'none';
        
        var type = document.getElementById('job_type').value;
        
        if(type == 'design_only') document.getElementById('sec_design_only').style.display = 'block';
        else if(type == 'print') { document.getElementById('sec_print').style.display = 'block'; document.getElementById('design_toggle').style.display = 'block'; }
        else if(type == 'carton') { document.getElementById('sec_carton').style.display = 'block'; document.getElementById('design_toggle').style.display = 'block'; }
        else if(type == 'plastic') { document.getElementById('sec_plastic').style.display = 'block'; document.getElementById('design_toggle').style.display = 'block'; }
        else if(type == 'social') document.getElementById('sec_social').style.display = 'block';
        else if(type == 'web') document.getElementById('sec_web').style.display = 'block';
    }

    function toggleOtherPaper(section) {
        if(section === 'print') {
            var val = document.getElementById('paper_type').value;
            document.getElementById('paper_type_other').style.display = (val === 'other') ? 'block' : 'none';
        } else if (section === 'carton') {
            var val = document.getElementById('carton_paper_type').value;
            document.getElementById('carton_paper_other').style.display = (val === 'other') ? 'block' : 'none';
        }
    }
</script>

<?php include 'footer.php'; ob_end_flush(); ?>