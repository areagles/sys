<?php
// modules/web.php - (Royal Web Manager V44.0 - Mobile & Full Control)

// 0. إعدادات النظام
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. دالة التوجيه
function safe_redirect($id) {
    echo "<script>window.location.href = 'job_details.php?id=$id';</script>";
    exit;
}

// 2. استخراج البيانات الفنية للعرض
$raw_text = $job['job_details'] ?? '';
function get_spec($pattern, $text) {
    preg_match($pattern, $text, $matches);
    return isset($matches[1]) ? trim($matches[1]) : '-';
}

$specs = [
    'type'    => get_spec('/(?:نوع الموقع|المشروع):\s*(.*)/u', $raw_text),
    'domain'  => get_spec('/(?:الدومين|النطاق):\s*(.*)/u', $raw_text),
    'hosting' => get_spec('/(?:الاستضافة|Hosting):\s*(.*)/u', $raw_text),
    'theme'   => get_spec('/(?:الثيم|Theme):\s*(.*)/u', $raw_text),
];

// 3. معالجة الطلبات (Controller Logic)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_name = $_SESSION['name'] ?? 'Developer';

    // === أدوات التحكم الجديدة (التعليقات، الحذف، التمرير الجبري) ===

    // 1. إضافة تعليق داخلي
    if (isset($_POST['add_internal_comment'])) {
        if(!empty($_POST['comment_text'])) {
            $c_text = $conn->real_escape_string($_POST['comment_text']);
            $timestamp = date('Y-m-d H:i');
            $new_note = "\n[💬 $user_name ($timestamp)]: $c_text";
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '$new_note') WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    // 2. التحكم الجبري بالمراحل (Force Stage)
    if (isset($_POST['force_stage_change'])) {
        $target_stage = $_POST['target_stage'];
        $conn->query("UPDATE job_orders SET current_stage='$target_stage' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // 3. حذف الملفات (شامل: من الاستضافة وقاعدة البيانات)
    if (isset($_POST['delete_item'])) {
        $tbl = ($_POST['type'] == 'proof') ? 'job_proofs' : 'job_files';
        $id = intval($_POST['item_id']);
        
        // جلب المسار لحذفه من السيرفر
        $q = $conn->query("SELECT file_path FROM $tbl WHERE id=$id");
        if ($r = $q->fetch_assoc()) { 
            if(file_exists($r['file_path'])) { unlink($r['file_path']); } // حذف فعلي من الاستضافة
        }
        
        // حذف من قاعدة البيانات
        $conn->query("DELETE FROM $tbl WHERE id=$id");
        safe_redirect($job['id']);
    }
    // === نهاية الأدوات الجديدة ===

    // A. مرحلة التحليل (Briefing)
    if (isset($_POST['save_brief'])) {
        $reqs = $conn->real_escape_string($_POST['requirements']);
        $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[💻 متطلبات]: $reqs') WHERE id={$job['id']}");
        
        if (!empty($_FILES['doc_files']['name'][0])) {
            if (!file_exists('uploads/briefs')) @mkdir('uploads/briefs', 0777, true);
            foreach ($_FILES['doc_files']['name'] as $i => $name) {
                if ($_FILES['doc_files']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/briefs/" . time() . "_web_$i.$ext";
                    if(move_uploaded_file($_FILES['doc_files']['tmp_name'][$i], $target)){
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ({$job['id']}, '$target', 'briefing', 'وثائق المشروع', '$user_name')");
                    }
                }
            }
        }
        $conn->query("UPDATE job_orders SET current_stage='ui_design' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // B. تصميم الواجهة (UI/UX)
    if (isset($_POST['action_ui'])) {
        // 1. رفع الملفات أولاً (إذا وجدت)
        if (!empty($_FILES['ui_files']['name'][0])) {
            if (!file_exists('uploads/proofs')) @mkdir('uploads/proofs', 0777, true);
            foreach ($_FILES['ui_files']['name'] as $i => $name) {
                if ($_FILES['ui_files']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/proofs/" . time() . "_ui_$i.$ext";
                    if(move_uploaded_file($_FILES['ui_files']['tmp_name'][$i], $target)){
                        $conn->query("INSERT INTO job_proofs (job_id, file_path, description, status) VALUES ({$job['id']}, '$target', 'تصميم واجهة', 'pending')");
                    }
                }
            }
        }

        // 2. التحقق من الزر المضغوط
        if ($_POST['action_ui'] === 'send_review') {
            // الانتقال للمرحلة التالية
            $conn->query("UPDATE job_orders SET current_stage='client_rev' WHERE id={$job['id']}");
        }
        
        safe_redirect($job['id']);
    }

    // C. مراجعة العميل
    if (isset($_POST['approve_ui'])) {
        $conn->query("UPDATE job_orders SET current_stage='development' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // D. البرمجة (Development)
    if (isset($_POST['save_dev_progress'])) {
        $dev_url = $conn->real_escape_string($_POST['dev_url']);
        if(!empty($dev_url)) {
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[رابط المعاينة]: $dev_url') WHERE id={$job['id']}");
        }
        
        if($_POST['save_dev_progress'] === 'finish') {
             $conn->query("UPDATE job_orders SET current_stage='testing' WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    // E. الاختبار (Testing)
    if (isset($_POST['finish_testing'])) {
        $conn->query("UPDATE job_orders SET current_stage='launch' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // F. الإطلاق
    if (isset($_POST['finish_launch'])) {
        $chk = $conn->query("SELECT id FROM invoices WHERE job_id={$job['id']}");
        if($chk->num_rows == 0) {
            $client_id = $job['client_id']; $price = $job['price'] ?? 0;
            $conn->query("INSERT INTO invoices (client_id, job_id, total_amount, remaining_amount, inv_date, status) VALUES ($client_id, {$job['id']}, $price, $price, NOW(), 'unpaid')");
        }
        $conn->query("UPDATE job_orders SET current_stage='accounting' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // G. الأرشيف وإعادة الفتح
    if (isset($_POST['archive_job'])) { 
        $conn->query("UPDATE job_orders SET current_stage='completed' WHERE id={$job['id']}"); 
        safe_redirect($job['id']); 
    }
    if (isset($_POST['reopen_job'])) { 
        $conn->query("UPDATE job_orders SET current_stage='briefing' WHERE id={$job['id']}"); 
        safe_redirect($job['id']); 
    }

    // خدمات عامة (تراجع)
    if (isset($_POST['return_stage'])) {
        $prev = $_POST['prev_target'];
        $reason = $conn->real_escape_string($_POST['return_reason']);
        $note = "\n[⚠️ تراجع]: $reason";
        $conn->query("UPDATE job_orders SET current_stage='$prev', notes = CONCAT(IFNULL(notes, ''), '$note') WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }
}

// 4. خريطة المراحل
$workflow = [
    'briefing'    => '1. التحليل',
    'ui_design'   => '2. تصميم UI',
    'client_rev'  => '3. مراجعة UI',
    'development' => '4. البرمجة',
    'testing'     => '5. الاختبار',
    'launch'      => '6. الإطلاق',
    'accounting'  => '7. الحسابات',
    'completed'   => '8. الأرشيف'
];
$curr = $job['current_stage'];
if(!array_key_exists($curr, $workflow)) $curr = 'briefing';

// تحديد المراحل السابقة والتالية
$keys = array_keys($workflow);
$curr_idx = array_search($curr, $keys);
$prev_stage = ($curr_idx > 0) ? $keys[$curr_idx - 1] : null;
$next_stage = ($curr_idx < count($keys) - 1) ? $keys[$curr_idx + 1] : null;

// رابط العميل
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = str_replace('/modules', '', $base_url); 
$client_link = $base_url . "/client_review.php?token=" . $job['access_token'];

// جلب جميع الملفات للشريط الجانبي
$all_files = $conn->query("SELECT * FROM job_files WHERE job_id={$job['id']} ORDER BY id DESC");
?>

<style>
    :root { --w-gold: #f39c12; --w-bg: #121212; --w-card: #1e1e1e; --w-blue: #3498db; --w-green: #2ecc71; --w-red: #e74c3c; }
    
    /* Responsive Split Layout */
    .split-layout { display: flex; gap: 20px; align-items: flex-start; }
    .sidebar { width: 320px; flex-shrink: 0; background: #151515; border: 1px solid #333; border-radius: 12px; padding: 20px; position: sticky; top: 20px; max-height: 90vh; overflow-y: auto; }
    .main-content { flex: 1; min-width: 0; }
    
    /* Mobile Logic */
    @media (max-width: 900px) { 
        .split-layout { flex-direction: column; } 
        .sidebar { width: 100%; order: 2; position: static; max-height: none; } 
        .main-content { width: 100%; order: 1; margin-bottom: 20px; }
    }

    /* Sidebar Items */
    .info-block { margin-bottom: 20px; border-bottom: 1px dashed #333; padding-bottom: 15px; }
    .info-label { color: var(--w-gold); font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block; }
    .info-value { color: #ddd; font-size: 0.95rem; white-space: pre-wrap; line-height: 1.6; background: #0a0a0a; padding: 10px; border-radius: 6px; border: 1px solid #222; }

    /* File List in Sidebar */
    .file-item { display: flex; align-items: center; gap: 10px; background: #0a0a0a; padding: 8px; margin-bottom: 5px; border-radius: 6px; border: 1px solid #333; transition: 0.2s; }
    .file-item:hover { border-color: var(--w-gold); }
    .file-icon { font-size: 1.2rem; color: #777; }
    .file-link { flex: 1; color: #fff; text-decoration: none; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .file-tag { font-size: 0.7rem; background: #333; padding: 2px 6px; border-radius: 4px; color: #aaa; }
    
    /* Delete Button */
    .delete-btn { background: none; border: none; color: var(--w-red); cursor: pointer; padding: 0 5px; font-size: 1.1rem; transition: 0.2s; }
    .delete-btn:hover { transform: scale(1.1); }

    /* Internal Comments */
    .comments-box { background: #000; padding: 10px; border-radius: 6px; max-height: 200px; overflow-y: auto; font-size: 0.85rem; border: 1px solid #333; margin-bottom: 10px; }
    .comment-input { width: 100%; background: #222; border: 1px solid #444; padding: 8px; color: #fff; border-radius: 4px; margin-bottom: 5px; }

    /* Admin Controls */
    .admin-controls { display: flex; gap: 5px; margin-top: 10px; background: #222; padding: 5px; border-radius: 5px; }

    /* General UI */
    .stage-header { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; border-bottom: 1px solid #333; }
    .step-badge { background: #333; color: #777; padding: 5px 15px; border-radius: 20px; white-space: nowrap; font-size: 0.8rem; }
    .step-badge.active { background: var(--w-gold); color: #000; font-weight: bold; }
    
    .main-card { background: var(--w-card); padding: 25px; border-radius: 12px; border: 1px solid #333; margin-bottom: 20px; }
    .card-title { color: var(--w-gold); margin: 0 0 15px 0; border-bottom: 1px dashed #444; padding-bottom: 10px; font-size: 1.2rem; }
    
    .btn { width: 100%; padding: 12px; border: none; border-radius: 5px; cursor: pointer; color: #fff; font-weight: bold; margin-top: 10px; }
    .btn-gold { background: var(--w-gold); color: #000; }
    .btn-blue { background: var(--w-blue); }
    .btn-green { background: var(--w-green); }
    .btn-red { background: var(--w-red); }
    .btn-gray { background: #444; }
    .btn-sm { padding: 5px 10px; font-size: 0.8rem; width: auto; margin-top: 0; }
    
    .tech-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .tech-item { background: #151515; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #333; }
    .tech-label { display: block; font-size: 0.75rem; color: #888; margin-bottom: 5px; }
    .tech-val { display: block; font-size: 1rem; color: #fff; font-weight: bold; }
    
    textarea, input[type="text"] { width: 100%; background: #151515; border: 1px solid #444; color: #fff; padding: 12px; border-radius: 6px; box-sizing: border-box; }
    
    .checklist-item { display: flex; align-items: center; gap: 10px; background: #000; padding: 10px; margin-bottom: 5px; border-radius: 5px; }
    input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--w-green); }
    
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); align-items: center; justify-content: center; z-index: 1000; }
    .modal-box { background: #222; padding: 20px; width: 90%; max-width: 350px; border-radius: 10px; border: 1px solid #555; text-align: center; }
</style>

<div class="container split-layout">
    
    <div class="sidebar">
        <h3 style="color:#fff; border-bottom:2px solid var(--w-gold); padding-bottom:10px; margin-top:0;">📂 ملف المشروع</h3>
        
        <div class="info-block">
            <span class="info-label">📝 البيانات الأساسية:</span>
            <div class="info-value">
                <strong>المشروع:</strong> <?php echo $job['job_name']; ?><br>
                <strong>النوع:</strong> <?php echo $specs['type']; ?><br>
                <strong>الثيم:</strong> <?php echo $specs['theme']; ?>
            </div>
        </div>

        <div class="info-block">
            <span class="info-label">💬 المناقشات الداخلية:</span>
            <div class="comments-box">
                <?php echo nl2br($job['notes'] ?? 'لا توجد ملاحظات'); ?>
            </div>
            <form method="POST">
                <input type="text" name="comment_text" class="comment-input" placeholder="اكتب ملاحظة..." required>
                <button type="submit" name="add_internal_comment" class="btn btn-gray btn-sm" style="width:100%;">إرسال تعليق</button>
            </form>
        </div>

        <div class="info-block" style="border:none;">
            <span class="info-label">📎 الأرشيف والمرفقات:</span>
            <?php if($all_files->num_rows > 0): ?>
                <?php while($f = $all_files->fetch_assoc()): 
                    $ext = pathinfo($f['file_path'], PATHINFO_EXTENSION);
                    $icon = in_array(strtolower($ext), ['jpg','png','jpeg','webp']) ? '🖼️' : '📄';
                ?>
                <div class="file-item">
                    <span class="file-icon"><?php echo $icon; ?></span>
                    <a href="<?php echo $f['file_path']; ?>" target="_blank" class="file-link"><?php echo $f['description'] ?: basename($f['file_path']); ?></a>
                    <span class="file-tag"><?php echo $f['stage']; ?></span>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="type" value="file">
                        <input type="hidden" name="item_id" value="<?php echo $f['id']; ?>">
                        <button name="delete_item" class="delete-btn" onclick="return confirm('حذف الملف نهائياً من السيرفر؟')">×</button>
                    </form>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="color:#666; font-size:0.9rem; text-align:center;">لا توجد مرفقات</div>
            <?php endif; ?>
        </div>

        <div class="info-block" style="border-top:1px dashed #333; padding-top:15px;">
            <span class="info-label">⚙️ تحكم إداري (تجاوز):</span>
            <div class="admin-controls">
                <?php if($prev_stage): ?>
                <form method="POST" style="flex:1;"><input type="hidden" name="target_stage" value="<?php echo $prev_stage; ?>"><button name="force_stage_change" class="btn btn-red btn-sm" style="width:100%;">« تراجع جبري</button></form>
                <?php endif; ?>
                <?php if($next_stage): ?>
                <form method="POST" style="flex:1;"><input type="hidden" name="target_stage" value="<?php echo $next_stage; ?>"><button name="force_stage_change" class="btn btn-gold btn-sm" style="width:100%;">تمرير جبري »</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="main-content">
        
        <div class="stage-header">
            <?php foreach($workflow as $key => $label): ?>
                <div class="step-badge <?php echo ($key == $curr) ? 'active' : ''; ?>"><?php echo $label; ?></div>
            <?php endforeach; ?>
        </div>

        <div class="main-card" style="border-top: 3px solid var(--w-gold);">
            <h3 class="card-title">🌐 <?php echo $job['job_name']; ?></h3>
            <div class="tech-grid">
                <div class="tech-item"><span class="tech-label">نوع المشروع</span><span class="tech-val"><?php echo $specs['type']; ?></span></div>
                <div class="tech-item"><span class="tech-label">النطاق (Domain)</span><a href="https://<?php echo $specs['domain']; ?>" target="_blank" class="tech-val" style="color:var(--w-blue); text-decoration:none;"><?php echo $specs['domain']; ?></a></div>
                <div class="tech-item"><span class="tech-label">الاستضافة</span><span class="tech-val"><?php echo $specs['hosting']; ?></span></div>
            </div>
        </div>

        <?php if($curr == 'briefing'): ?>
        <div class="main-card">
            <h3 class="card-title">📝 تحليل المتطلبات (Requirements)</h3>
            <form method="POST" enctype="multipart/form-data">
                <label style="color:#aaa;">المتطلبات التقنية والمميزات:</label>
                <textarea name="requirements" rows="6" placeholder="اكتب قائمة المميزات، الصفحات المطلوبة، اللغات، طرق الدفع..."><?php echo $job['notes']; ?></textarea>
                
                <div style="margin-top:15px;">
                    <label style="color:#aaa;">ملفات التخطيط (Sitemap / Wireframes):</label>
                    <input type="file" name="doc_files[]" multiple style="color:#fff; display:block; margin-top:5px;">
                </div>
                
                <button type="submit" name="save_brief" class="btn btn-gold">حفظ وبدء التصميم (UI) ➡️</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'ui_design'): ?>
        <div class="main-card">
            <h3 class="card-title">🎨 تصميم الواجهة (UI/UX)</h3>
            <form method="POST" enctype="multipart/form-data">
                <label style="color:#aaa;">رفع شاشات التصميم (XD/Figma/Images):</label>
                <input type="file" name="ui_files[]" multiple style="color:#fff; display:block; margin-top:10px; margin-bottom:10px;">
                
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="action_ui" value="upload" class="btn btn-gray">📤 رفع الملفات فقط</button>
                    <button type="submit" name="action_ui" value="send_review" class="btn btn-gold">رفع وإرسال للمراجعة ➡️</button>
                </div>
            </form>
            
            <h4 style="color:#fff; margin-top:20px;">البروفات الحالية:</h4>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
                <?php 
                $proofs = $conn->query("SELECT * FROM job_proofs WHERE job_id={$job['id']}");
                while($p = $proofs->fetch_assoc()): ?>
                    <div style="text-align:center;">
                        <a href="<?php echo $p['file_path']; ?>" target="_blank">
                            <img src="<?php echo $p['file_path']; ?>" style="width:100px; height:100px; object-fit:cover; border:1px solid #444; border-radius:5px;">
                        </a>
                        <form method="POST" onsubmit="return confirm('حذف التصميم؟');" style="margin-top:5px;">
                            <input type="hidden" name="type" value="proof">
                            <input type="hidden" name="item_id" value="<?php echo $p['id']; ?>">
                            <button name="delete_item" style="background:none; border:none; color:var(--w-red); cursor:pointer;">🗑️ حذف</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($curr == 'client_rev'): ?>
        <div class="main-card" style="text-align:center;">
            <h3 class="card-title">⏳ انتظار مراجعة العميل (UI)</h3>
            <p style="color:#aaa;">العميل يراجع التصميمات حالياً.</p>
            <div style="background:#000; padding:10px; border-radius:5px; margin-bottom:15px;">
                <input type="text" value="<?php echo $client_link; ?>" readonly style="width:100%; background:#000; color:var(--w-green); text-align:center; border:none;">
            </div>
            <a href="https://wa.me/<?php echo $job['client_phone']; ?>?text=<?php echo urlencode("يرجى مراجعة تصميم الموقع:\n$client_link"); ?>" target="_blank" class="btn btn-green" style="display:inline-block; width:auto; text-decoration:none;">📱 إرسال واتساب</a>
            
            <form method="POST" style="margin-top:20px;">
                <button name="approve_ui" class="btn btn-gold">✅ تم الاعتماد (تخطي يدوي للبرمجة)</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'development'): ?>
        <div class="main-card">
            <h3 class="card-title">💻 مرحلة التكويد (Development)</h3>
            <form method="POST">
                <label style="color:#aaa;">رابط بيئة التطوير (Staging URL):</label>
                <input type="text" name="dev_url" placeholder="http://dev.yoursite.com" style="direction:ltr;">
                
                <label style="color:#aaa; margin-top:10px; display:block;">ملاحظات / بيانات دخول لوحة التحكم:</label>
                <textarea name="dev_notes" rows="3" placeholder="Admin URL / Username / Password..."></textarea>
                
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="submit" name="save_dev_progress" value="save" class="btn btn-gray">💾 حفظ التقدم</button>
                    <button type="submit" name="save_dev_progress" value="finish" class="btn btn-gold">✅ انتهاء التكويد (للاختبار) ➡️</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'testing'): ?>
        <div class="main-card">
            <h3 class="card-title">🧪 اختبار الجودة (QA Testing)</h3>
            <div style="background:#111; padding:15px; border-radius:8px; margin-bottom:15px;">
                <div class="checklist-item"><input type="checkbox"> <span>التوافق مع الجوال (Responsive)</span></div>
                <div class="checklist-item"><input type="checkbox"> <span>سرعة التحميل (Speed Test)</span></div>
                <div class="checklist-item"><input type="checkbox"> <span>عمل النماذج (Forms)</span></div>
            </div>
            <form method="POST">
                <button name="finish_testing" class="btn btn-gold">✅ الموقع جاهز للإطلاق ➡️</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'launch'): ?>
        <div class="main-card">
            <h3 class="card-title">🚀 الإطلاق الرسمي (Go Live)</h3>
            <p style="color:#aaa;">تأكد من ربط الدومين الأساسي ونقل الملفات للسيرفر الحي.</p>
            
            <div style="text-align:center; margin:20px 0;">
                <a href="https://<?php echo $specs['domain']; ?>" target="_blank" class="btn btn-blue" style="text-decoration:none; display:inline-block; width:auto; padding:15px 40px;">🌐 زيارة الموقع الحي</a>
            </div>
            
            <form method="POST">
                <button name="finish_launch" class="btn btn-gold">✅ تأكيد التسليم والتحويل للحسابات</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if(in_array($curr, ['accounting', 'completed'])): ?>
        <div class="main-card" style="text-align:center;">
            <h2 style="color:var(--w-green);">✅ تم تسليم المشروع بنجاح</h2>
            <?php if($curr == 'accounting'): ?>
                <a href="invoices.php" class="btn btn-gray" style="display:inline-block; width:auto;">الملف المالي</a>
                <form method="POST" style="margin-top:15px;">
                    <button name="archive_job" class="btn btn-gold" style="width:auto;">أرشفة نهائية</button>
                </form>
            <?php else: ?>
                <p style="color:#aaa;">المشروع في الأرشيف.</p>
                <form method="POST" onsubmit="return confirm('تأكيد إعادة الفتح؟');" style="margin-top:20px;">
                    <button name="reopen_job" class="btn btn-red" style="width:auto;">🔄 إعادة فتح المشروع</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($prev_stage && !in_array($curr, ['completed'])): ?>
        <div style="text-align:right; margin-top:20px;">
            <button onclick="document.getElementById('returnModal').style.display='flex'" class="btn btn-red" style="width:auto; padding:8px 20px; font-size:0.8rem;">↩️ تراجع للمرحلة السابقة</button>
        </div>
        <?php endif; ?>

    </div>

</div>

<div id="returnModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="color:var(--w-red); margin-top:0;">⚠️ تأكيد التراجع</h3>
        <form method="POST">
            <input type="hidden" name="prev_target" value="<?php echo $prev_stage; ?>">
            <textarea name="return_reason" required placeholder="سبب التراجع..." style="width:100%; height:80px; background:#000; color:#fff; border:1px solid #555; margin-bottom:10px;"></textarea>
            <div style="display:flex; gap:10px;">
                <button name="return_stage" class="btn btn-red" style="flex:1;">تأكيد</button>
                <button type="button" onclick="document.getElementById('returnModal').style.display='none'" class="btn btn-gray" style="flex:1;">إلغاء</button>
            </div>
        </form>
    </div>
</div>