<?php
// modules/carton.php - (Royal Carton Master V30.1 - Bug Fixes)

// 0. تفعيل الأخطاء
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. إصلاح الجداول
$cols_to_check = ['job_files' => 'description', 'job_proofs' => 'description'];
foreach($cols_to_check as $tbl => $col) {
    $check = $conn->query("SHOW COLUMNS FROM $tbl LIKE '$col'");
    if($check->num_rows == 0) { $conn->query("ALTER TABLE $tbl ADD COLUMN $col TEXT DEFAULT NULL"); }
}

// 2. دالة التوجيه
function safe_redirect($id) {
    echo "<script>window.location.href = 'job_details.php?id=$id';</script>";
    exit;
}

// دالة الواتساب الذكية
function get_wa_link($phone, $text) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11 && substr($phone, 0, 2) == '01') { $phone = '2' . $phone; }
    elseif (strlen($phone) == 10 && substr($phone, 0, 2) == '05') { $phone = '966' . substr($phone, 1); }
    if (strlen($phone) < 10) return false;
    return "https://wa.me/$phone?text=" . urlencode($text);
}

// 3. استخراج البيانات الفنية
$raw_text = $job['job_details'] ?? '';
function get_spec($pattern, $text, $default = '-') {
    if(empty($text)) return $default;
    preg_match($pattern, $text, $matches);
    return isset($matches[1]) ? trim($matches[1]) : $default;
}

$specs = [
    'mat'       => get_spec('/الخامة الخارجية:\s*(.*)/u', $raw_text, ''),
    'layers'    => get_spec('/عدد الطبقات:\s*(.*)/u', $raw_text, ''),
    'cut'       => get_spec('/مقاس القص:\s*(.*)/u', $raw_text, ''),
    'die'       => get_spec('/رقم الفورمة:\s*(.*)/u', $raw_text, ''),
    'colors'    => get_spec('/الألوان:\s*(.*)/u', $raw_text, ''),
];

// الصلاحيات المالية
$user_role = $_SESSION['role'] ?? '';
$is_financial = in_array($user_role, ['admin', 'manager', 'accountant']);

