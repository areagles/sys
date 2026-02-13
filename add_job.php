<?php 
// add_job.php - (Royal Ops V30.0 - Secure Uploads)
error_reporting(E_ALL); ini_set('display_errors', 0); // Hide errors on prod
require 'auth.php'; require 'config.php'; 
require 'header.php'; 

if($_SESSION['role'] == 'driver' || $_SESSION['role'] == 'accountant'){
    die("<div class='container'><div class='alert-box' style='color:red; text-align:center; padding:50px; background:#1a1a1a; border-radius:10px;'>⛔ عذراً، ليس لديك صلاحية فتح أمر شغل.</div></div>");
}

if(isset($_POST['save_job'])){
    $client_id = intval($_POST['client_id']);
    $job_name = $conn->real_escape_string($_POST['job_name']);
    $job_type = $_POST['job_type'];
    $delivery_date = $_POST['delivery_date'];
    $notes = $conn->real_escape_string($_POST['notes']);
    $design_status = $_POST['design_status'] ?? 'ready';
    
    // --- تجميع التفاصيل الفنية ---
    $details = [];
    $details[] = "--- تفاصيل العملية ---";
    
    $final_paper_type = $_POST['paper_type'] ?? '';
    if($final_paper_type == 'other' && !empty($_POST['paper_type_other'])) $final_paper_type = $_POST['paper_type_other'];

    $qty = 0; 
    if($job_type == 'design_only'){
        $qty = intval($_POST['design_items_count']);
        $details[] = "عدد البنود: " . $qty;
    } elseif($job_type == 'print'){
        $qty = floatval($_POST['print_quantity'] ?? 0); 
        $details[] = "الكمية المطلوبة: " . $qty;
        $details[] = "الورق: " . $final_paper_type . " | الوزن: " . $_POST['paper_weight'] . "جم";
        $details[] = "مقاس الورق: " . $_POST['paper_w'] . "x" . $_POST['paper_h'];
        $details[] = "مقاس القص: " . $_POST['cut_w'] . "x" . $_POST['cut_h'];
        $details[] = "الألوان: " . $_POST['print_colors'] . " | طريقة الطبع: " . $_POST['print_mode'];
        $details[] = "الزنكات: " . $_POST['zinc_count'] . " (" . $_POST['zinc_status'] . ")";
        if(isset($_POST['print_finish'])) $details[] = "التكميلي: " . implode(" + ", $_POST['print_finish']);
    } elseif($job_type == 'carton'){
        $carton_paper = $_POST['carton_paper_type'];
        if($carton_paper == 'other' && !empty($_POST['carton_paper_other'])) $carton_paper = $_POST['carton_paper_other'];
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
    $job_details_text = $conn->real_escape_string($job_details_text);

    $current_stage = 'briefing'; 
    if(in_array($job_type, ['design_only', 'social', 'web'])) {
        $current_stage = 'briefing'; 
    } elseif ($job_type == 'plastic') {
        $current_stage = ($design_status == 'needed') ? 'design' : 'cylinders';
    } else {
        $current_stage = ($design_status == 'needed') ? 'design' : 'pre_press';
    }

    $user = $_SESSION['name'] ?? $_SESSION['username'];
    $sql = "INSERT INTO job_orders (client_id, job_name, job_type, design_status, start_date, delivery_date, current_stage, quantity, notes, added_by, job_details) 
            VALUES ('$client_id', '$job_name', '$job_type', '$design_status', NOW(), '$delivery_date', '$current_stage', '$qty', '$notes', '$user', '$job_details_text')";

    if($conn->query($sql)){
        $new_id = $conn->insert_id;
        
        // --- Smart Secure Upload Strategy ---
        if(!empty($_FILES['attachment']['name'][0])){
            // Fix: Use 0755 permissions instead of 0777
            if (!file_exists('uploads')) mkdir('uploads', 0755, true);
            
            $allowed_file_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'ai', 'psd', 'cdr', 'txt'];
            
            $total_files = count($_FILES['attachment']['name']);
            for($i=0; $i < $total_files; $i++) {
                if($_FILES['attachment']['error'][$i] == 0){
                    $file_name = $_FILES['attachment']['name'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Security Check: Whitelist
                    if(in_array($file_ext, $allowed_file_types)){
                        // Rename file to prevent overwriting and guessing
                        $new_name = time() . "_" . $i . "_" . uniqid() . "." . $file_ext;
                        $target = "uploads/" . $new_name;
                        
                        if(move_uploaded_file($_FILES['attachment']['tmp_name'][$i], $target)){
                            $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ($new_id, '$target', '$current_stage', 'ملف مرفق عند الإنشاء', '$user')");
                        }
                    } else {
                        echo "<script>alert('⚠️ تنبيه: تم تجاهل الملف ($file_name) لأن صيغته غير مسموحة أمنياً.');</script>";
                    }
                }
            }
        }

        echo "<script>alert('✅ تم فتح أمر الشغل رقم #$new_id بنجاح'); window.location.href='job_details.php?id=$new_id';</script>";
    } else {
        echo "<script>alert('خطأ: " . $conn->error . "');</script>";
    }
}
?>

<style>
    :root { --bg-dark: #121212; --panel: #1e1e1e; --gold: #d4af37; --text: #e0e0e0; }
    body { background-color: var(--bg-dark); color: var(--text); font-family: 'Cairo', sans-serif; margin: 0; padding-bottom: 50px; }
    .container { max-width: 1000px; margin: 0 auto; padding: 15px; }
    .royal-card { background: var(--panel); border: 1px solid #333; border-top: 4px solid var(--gold); border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .section-header { color: var(--gold); font-size: 1.1rem; border-bottom: 1px solid #333; padding-bottom: 10px; margin: 25px 0 15px 0; display: flex; align-items: center; gap: 10px; }
    .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px; }
    label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #aaa; }
    input, select, textarea { width: 100%; padding: 12px; background: #0a0a0a; border: 1px solid #444; color: #fff; border-radius: 8px; font-family: 'Cairo'; transition: 0.3s; box-sizing: border-box; }
    input:focus, select:focus, textarea:focus { border-color: var(--gold); outline: none; box-shadow: 0 0 10px rgba(212, 175, 55, 0.2); }
    .btn-royal { background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; font-weight: bold; border: none; padding: 15px; border-radius: 50px; cursor: pointer; font-size: 1.1rem; width: 100%; margin-top: 20px; transition: transform 0.2s; box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3); }
    .btn-royal:hover { transform: translateY(-2px); }
    .dynamic-section { display: none; animation: fadeIn 0.5s; }
    @keyframes fadeIn { from {opacity:0; transform:translateY(-10px);} to {opacity:1; transform:translateY(0);} }
    .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
    .cb-label { background: #252525; padding: 10px 15px; border-radius: 8px; cursor: pointer; border: 1px solid #333; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; transition: 0.3s; flex: 1; min-width: 120px; }
    .cb-label:hover { border-color: var(--gold); transform: translateY(-2px); }
    input[type="checkbox"] { width: auto; accent-color: var(--gold); margin: 0; }
</style>

<div class="container">
    <div class="royal-card">
        <h2 style="text-align:center; color:var(--gold); margin-top:0; border-bottom:1px dashed #333; padding-bottom:15px;">🦅 أمر تشغيل ذكي</h2>
        <form method="post" enctype="multipart/form-data">
            <div class="section-header"><i class="fa-solid fa-circle-info"></i> 1. البيانات الأساسية</div>
            <div class="grid-row">
                <div><label>العميل</label><select name="client_id" required><option value="">-- اختر العميل --</option><?php $c_res = $conn->query("SELECT id, name FROM clients ORDER BY name ASC"); while($row = $c_res->fetch_assoc()) echo "<option value='{$row['id']}'>{$row['name']}</option>"; ?></select></div>
                <div><label>اسم العملية</label><input type="text" name="job_name" required placeholder="مثال: علبة حلويات رمضان"></div>
                <div><label>تاريخ التسليم</label><input type="date" name="delivery_date" required></div>
            </div>

            <div class="section-header"><i class="fa-solid fa-layer-group"></i> 2. القسم الفني</div>
            <div class="grid-row">
                <div><label>نوع العملية</label><select name="job_type" id="job_type" onchange="showSection()"><option value="">-- حدد القسم --</option><option value="print">🖨️ قسم الطباعة</option><option value="carton">📦 قسم الكرتون</option><option value="plastic">🛍️ قسم البلاستيك</option><option value="social">📱 التسويق الإلكتروني</option><option value="web">🌐 المواقع والبرمجة</option><option value="design_only">🎨 قسم التصميم فقط</option></select></div>
                <div id="design_toggle" style="display:none;"><label>حالة التصميم</label><select name="design_status"><option value="needed">🖌️ يحتاج تصميم</option><option value="ready">💾 التصميم جاهز</option></select></div>
            </div>

            <div id="sec_design_only" class="dynamic-section">
                <div class="section-header">🎨 تفاصيل التصميم</div>
                <div class="grid-row"><div><label>عدد البنود</label><input type="number" name="design_items_count" value="1"></div></div>
            </div>

            <div id="sec_print" class="dynamic-section">
                <div class="section-header">📋 مواصفات الطباعة</div>
                <div class="grid-row"><div><label>الكمية</label><input type="number" step="any" name="print_quantity"></div></div>
                <div class="grid-row">
                    <div><label>الورق</label><select name="paper_type" id="paper_type" onchange="toggleOtherPaper('print')"><option value="كوشيه">كوشيه</option><option value="دوبلكس">دوبلكس</option><option value="برستول">برستول</option><option value="طبع">طبع</option><option value="other">أخرى...</option></select><input type="text" name="paper_type_other" id="paper_type_other" style="display:none; margin-top:5px;"></div>
                    <div><label>الوزن</label><input type="number" step="any" name="paper_weight"></div>
                    <div><label>الألوان</label><input type="text" name="print_colors"></div>
                </div>
                <div class="grid-row"><div><label>المقاس (سم)</label><div style="display:flex; gap:5px;"><input placeholder="عرض" name="paper_w"><input placeholder="طول" name="paper_h"></div></div><div><label>القص (سم)</label><div style="display:flex; gap:5px;"><input placeholder="عرض" name="cut_w"><input placeholder="طول" name="cut_h"></div></div></div>
                <div class="grid-row"><div><label>طريقة الطباعة</label><select name="print_mode"><option value="وجه واحد">وجه واحد</option><option value="وجهين">وجهين</option><option value="طبع وقلب بنسة">طبع وقلب بنسة</option><option value="طبع وقلب ديل">طبع وقلب ديل</option></select></div><div><label>عدد الزنكات</label><input type="number" step="any" name="zinc_count"></div><div><label>حالتها</label><select name="zinc_status"><option>جديدة</option><option>مستخدمة</option></select></div></div>
                <label>تكميلي:</label>
                <div class="checkbox-group"><label class="cb-label"><input type="checkbox" name="print_finish[]" value="سلفان لامع"> سلفان لامع</label><label class="cb-label"><input type="checkbox" name="print_finish[]" value="سلفان مط"> سلفان مط</label><label class="cb-label"><input type="checkbox" name="print_finish[]" value="سبوت يوفي"> سبوت يوفي</label><label class="cb-label"><input type="checkbox" name="print_finish[]" value="تكسير"> تكسير</label><label class="cb-label"><input type="checkbox" name="print_finish[]" value="لصق"> لصق</label></div>
            </div>

            <div id="sec_carton" class="dynamic-section">
                <div class="section-header">📦 مواصفات الكرتون</div>
                <div class="grid-row"><div><label>الكمية</label><input type="number" step="any" name="carton_quantity"></div></div>
                <div class="grid-row"><div><label>الخامة الخارجية</label><select name="carton_paper_type" id="carton_paper_type" onchange="toggleOtherPaper('carton')"><option value="كوشيه">كوشيه</option><option value="دوبلكس">دوبلكس</option><option value="كرافت">كرافت</option><option value="other">أخرى...</option></select><input type="text" name="carton_paper_other" id="carton_paper_other" style="display:none; margin-top:5px;"></div><div><label>طبقات</label><input type="number" name="carton_layers"></div></div>
                <label>تفاصيل الطبقات:</label><textarea name="carton_details"></textarea>
                <div class="grid-row" style="margin-top:10px;"><div><label>القص النهائي</label><div style="display:flex; gap:5px;"><input placeholder="عرض" name="carton_cut_w"><input placeholder="طول" name="carton_cut_h"></div></div><div><label>الزنكات</label><input type="number" step="any" name="carton_zinc_count"></div><div><label>حالتها</label><select name="carton_zinc_status"><option>جديدة</option><option>مستخدمة</option></select></div></div>
                <div class="checkbox-group"><label class="cb-label"><input type="checkbox" name="carton_finish[]" value="سلفان"> سلفان</label><label class="cb-label"><input type="checkbox" name="carton_finish[]" value="بصمة"> بصمة</label><label class="cb-label"><input type="checkbox" name="carton_finish[]" value="تكسير"> تكسير</label></div>
            </div>

            <div id="sec_plastic" class="dynamic-section">
                <div class="section-header">🛍️ مواصفات البلاستيك</div>
                <div class="grid-row"><div><label>الكمية</label><input type="number" step="any" name="plastic_quantity"></div></div>
                <div class="grid-row"><div><label>الخامة</label><select name="plastic_material"><option value="HDPE">هاي</option><option value="LDPE">لو</option><option value="PP">PP</option></select></div><div><label>السمك</label><input type="number" step="any" name="plastic_microns"></div><div><label>عرض الفيلم</label><input type="text" name="film_width"></div></div>
                <div class="grid-row"><div><label>المعالجة</label><select name="plastic_treatment"><option value="بدون">بدون</option><option value="وجه واحد">وجه واحد</option><option value="وجهين">وجهين</option></select></div><div><label>طول القص</label><input type="text" name="plastic_cut_len"></div></div>
                <div class="grid-row"><div><label>السلندرات</label><input type="number" step="any" name="cylinder_count"></div><div><label>حالتها</label><select name="cylinder_status"><option>جديدة</option><option>مستخدمة</option></select></div></div>
            </div>

            <div id="sec_social" class="dynamic-section">
                <div class="section-header">📱 تسويق إلكتروني</div>
                <div class="grid-row"><div><label>الهدف</label><select name="campaign_goal"><option value="Awareness">وعي (Awareness)</option><option value="Engagement">تفاعل (Engagement)</option><option value="Traffic">زيارات (Traffic)</option><option value="Leads">بيانات (Leads)</option><option value="Sales">مبيعات (Sales)</option></select></div><div><label>عدد البوستات</label><input type="number" name="social_items_count" value="4"></div></div>
                <label>المنصات:</label><div class="checkbox-group"><label class="cb-label"><input type="checkbox" name="social_platforms[]" value="Facebook"> فيسبوك</label><label class="cb-label"><input type="checkbox" name="social_platforms[]" value="Instagram"> انستجرام</label><label class="cb-label"><input type="checkbox" name="social_platforms[]" value="TikTok"> تيك توك</label><label class="cb-label"><input type="checkbox" name="social_platforms[]" value="Snapchat"> سناب</label><label class="cb-label"><input type="checkbox" name="social_platforms[]" value="Google"> جوجل</label></div>
                <div class="grid-row" style="margin-top:15px;"><div><label>الجمهور</label><input type="text" name="target_audience"></div><div><label>الميزانية</label><input type="text" name="ad_budget"></div></div>
            </div>

            <div id="sec_web" class="dynamic-section">
                <div class="section-header">🌐 مواقع إلكترونية</div>
                <div class="grid-row"><div><label>النوع</label><select name="web_type"><option value="تعريفي">تعريفي</option><option value="متجر">متجر</option><option value="تطبيق">تطبيق</option></select></div><div><label>الدومين</label><input type="text" name="web_domain"></div><div><label>الاستضافة</label><input type="text" name="web_hosting"></div></div>
                <label>الثيم</label><textarea name="web_theme" rows="2"></textarea>
            </div>

            <div class="section-header"><i class="fa-solid fa-paperclip"></i> 4. مرفقات وملاحظات</div>
            <div class="grid-row"><div><label>ملفات مساعدة (JPG, PNG, PDF, ZIP)</label><input type="file" name="attachment[]" multiple></div></div>
            <label>ملاحظات</label><textarea name="notes" rows="3"></textarea>
            <button type="submit" name="save_job" class="btn-royal">🚀 إطلاق أمر الشغل</button>
        </form>
    </div>
</div>
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
        var id = section === 'print' ? 'paper_type' : 'carton_paper_type';
        var otherId = section === 'print' ? 'paper_type_other' : 'carton_paper_other';
        document.getElementById(otherId).style.display = (document.getElementById(id).value === 'other') ? 'block' : 'none';
    }
</script>
<?php include 'footer.php'; ob_end_flush(); ?>