// 4. معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_name = $_SESSION['name'] ?? 'Officer';

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

    // 2. التحكم الجبري بالمراحل
    if (isset($_POST['force_stage_change'])) {
        $target_stage = $_POST['target_stage'];
        $conn->query("UPDATE job_orders SET current_stage='$target_stage' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // 3. حذف الملفات
    if (isset($_POST['delete_item'])) {
        $tbl = ($_POST['type'] == 'proof') ? 'job_proofs' : 'job_files';
        $id = intval($_POST['item_id']);
        $q = $conn->query("SELECT file_path FROM $tbl WHERE id=$id");
        if ($r = $q->fetch_assoc()) { 
            if(file_exists($r['file_path'])) { unlink($r['file_path']); } 
        }
        $conn->query("DELETE FROM $tbl WHERE id=$id");
        safe_redirect($job['id']);
    }

    // A. التجهيز
    if (isset($_POST['save_brief'])) {
        if (!empty($_POST['notes'])) {
            $note = $conn->real_escape_string($_POST['notes']);
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[📝 تجهيز]: $note') WHERE id={$job['id']}");
        }
        $brief_descs = $_POST['brief_desc'] ?? [];
        if (isset($_FILES['brief_file']) && !empty($_FILES['brief_file']['name'][0])) {
            if (!file_exists('uploads/briefs')) @mkdir('uploads/briefs', 0777, true);
            foreach ($_FILES['brief_file']['name'] as $i => $name) {
                if ($_FILES['brief_file']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/briefs/" . time() . "_{$job['id']}_$i.$ext";
                    if (move_uploaded_file($_FILES['brief_file']['tmp_name'][$i], $target)) {
                        $desc = $conn->real_escape_string($brief_descs[$i] ?? 'ملف تجهيز');
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ({$job['id']}, '$target', 'briefing', '$desc', '$user_name')");
                    }
                }
            }
        }
        $conn->query("UPDATE job_orders SET current_stage='design' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // B. التصميم
    if (isset($_POST['upload_proof'])) {
        if (!empty($_FILES['proof_file']['name'])) {
            if (!file_exists('uploads/proofs')) @mkdir('uploads/proofs', 0777, true);
            $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
            $target = "uploads/proofs/" . time() . "_proof.$ext";
            $desc = $conn->real_escape_string($_POST['proof_desc'] ?? 'بروفة');
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $target)) {
                $conn->query("INSERT INTO job_proofs (job_id, file_path, description, status) VALUES ({$job['id']}, '$target', '$desc', 'pending')");
            }
        }
        safe_redirect($job['id']);
    }
    if (isset($_POST['send_to_review'])) {
        $conn->query("UPDATE job_orders SET current_stage='client_rev' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // C. المصادقة
    if (isset($_POST['finalize_review'])) {
        if (!empty($_FILES['source_files']['name'][0])) {
            if (!file_exists('uploads/source')) @mkdir('uploads/source', 0777, true);
            foreach ($_FILES['source_files']['name'] as $i => $name) {
                if ($_FILES['source_files']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/source/" . time() . "_src_$i.$ext";
                    if(move_uploaded_file($_FILES['source_files']['tmp_name'][$i], $target)){
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ({$job['id']}, '$target', 'pre_press', 'Source File (تكسير/طباعة)', '$user_name')");
                    }
                }
            }
        }
        $conn->query("UPDATE job_orders SET current_stage='pre_press' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // D. التوريدات
    if (isset($_POST['save_materials']) || isset($_POST['finish_materials'])) {
        $items = $_POST['item_text'] ?? [];
        $suppliers = $_POST['supplier_phone'] ?? [];
        
        if (is_array($items)) {
            if (!file_exists('uploads/materials')) @mkdir('uploads/materials', 0777, true);
            foreach ($items as $i => $text) {
                if (!empty($text)) {
                    $file_link = '';
                    if (!empty($_FILES['item_file']['name'][$i])) {
                        $ext = pathinfo($_FILES['item_file']['name'][$i], PATHINFO_EXTENSION);
                        $target = "uploads/materials/" . time() . "_mat_$i.$ext";
                        if(move_uploaded_file($_FILES['item_file']['tmp_name'][$i], $target)) $file_link = $target;
                    }
                    $desc = $conn->real_escape_string($text);
                    $supp = $conn->real_escape_string($suppliers[$i]??'');
                    $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ({$job['id']}, '$file_link', 'materials', '$desc', '$supp')");
                }
            }
        }
        if (isset($_POST['finish_materials'])) {
            $conn->query("UPDATE job_orders SET current_stage='printing' WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    // E. الطباعة
    if (isset($_POST['save_print_specs'])) {
        $colors = $conn->real_escape_string($_POST['colors']);
        $safe_log = "الألوان: $colors | الخامة: {$specs['mat']} | الطبقات: {$specs['layers']}";
        $conn->query("UPDATE job_orders SET job_details = CONCAT(IFNULL(job_details,''), '\n$safe_log') WHERE id={$job['id']}");
        
        if(!empty($_POST['print_notes'])) {
            $p_note = $conn->real_escape_string($_POST['print_notes']);
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[🖨️ طباعة]: $p_note') WHERE id={$job['id']}");
        }
        $conn->query("UPDATE job_orders SET current_stage='die_cutting' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // F. التكسير
    if (isset($_POST['finish_diecut'])) {
        if(!empty($_POST['diecut_notes'])) {
            $d_note = $conn->real_escape_string($_POST['diecut_notes']);
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[✂️ تكسير]: $d_note') WHERE id={$job['id']}");
        }
        $conn->query("UPDATE job_orders SET current_stage='gluing' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // G. اللصق
    if (isset($_POST['finish_gluing'])) {
        if(!empty($_POST['gluing_notes'])) {
            $g_note = $conn->real_escape_string($_POST['gluing_notes']);
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[🧴 لصق]: $g_note') WHERE id={$job['id']}");
        }
        $conn->query("UPDATE job_orders SET current_stage='delivery' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // H. التسليم
    if (isset($_POST['finish_delivery'])) {
        $check_inv = $conn->query("SELECT id FROM invoices WHERE job_id={$job['id']}");
        if($check_inv->num_rows == 0) {
            $client_id = $job['client_id']; $price = $job['price'] ?? 0;
            $conn->query("INSERT INTO invoices (client_id, job_id, total_amount, remaining_amount, inv_date, status) VALUES ($client_id, {$job['id']}, $price, $price, NOW(), 'unpaid')");
        }
        $conn->query("UPDATE job_orders SET current_stage='accounting' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // I. الأرشفة / إعادة الفتح
    if (isset($_POST['archive_job'])) { $conn->query("UPDATE job_orders SET current_stage='completed' WHERE id={$job['id']}"); safe_redirect($job['id']); }
    if (isset($_POST['reopen_job'])) { $conn->query("UPDATE job_orders SET current_stage='briefing' WHERE id={$job['id']}"); safe_redirect($job['id']); }

    // الخدمات العامة (تراجع)
    if (isset($_POST['return_stage'])) {
        $prev = $_POST['prev_target'];
        $reason = $conn->real_escape_string($_POST['return_reason']);
        $note = "\n[⚠️ تراجع]: $reason";
        $conn->query("UPDATE job_orders SET current_stage='$prev', notes = CONCAT(IFNULL(notes, ''), '$note') WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }
}

// 5. خريطة المراحل (تم تصحيح الهيكل)
$workflow = [
    'briefing'    => ['label' => '1. التجهيز'],
    'design'      => ['label' => '2. التصميم'],
    'client_rev'  => ['label' => '3. المصادقة'],
    'pre_press'   => ['label' => '4. التوريدات'],
    'printing'    => ['label' => '5. الطباعة'],
    'die_cutting' => ['label' => '6. التكسير'],
    'gluing'      => ['label' => '7. اللصق'],
    'delivery'    => ['label' => '8. التسليم'],
    'accounting'  => ['label' => '9. الحسابات'],
    'completed'   => ['label' => '10. الأرشيف']
];
$curr = $job['current_stage'];
if(!array_key_exists($curr, $workflow)) $curr = 'briefing';

$keys = array_keys($workflow);
$curr_idx = array_search($curr, $keys);
$prev_stage_key = isset($keys[$curr_idx-1]) ? $keys[$curr_idx-1] : null;
$next_stage_key = isset($keys[$curr_idx+1]) ? $keys[$curr_idx+1] : null;

// [تم الإصلاح] تعريف المتغيرات لتجنب الأخطاء
$prev_stage = $prev_stage_key;
$next_stage = $next_stage_key;

$suppliers_options = "";
$s_res = $conn->query("SELECT * FROM suppliers");
if($s_res) while($r = $s_res->fetch_assoc()) $suppliers_options .= "<option value='{$r['phone']}'>{$r['name']}</option>";

// جلب جميع الملفات
$all_files = $conn->query("SELECT * FROM job_files WHERE job_id={$job['id']} ORDER BY id DESC");
?>

<style>
    :root { --c-gold: #d4af37; --c-bg: #121212; --c-card: #1e1e1e; --c-green: #2ecc71; --c-red: #e74c3c; --c-blue: #3498db; }
    
    /* Responsive Layout */
    .split-layout { display: flex; gap: 20px; align-items: flex-start; }
    .sidebar { width: 300px; flex-shrink: 0; background: #151515; border: 1px solid #333; border-radius: 12px; padding: 20px; position: sticky; top: 20px; max-height: 90vh; overflow-y: auto; }
    .main-content { flex: 1; min-width: 0; }
    
    /* Mobile Logic */
    @media (max-width: 900px) { 
        .split-layout { flex-direction: column; } 
        .sidebar { width: 100%; order: 2; position: static; max-height: none; } 
        .main-content { width: 100%; order: 1; margin-bottom: 20px; }
    }

    /* Sidebar Items */
    .info-block { margin-bottom: 20px; border-bottom: 1px dashed #333; padding-bottom: 15px; }
    .info-label { color: var(--c-gold); font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block; }
    .info-value { color: #ddd; font-size: 0.95rem; white-space: pre-wrap; line-height: 1.6; background: #0a0a0a; padding: 10px; border-radius: 6px; border: 1px solid #222; }

    /* Timeline in Sidebar */
    .timeline { position: relative; padding-right: 20px; border-right: 2px solid #333; }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; right: -26px; top: 5px; width: 10px; height: 10px; background: #555; border-radius: 50%; border: 2px solid #151515; transition: 0.3s; }
    .timeline-item.active::before { background: var(--c-gold); box-shadow: 0 0 10px var(--c-gold); }
    .timeline-item.active .t-title { color: var(--c-gold); font-weight: bold; }
    .t-title { color: #888; font-size: 0.9rem; }

    /* Internal Comments */
    .comments-box { background: #000; padding: 10px; border-radius: 6px; max-height: 200px; overflow-y: auto; font-size: 0.85rem; border: 1px solid #333; margin-bottom: 10px; }
    .comment-input { width: 100%; background: #222; border: 1px solid #444; padding: 8px; color: #fff; border-radius: 4px; margin-bottom: 5px; }

    /* Admin Controls */
    .admin-controls { display: flex; gap: 5px; margin-top: 10px; background: #222; padding: 5px; border-radius: 5px; }

    /* General UI */
    .stage-header { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; border-bottom: 1px solid #333; }
    .step-badge { background: #333; color: #777; padding: 5px 15px; border-radius: 20px; white-space: nowrap; font-size: 0.8rem; }
    .step-badge.active { background: var(--c-gold); color: #000; font-weight: bold; }
    
    .main-card { background: var(--c-card); padding: 25px; border-radius: 12px; border: 1px solid #333; margin-bottom: 20px; }
    .card-title { color: var(--c-gold); margin: 0 0 15px 0; border-bottom: 1px dashed #444; padding-bottom: 10px; font-size: 1.2rem; }
    
    .btn { width: 100%; padding: 12px; border: none; border-radius: 5px; cursor: pointer; color: #fff; font-weight: bold; margin-top: 10px; transition: 0.2s; }
    .btn:hover { opacity: 0.9; }
    .btn-gold { background: linear-gradient(45deg, var(--c-gold), #b8860b); color: #000; }
    .btn-green { background: var(--c-green); }
    .btn-red { background: var(--c-red); }
    .btn-gray { background: #444; }
    .btn-sm { padding: 5px 10px; font-size: 0.8rem; width: auto; margin-top: 0; }
    
    .p-input { background: #000; border: 1px solid #444; color: #fff; padding: 8px; width: 100%; border-radius: 4px; }
    .asset-box { display: flex; align-items: center; background: #000; border: 1px solid #444; border-radius: 6px; padding: 10px; gap: 10px; margin-bottom: 10px; }
    
    .proof-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px; }
    .proof-item { background: #000; border: 1px solid #333; border-radius: 8px; overflow: hidden; position: relative; text-align: center; }
    .proof-status-badge { position: absolute; top: 5px; right: 5px; padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; color: #fff; font-weight: bold; }
    
    /* File List in Sidebar */
    .file-item { display: flex; align-items: center; gap: 10px; background: #0a0a0a; padding: 8px; margin-bottom: 5px; border-radius: 6px; border: 1px solid #333; transition: 0.2s; }
    .file-item:hover { border-color: var(--c-gold); }
    .file-icon { font-size: 1.2rem; color: #777; }
    .file-link { flex: 1; color: #fff; text-decoration: none; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .file-tag { font-size: 0.7rem; background: #333; padding: 2px 6px; border-radius: 4px; color: #aaa; }
    
    .delete-btn { background: none; border: none; color: var(--c-red); cursor: pointer; padding: 0 5px; font-size: 1.1rem; transition: 0.2s; }
    .delete-btn:hover { transform: scale(1.1); }
    
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; justify-content: center; align-items: center; }
    .modal-box { background: #1a1a1a; padding: 30px; width: 450px; border: 2px solid var(--c-red); border-radius: 10px; text-align: center; }
</style>

<div class="container split-layout">
    
    <div class="sidebar">
        <h3 style="color:#fff; border-bottom:2px solid var(--c-gold); padding-bottom:10px; margin-top:0;">📂 ملف العملية</h3>
        
        <div class="info-block">
            <span class="info-label">📊 مواصفات فنية:</span>
            <div class="info-value" style="font-size:0.85rem;">
                <strong>الخامة:</strong> <?php echo $specs['mat']; ?><br>
                <strong>الطبقات:</strong> <?php echo $specs['layers']; ?><br>
                <strong>القص:</strong> <?php echo $specs['cut']; ?><br>
                <strong>الفورمة:</strong> <?php echo $specs['die']; ?>
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
        
        <div class="info-block">
            <span class="info-label">📋 مسار العمل:</span>
            <div class="timeline">
                <?php foreach($workflow as $k => $v): $active = ($k == $curr) ? 'active' : ''; ?>
                <div class="timeline-item <?php echo $active; ?>"><span class="t-title"><?php echo $v['label']; ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="info-block" style="border-top:1px dashed #333; padding-top:15px;">
            <span class="info-label">⚙️ تحكم إداري (تجاوز):</span>
            <div class="admin-controls">
                <?php if($prev_stage_key): ?>
                <form method="POST" style="flex:1;"><input type="hidden" name="target_stage" value="<?php echo $prev_stage_key; ?>"><button name="force_stage_change" class="btn btn-red btn-sm" style="width:100%;">« تراجع جبري</button></form>
                <?php endif; ?>
                <?php if($next_stage_key): ?>
                <form method="POST" style="flex:1;"><input type="hidden" name="target_stage" value="<?php echo $next_stage_key; ?>"><button name="force_stage_change" class="btn btn-gold btn-sm" style="width:100%;">تمرير جبري »</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="main-content">

        <div class="stage-header">
            <?php foreach($workflow as $key => $data): ?>
                <div class="step-badge <?php echo ($key == $curr) ? 'active' : ''; ?>"><?php echo $data['label']; ?></div>
            <?php endforeach; ?>
        </div>

        <div class="main-card" style="border-top:3px solid var(--c-gold);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
                <h3 class="card-title" style="margin:0;">📦 <?php echo $job['job_name']; ?> (#<?php echo $job['id']; ?>)</h3>
                <button onclick="printOrder()" class="btn btn-gray" style="width:auto; padding:5px 15px; font-size:0.8rem;">📄 طباعة أمر الشغل</button>
            </div>
        </div>

        <?php if($curr == 'briefing'): ?>
        <div class="main-card">
            <h3 class="card-title">📝 التجهيز</h3>
            <form method="POST" enctype="multipart/form-data">
                <textarea name="notes" rows="3" class="p-input" placeholder="ملاحظات العلبة (طريقة الفتح، الاتجاه...)"></textarea>
                <div id="brief-area" style="margin-top:10px;">
                    <div style="display:flex; gap:5px; margin-bottom:5px;">
                        <input type="text" name="brief_desc[]" placeholder="وصف الملف (شعار/دايكت)" class="p-input" style="flex:2;">
                        <input type="file" name="brief_file[]" style="width:100px;">
                    </div>
                </div>
                <button type="button" onclick="addBrief()" class="btn btn-gray" style="width:auto;">+ ملف آخر</button>
                <button name="save_brief" class="btn btn-gold">حفظ وبدء التصميم ➡️</button>
            </form>
            <script>function addBrief(){ let d=document.createElement('div'); d.innerHTML=document.querySelector('#brief-area > div').innerHTML; document.getElementById('brief-area').appendChild(d); }</script>
        </div>
        <?php endif; ?>

        <?php if($curr == 'design'): ?>
        <div class="main-card">
            <h3 class="card-title">🎨 التصميم</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-bottom:20px;">
                <div style="display:flex; gap:10px; flex-direction:column;">
                    <input type="text" name="proof_desc" placeholder="اسم التصميم" class="p-input">
                    <input type="file" name="proof_file" style="color:#aaa;">
                </div>
                <button name="upload_proof" class="btn btn-gray">📤 رفع</button>
            </form>
            <div class="proof-grid">
                <?php $proofs = $conn->query("SELECT * FROM job_proofs WHERE job_id={$job['id']}");
                while($p = $proofs->fetch_assoc()): ?>
                    <div class="proof-item">
                        <a href="<?php echo $p['file_path']; ?>" target="_blank"><img src="<?php echo $p['file_path']; ?>" style="width:100%; height:80px; object-fit:contain;"></a>
                        <div style="font-size:0.7rem; color:#888; margin:5px 0;"><?php echo $p['description']; ?></div>
                        <form method="POST" onsubmit="return confirm('حذف البروفة نهائياً؟');"><input type="hidden" name="type" value="proof"><input type="hidden" name="item_id" value="<?php echo $p['id']; ?>"><button name="delete_item" style="color:red; background:none; border:none; cursor:pointer;">🗑️ حذف</button></form>
                    </div>
                <?php endwhile; ?>
            </div>
            <form method="POST"><button name="send_to_review" class="btn btn-gold">إرسال للمراجعة ➡️</button></form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'client_rev'): ?>
        <div class="main-card">
            <h3 class="card-title">⏳ المصادقة</h3>
            <?php 
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $base_url = str_replace('/modules', '', $base_url); 
            $link = $base_url . "/client_review.php?token=" . $job['access_token'];
            
            $wa_link = get_wa_link($job['client_phone'], "رابط المراجعة:\n$link");
            $wa_attr = $wa_link ? "href='$wa_link' target='_blank'" : "href='#' onclick=\"alert('رقم خطأ');\"";
            
            // إحصائيات
            $proofs = $conn->query("SELECT * FROM job_proofs WHERE job_id={$job['id']}");
            $total = $proofs->num_rows;
            $approved = 0; $rejected = 0;
            $proof_list = [];
            while($p = $proofs->fetch_assoc()) {
                if($p['status'] == 'approved') $approved++;
                if($p['status'] == 'rejected') $rejected++;
                $proof_list[] = $p;
            }
            ?>
            
            <div style="background:#111; padding:15px; text-align:center; border:1px dashed var(--c-gold);">
                <input type="text" value="<?php echo $link; ?>" readonly class="p-input" style="direction:ltr; margin-bottom:10px;">
                <a <?php echo $wa_attr; ?> class="btn btn-green">📱 واتساب للعميل</a>
            </div>

            <h4 style="color:#aaa; margin-top:20px;">حالة التصاميم (<?php echo "$approved / $total"; ?>):</h4>
            <div class="proof-grid">
                <?php foreach($proof_list as $p): 
                    $st_col = $p['status']=='approved' ? 'var(--c-green)' : ($p['status']=='rejected' ? 'var(--c-red)' : '#f1c40f');
                    $st_txt = $p['status']=='approved' ? 'مقبول' : ($p['status']=='rejected' ? 'مرفوض' : 'انتظار');
                ?>
                    <div class="proof-item" style="border:1px solid <?php echo $st_col; ?>;">
                        <div class="proof-status-badge" style="background:<?php echo $st_col; ?>;"><?php echo $st_txt; ?></div>
                        <img src="<?php echo $p['file_path']; ?>" style="width:100%; height:80px; object-fit:contain;">
                        <div style="padding:5px;">
                            <div style="font-size:0.7rem; color:#fff;"><?php echo $p['description']; ?></div>
                            <?php if($p['status'] == 'rejected'): ?>
                                <div style="font-size:0.7rem; color:var(--c-red); background:rgba(231,76,60,0.1); padding:3px; margin-top:3px; border-radius:3px;">
                                    💬 "<?php echo $p['client_comment']; ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:20px; border-top:1px solid #333; padding-top:20px;">
                <?php if($rejected > 0): ?>
                    <div style="text-align:center; color:var(--c-red); margin-bottom:10px; font-weight:bold;">
                        ⛔ يوجد (<?php echo $rejected; ?>) ملفات مرفوضة. يرجى العودة للتصميم للتعديل.
                    </div>
                    <form method="POST"><input type="hidden" name="prev_target" value="design"><input type="hidden" name="return_reason" value="رفض العميل للتصميم"><button name="return_stage" class="btn btn-red">↩️ عودة للتصميم (إجباري)</button></form>
                
                <?php elseif($total > 0 && $total == $approved): ?>
                    <div style="text-align:center; color:var(--c-green); margin-bottom:10px; font-weight:bold;">
                        ✅ تم اعتماد جميع التصاميم! يمكنك الآن رفع الملفات النهائية.
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <label style="color:var(--c-gold);">📥 رفع الملفات النهائية (AI/PDF/DXF):</label>
                        <input type="file" name="source_files[]" multiple required style="color:#fff; margin:10px 0; display:block; width:100%;">
                        <button name="finalize_review" class="btn btn-gold">حفظ وتحويل للتوريدات ➡️</button>
                    </form>
                
                <?php else: ?>
                    <div style="text-align:center; color:#aaa; margin-bottom:10px;">⏳ بانتظار رد العميل...</div>
                    <form method="POST"><input type="hidden" name="prev_target" value="design"><input type="hidden" name="return_reason" value="تراجع يدوي"><button name="return_stage" class="btn btn-gray" style="font-size:0.8rem;">تراجع للتصميم (يدوي)</button></form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($curr == 'pre_press'): ?>
        <div class="main-card">
            <h3 class="card-title">📦 التوريدات (ورق/فورمة)</h3>
            <?php $mats = $conn->query("SELECT * FROM job_files WHERE job_id={$job['id']} AND stage='materials'");
            if($mats->num_rows > 0): ?>
                <div style="margin-bottom:20px;">
                    <?php while($m = $mats->fetch_assoc()): 
                        $file_url = "http://" . $_SERVER['HTTP_HOST'] . "/" . $m['file_path']; ?>
                        <div class="asset-box">
                            <div style="flex:1;"><strong><?php echo $m['description']; ?></strong></div>
                            <?php if(!empty($m['uploaded_by'])): 
                                $wa_link_mat = get_wa_link($m['uploaded_by'], "طلب: " . $m['description'] . "\nرابط: " . ($m['file_path'] ? $file_url : '')); 
                                $wa_attr_mat = $wa_link_mat ? "href='$wa_link_mat' target='_blank'" : "href='#' onclick=\"alert('رقم خطأ');\""; ?>
                                <a <?php echo $wa_attr_mat; ?> class="btn btn-green" style="width:auto; font-size:0.8rem;">📱 إرسال للمورد</a>
                            <?php endif; ?>
                            <form method="POST" style="margin:0;"><input type="hidden" name="type" value="file"><input type="hidden" name="item_id" value="<?php echo $m['id']; ?>"><button name="delete_item" style="color:red; background:none; border:none; cursor:pointer;">×</button></form>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div id="mat-area">
                    <div style="display:flex; gap:5px; margin-bottom:5px;">
                        <input type="text" name="item_text[]" placeholder="بند (ورق/فورمة)" class="p-input" style="flex:2;">
                        <select name="supplier_phone[]" class="p-input" style="flex:1;"><option value="">مورد</option><?php echo $suppliers_options; ?></select>
                        <input type="file" name="item_file[]" style="width:80px;">
                    </div>
                </div>
                <button type="button" onclick="addM()" class="btn btn-gray" style="width:auto;">+ بند</button>
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button name="save_materials" class="btn btn-gray" style="flex:1;">💾 حفظ فقط</button>
                    <button name="finish_materials" class="btn btn-gold" style="flex:1;">✅ إنهاء وبدء الطباعة</button>
                </div>
            </form>
            <script>function addM(){ let d=document.createElement('div'); d.innerHTML=document.querySelector('#mat-area > div').innerHTML; document.getElementById('mat-area').appendChild(d); }</script>
        </div>
        <?php endif; ?>

        <?php if($curr == 'printing'): ?>
        <div class="main-card">
            <h3 class="card-title">🖨️ الطباعة</h3>
            <form method="POST">
                <label style="color:#aaa;">تأكيد الألوان:</label>
                <input type="text" name="colors" value="<?php echo $specs['colors']; ?>" class="p-input" style="margin-bottom:10px;">
                <label style="color:#aaa;">ملاحظات الطباعة:</label>
                <textarea name="print_notes" class="p-input" rows="2"></textarea>
                <button name="save_print_specs" class="btn btn-gold">✅ تمت الطباعة (للتكسير) ➡️</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'die_cutting'): ?>
        <div class="main-card">
            <h3 class="card-title">✂️ التكسير (Die Cutting)</h3>
            <form method="POST">
                <label style="color:#aaa;">ملاحظات التكسير:</label>
                <textarea name="diecut_notes" class="p-input" rows="2"></textarea>
                <button name="finish_diecut" class="btn btn-gold">✅ تم التكسير (للصق) ➡️</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'gluing'): ?>
        <div class="main-card">
            <h3 class="card-title">🧴 اللصق (Gluing)</h3>
            <form method="POST">
                <label style="color:#aaa;">ملاحظات اللصق والعد:</label>
                <textarea name="gluing_notes" class="p-input" rows="2"></textarea>
                <button name="finish_gluing" class="btn btn-gold">✅ تم اللصق (للتسليم) ➡️</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'delivery'): ?>
        <div class="main-card">
            <h3 class="card-title">🚚 التسليم</h3>
            <p style="color:#fff;">العميل: <?php echo $job['client_name']; ?></p>
            <form method="POST" onsubmit="return confirm('إغلاق نهائي؟');"><button name="finish_delivery" class="btn btn-gold">تسليم وإغلاق 🏁</button></form>
        </div>
        <?php endif; ?>

        <?php if(in_array($curr, ['accounting', 'completed'])): ?>
        <div class="main-card" style="text-align:center;">
            <h2 style="color:var(--c-green);">✅ العملية مكتملة</h2>
            <?php if($is_financial): ?>
                <a href="invoices.php" class="btn btn-gray" style="display:inline-block; width:auto;">الملف المالي</a>
                <?php if($curr == 'accounting'): ?><form method="POST"><button name="archive_job" class="btn btn-gold" style="width:auto; margin-top:10px;">أرشفة نهائية</button></form><?php endif; ?>
            <?php endif; ?>
            <?php if($curr == 'completed'): ?><form method="POST" onsubmit="return confirm('تأكيد؟');" style="margin-top:20px;"><button name="reopen_job" class="btn btn-red" style="width:auto;">🔄 إعادة فتح</button></form><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($prev_stage && !in_array($curr, ['completed'])): ?>
        <div style="text-align:right; margin-top:20px;">
            <button onclick="document.getElementById('backModal').style.display='flex'" class="btn btn-red" style="width:auto; padding:8px 20px; font-size:0.8rem;">↩️ تراجع</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="backModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="color:var(--c-red);">⚠️ تراجع للمرحلة السابقة</h3>
        <form method="POST">
            <input type="hidden" name="prev_target" value="<?php echo $prev_stage; ?>">
            <textarea name="return_reason" required placeholder="سبب التراجع..." style="width:100%; height:80px; background:#000; color:#fff; border:1px solid #555;"></textarea>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <button name="return_stage" class="btn btn-red">تأكيد</button>
                <button type="button" onclick="document.getElementById('backModal').style.display='none'" class="btn btn-gray">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function printOrder() {
    var win = window.open('', '', 'width=800,height=600');
    var fullNotes = <?php echo json_encode(nl2br($job['notes'] ?? '')); ?>;
    win.document.write('<html dir="rtl"><body style="font-family:sans-serif; padding:20px;">');
    win.document.write('<h2 style="text-align:center; border-bottom:2px solid #000;">أمر تشغيل كرتون</h2>');
    win.document.write('<h3>العميل: <?php echo $job['client_name']; ?> | العملية: <?php echo $job['job_name']; ?></h3>');
    win.document.write('<table border="1" width="100%" cellpadding="10" style="border-collapse:collapse; margin-top:20px;">');
    win.document.write('<tr><td><strong>الخامة:</strong> <?php echo $specs['mat']; ?></td><td><strong>الطبقات:</strong> <?php echo $specs['layers']; ?></td></tr>');
    win.document.write('<tr><td><strong>القص:</strong> <?php echo $specs['cut']; ?></td><td><strong>الفورمة:</strong> <?php echo $specs['die']; ?></td></tr>');
    win.document.write('</table>');
    win.document.write('<div style="margin-top:20px; border:1px dashed #000; padding:10px;"><strong>📜 ملاحظات:</strong><br>' + fullNotes + '</div>');
    win.document.write('<script>window.print();<\/script></body></html>');
    win.document.close();
}
</script>