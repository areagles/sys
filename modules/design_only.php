<?php
// modules/design_only.php - (Royal Design Studio V23.1 - Navigation & Details Fix)

// 0. إعدادات النظام
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. الإصلاح الذاتي والتحقق
$cols_to_check = [
    'job_files' => 'description', 
    'job_proofs' => 'description', 
    'job_proofs' => 'item_index',
    'job_proofs' => 'client_comment'
];

foreach($cols_to_check as $tbl => $col) {
    $check = $conn->query("SHOW COLUMNS FROM $tbl LIKE '$col'");
    if($check->num_rows == 0) { 
        $type = ($col == 'item_index') ? 'INT DEFAULT 0' : 'TEXT DEFAULT NULL';
        $conn->query("ALTER TABLE $tbl ADD COLUMN $col $type"); 
    }
}

// التأكد من وجود Access Token لرابط العميل
if(empty($job['access_token'])) {
    $token = bin2hex(random_bytes(16));
    $conn->query("UPDATE job_orders SET access_token='$token' WHERE id={$job['id']}");
    $job['access_token'] = $token;
}

// 2. دوال مساعدة
function safe_redirect($id) {
    echo "<script>window.location.href = 'job_details.php?id=$id';</script>";
    exit;
}

function get_wa_link($phone, $text) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 11 && substr($phone, 0, 2) == '01') { $phone = '2' . $phone; }
    elseif (strlen($phone) == 10 && substr($phone, 0, 2) == '05') { $phone = '966' . substr($phone, 1); }
    if (strlen($phone) < 10) return false;
    return "https://wa.me/$phone?text=" . urlencode($text);
}

// 3. معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_name = $_SESSION['name'] ?? 'Creative';

    // === أدوات التحكم ===
    
    // إضافة تعليق داخلي
    if (isset($_POST['add_internal_comment'])) {
        if(!empty($_POST['comment_text'])) {
            $c_text = $conn->real_escape_string($_POST['comment_text']);
            $timestamp = date('Y-m-d H:i');
            $new_note = "\n[💬 $user_name ($timestamp)]: $c_text";
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '$new_note') WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    // التمرير الجبري
    if (isset($_POST['force_stage_change'])) {
        $target_stage = $_POST['target_stage'];
        $conn->query("UPDATE job_orders SET current_stage='$target_stage' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // حذف بروفة تصميم
    if (isset($_POST['delete_proof'])) {
        $pid = intval($_POST['delete_proof']);
        $p = $conn->query("SELECT file_path FROM job_proofs WHERE id=$pid")->fetch_assoc();
        if($p && file_exists($p['file_path'])) { unlink($p['file_path']); }
        $conn->query("DELETE FROM job_proofs WHERE id=$pid");
        safe_redirect($job['id']);
    }

    // A. التجهيز
    if (isset($_POST['save_brief'])) {
        if (!empty($_POST['imagination_notes'])) {
            $note = $conn->real_escape_string($_POST['imagination_notes']);
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[📝 تخيل]: $note') WHERE id={$job['id']}");
        }
        if (!empty($_FILES['help_files']['name'][0])) {
            if (!file_exists('uploads/briefs')) @mkdir('uploads/briefs', 0777, true);
            foreach ($_FILES['help_files']['name'] as $i => $name) {
                if ($_FILES['help_files']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/briefs/" . time() . "_$i.$ext";
                    $file_desc = !empty($_POST['help_desc'][$i]) ? $conn->real_escape_string($_POST['help_desc'][$i]) : 'ملف مساعد';
                    if (move_uploaded_file($_FILES['help_files']['tmp_name'][$i], $target)) {
                        $conn->query("INSERT INTO job_files (job_id, file_path, file_type, stage, uploaded_by, description) VALUES ({$job['id']}, '$target', 'helper', 'briefing', '$user_name', '$file_desc')");
                    }
                }
            }
        }
        $conn->query("UPDATE job_orders SET current_stage='design' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // B. رفع التصاميم
    if (isset($_POST['upload_designs_only']) || isset($_POST['send_to_review'])) {
        if (!empty($_FILES['design_files']['name'])) {
            if (!file_exists('uploads/proofs')) @mkdir('uploads/proofs', 0777, true);
            
            foreach ($_FILES['design_files']['name'] as $idx => $name) {
                if (!empty($name) && $_FILES['design_files']['error'][$idx] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/proofs/" . time() . "_item_{$idx}.$ext";
                    $desc = "تصميم بند #" . ($idx + 1);
                    
                    if (move_uploaded_file($_FILES['design_files']['tmp_name'][$idx], $target)) {
                        $conn->query("INSERT INTO job_proofs (job_id, file_path, description, status, item_index) VALUES ({$job['id']}, '$target', '$desc', 'pending', $idx)");
                    }
                }
            }
        }

        if (isset($_POST['send_to_review'])) {
            $conn->query("UPDATE job_orders SET current_stage='client_rev' WHERE id={$job['id']}");
        }
        
        safe_redirect($job['id']);
    }

    // C. المراجعة والاعتماد
    if (isset($_POST['finalize_review'])) {
        $conn->query("UPDATE job_orders SET current_stage='handover' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    if (isset($_POST['manual_rollback'])) {
        $reason = $conn->real_escape_string($_POST['return_reason']);
        $note = "\n[⚠️ تراجع للتعديل]: $reason";
        $conn->query("UPDATE job_orders SET current_stage='design', notes = CONCAT(IFNULL(notes, ''), '$note') WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // D. التسليم
    if (isset($_POST['upload_handover_files'])) {
        $link = $conn->real_escape_string($_POST['source_link']);
        if($link) {
            $conn->query("INSERT INTO job_files (job_id, file_path, file_type, stage, description, uploaded_by) VALUES ({$job['id']}, '$link', 'link', 'handover', 'رابط خارجي', '$user_name')");
        }
        if (!empty($_FILES['source_files']['name'][0])) {
            if (!file_exists('uploads/source')) @mkdir('uploads/source', 0777, true);
            foreach ($_FILES['source_files']['name'] as $i => $name) {
                if ($_FILES['source_files']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/source/" . time() . "_src_$i.$ext";
                    if (move_uploaded_file($_FILES['source_files']['tmp_name'][$i], $target)) {
                        $conn->query("INSERT INTO job_files (job_id, file_path, file_type, stage, uploaded_by, description) VALUES ({$job['id']}, '$target', 'source', 'handover', 'ملف مصدر', '$user_name')");
                    }
                }
            }
        }
        safe_redirect($job['id']);
    }

    if (isset($_POST['finish_handover'])) {
        $check_inv = $conn->query("SELECT id FROM invoices WHERE job_id={$job['id']}");
        if($check_inv->num_rows == 0) {
            $client_id = $job['client_id']; $price = $job['price'] ?? 0;
            $conn->query("INSERT INTO invoices (client_id, job_id, total_amount, remaining_amount, inv_date, status) VALUES ($client_id, {$job['id']}, $price, $price, NOW(), 'unpaid')");
        }
        $conn->query("UPDATE job_orders SET current_stage='accounting' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // خدمات
    if (isset($_POST['archive_job'])) {
        $conn->query("UPDATE job_orders SET current_stage='completed' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }
    if (isset($_POST['reopen_job'])) {
        $conn->query("UPDATE job_orders SET current_stage='briefing' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }
    if (isset($_POST['delete_file'])) {
        $fid = intval($_POST['file_id']);
        $f = $conn->query("SELECT file_path FROM job_files WHERE id=$fid")->fetch_assoc();
        if($f && file_exists($f['file_path']) && !filter_var($f['file_path'], FILTER_VALIDATE_URL)) unlink($f['file_path']);
        $conn->query("DELETE FROM job_files WHERE id=$fid");
        safe_redirect($job['id']);
    }
}

// 4. تهيئة الواجهة
$workflow = [
    'briefing'   => ['label'=>'1. التجهيز', 'prev'=>null, 'next'=>'design'],
    'design'     => ['label'=>'2. التصميم', 'prev'=>'briefing', 'next'=>'client_rev'],
    'client_rev' => ['label'=>'3. المراجعة', 'prev'=>'design', 'next'=>'handover'],
    'handover'   => ['label'=>'4. التسليم', 'prev'=>'client_rev', 'next'=>'accounting'],
    'accounting' => ['label'=>'5. الحسابات', 'prev'=>'handover', 'next'=>'completed'],
    'completed'  => ['label'=>'6. الأرشيف', 'prev'=>'accounting', 'next'=>null]
];

$curr = $job['current_stage'];
if(!array_key_exists($curr, $workflow)) $curr = 'briefing';

$prev_stage = $workflow[$curr]['prev'] ?? null;
$next_stage = $workflow[$curr]['next'] ?? null;
$role = $_SESSION['role'] ?? '';
$is_financial = in_array($role, ['admin', 'manager', 'accountant']);

$items_count = (intval($job['quantity']) > 0) ? intval($job['quantity']) : 1;

// جلب البروفات
$latest_proofs = [];
for($i=0; $i<$items_count; $i++) {
    $q = $conn->query("SELECT * FROM job_proofs WHERE job_id={$job['id']} AND item_index=$i ORDER BY id DESC LIMIT 1");
    $latest_proofs[$i] = ($q->num_rows > 0) ? $q->fetch_assoc() : null;
}

$all_files = $conn->query("SELECT * FROM job_files WHERE job_id={$job['id']} ORDER BY id DESC");
?>

<style>
    :root { --d-gold: #d4af37; --d-bg: #121212; --d-card: #1e1e1e; --d-green: #2ecc71; --d-red: #c0392b; }
    
    .split-layout { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
    .sidebar { width: 320px; flex-shrink: 0; background: #151515; border: 1px solid #333; border-radius: 12px; padding: 20px; }
    .main-content { flex: 1; min-width: 0; }
    
    @media (max-width: 900px) { 
        .split-layout { flex-direction: column; }
        .sidebar { width: 100%; order: 2; } 
        .main-content { width: 100%; order: 1; margin-bottom: 20px; } 
    }

    .info-block { margin-bottom: 20px; border-bottom: 1px dashed #333; padding-bottom: 15px; }
    .info-label { color: var(--d-gold); font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; display: block; }
    .info-value { color: #ddd; font-size: 0.9rem; white-space: pre-wrap; line-height: 1.5; }
    
    .file-item { display: flex; align-items: center; gap: 10px; background: #0a0a0a; padding: 8px; margin-bottom: 5px; border-radius: 6px; border: 1px solid #333; }
    .file-link { flex: 1; color: #fff; text-decoration: none; font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .delete-btn { background:none; border:none; color:var(--d-red); cursor:pointer; font-size:1.1rem; padding: 0 5px; }

    .comments-box { background: #000; padding: 10px; border-radius: 6px; max-height: 200px; overflow-y: auto; font-size: 0.85rem; border: 1px solid #333; margin-bottom: 10px; }
    .comment-input { width: 100%; background: #222; border: 1px solid #444; padding: 8px; color: #fff; border-radius: 4px; }
    
    .stage-header { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; border-bottom: 1px solid #333; -webkit-overflow-scrolling: touch; }
    .step-badge { background: #333; color: #777; padding: 5px 15px; border-radius: 20px; white-space: nowrap; font-size: 0.85rem; transition:0.3s; }
    .step-badge.active { background: var(--d-gold); color: #000; font-weight: bold; transform: scale(1.05); }
    
    .main-card { background: var(--d-card); padding: 25px; border-radius: 12px; border: 1px solid #333; margin-bottom: 20px; }
    .card-title { color: var(--d-gold); margin: 0 0 15px 0; border-bottom: 1px dashed #444; padding-bottom: 10px; font-size: 1.2rem; display: flex; justify-content: space-between; align-items: center; }
    
    .item-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
    @media (max-width: 600px) { .item-grid { grid-template-columns: 1fr; } }

    .item-card { background: #000; border: 1px solid #333; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; position: relative; }
    .item-card.rejected { border-color: var(--d-red); box-shadow: 0 0 5px rgba(192, 57, 43, 0.3); }
    .item-card.approved { border-color: var(--d-green); box-shadow: 0 0 5px rgba(46, 204, 113, 0.3); }
    
    .item-img { width: 100%; height: 200px; object-fit: contain; background: #111; border-bottom: 1px solid #333; }
    .item-body { padding: 15px; flex: 1; display:flex; flex-direction:column; }
    
    .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; margin-bottom:5px; align-self:flex-start; }
    .st-pending { background: #f39c12; color: #000; }
    .st-approved { background: var(--d-green); color: #000; }
    .st-rejected { background: var(--d-red); color: #fff; }
    
    .feedback-box { background: rgba(192, 57, 43, 0.1); border-left: 3px solid var(--d-red); padding: 10px; margin-top: 10px; font-size: 0.85rem; color: #e74c3c; }
    .feedback-info { background: rgba(52, 152, 219, 0.1); border-left: 3px solid #3498db; padding: 10px; margin-top: 10px; font-size: 0.85rem; color: #3498db; }

    .btn { padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; color: #fff; font-weight: bold; width: 100%; margin-top: 10px; transition: 0.3s; }
    .btn:active { transform: scale(0.98); }
    .btn-gold { background: linear-gradient(45deg, var(--d-gold), #b8860b); color: #000; }
    .btn-green { background: var(--d-green); color: #000; }
    .btn-red { background: var(--d-red); }
    .btn-gray { background: #444; }
    .btn-sm { padding: 5px 10px; font-size: 0.8rem; width: auto; margin-top: 0; }
    
    .wa-btn { background: #25D366; color: #fff; text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; width: 100%; text-align: center; box-sizing: border-box; }
    
    .control-panel { background: #0f0f0f; border: 1px solid #333; padding: 10px; border-radius: 8px; margin-top: 20px; }
    .control-row { display: flex; gap: 5px; margin-top: 5px; }
</style>

<div class="container split-layout">
    
    <div class="sidebar">
        <h3 style="color:#fff; border-bottom:2px solid var(--d-gold); padding-bottom:10px; margin-top:0;">📂 معلومات العملية</h3>
        
        <div class="info-block">
            <span class="info-label">المشروع:</span>
            <span class="info-value"><?php echo htmlspecialchars($job['job_name']); ?></span>
        </div>
        <div class="info-block">
            <span class="info-label">العميل:</span>
            <span class="info-value"><?php echo htmlspecialchars($job['client_name']); ?></span>
        </div>
        
        <div class="info-block">
            <span class="info-label">💬 تعليقات الفريق (خاص):</span>
            <div class="comments-box">
                <?php echo nl2br(htmlspecialchars($job['notes'] ?? 'لا توجد ملاحظات.')); ?>
            </div>
            <form method="POST">
                <div style="display:flex; gap:5px;">
                    <input type="text" name="comment_text" class="comment-input" placeholder="اكتب ملاحظة..." required>
                    <button type="submit" name="add_internal_comment" class="btn btn-gold btn-sm">إرسال</button>
                </div>
            </form>
        </div>

        <div class="info-block" style="border:none;">
            <span class="info-label">📎 ملفات ومرفقات:</span>
            <?php if($all_files->num_rows > 0): ?>
                <?php while($f = $all_files->fetch_assoc()): ?>
                <div class="file-item">
                    <span style="font-size:1.2rem;">📄</span>
                    <a href="<?php echo $f['file_path']; ?>" target="_blank" class="file-link"><?php echo htmlspecialchars($f['description'] ?: basename($f['file_path'])); ?></a>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="file_id" value="<?php echo $f['id']; ?>">
                        <button name="delete_file" class="delete-btn" onclick="return confirm('حذف الملف نهائياً؟')">×</button>
                    </form>
                </div>
                <?php endwhile; ?>
            <?php else: echo "<div style='color:#666; font-size:0.8rem;'>لا يوجد ملفات.</div>"; endif; ?>
        </div>

        <div class="control-panel">
            <span class="info-label" style="text-align:center;">🕹️ تحكم إداري</span>
            
            <div class="control-row">
                <?php if($prev_stage): ?>
                <form method="POST" style="flex:1; margin:0;">
                    <input type="hidden" name="target_stage" value="<?php echo $prev_stage; ?>">
                    <button type="submit" name="force_stage_change" class="btn btn-gray btn-sm" style="width:100%;">« تراجع</button>
                </form>
                <?php endif; ?>
                
                <?php if($next_stage): ?>
                <form method="POST" style="flex:1; margin:0;">
                    <input type="hidden" name="target_stage" value="<?php echo $next_stage; ?>">
                    <button type="submit" name="force_stage_change" class="btn btn-gold btn-sm" style="width:100%; margin:0;">تمرير »</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if(!empty($job['job_details'])): ?>
        <div class="main-card">
            <h3 class="card-title">📄 تفاصيل الطلب (من البوابة)</h3>
            <div class="info-value" style="color:#eee; line-height:1.6;"><?php echo nl2br(htmlspecialchars($job['job_details'])); ?></div>
        </div>
        <?php endif; ?>

        <div class="stage-header">
            <?php foreach($workflow as $key => $label): ?>
                <div class="step-badge <?php echo ($key == $curr) ? 'active' : ''; ?>"><?php echo $label['label']; ?></div>
            <?php endforeach; ?>
        </div>

        <?php if($curr == 'briefing'): ?>
        <div class="main-card">
            <h3 class="card-title">📝 مرحلة التجهيز (Briefing)</h3>
            <form method="POST" enctype="multipart/form-data">
                <label style="color:#aaa;">وصف التخيل الفني / تعليمات المصمم:</label>
                <textarea name="imagination_notes" rows="4" style="width:100%; background:#000; border:1px solid #444; color:#fff; padding:15px; margin-bottom:15px;" placeholder="اكتب هنا..."></textarea>
                
                <div id="help_files_area">
                    <label style="color:#aaa;">ملفات مساعدة (شعار، صور، خطوط):</label>
                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <input type="file" name="help_files[]" style="color:#fff; width:100%;">
                        <input type="text" name="help_desc[]" placeholder="وصف الملف" style="background:#000; border:1px solid #444; color:#fff; padding:5px; flex:1; display:none;">
                    </div>
                </div>
                <button type="button" onclick="addHelpFile()" class="btn btn-gray" style="width:auto; margin-bottom:15px;">+ ملف آخر</button>
                <button type="submit" name="save_brief" class="btn btn-gold">حفظ وبدء التصميم ➡️</button>
            </form>
        </div>
        <script>function addHelpFile() { let div = document.createElement('div'); div.innerHTML = `<div style="display:flex; gap:10px; margin-bottom:10px;"><input type="file" name="help_files[]" style="color:#fff; width:100%;"></div>`; document.getElementById('help_files_area').appendChild(div); }</script>
        <?php endif; ?>

        <?php if($curr == 'design'): ?>
        <div class="main-card">
            <h3 class="card-title">🎨 ورشة التصميم (Studio)</h3>
            <p style="color:#aaa; margin-bottom:20px; font-size:0.9rem;">يمكنك رفع التصاميم، حفظ العمل، أو الإرسال للمراجعة.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="item-grid">
                    <?php for($i=0; $i<$items_count; $i++): 
                        $proof = $latest_proofs[$i];
                        $status = $proof['status'] ?? 'new';
                        $is_approved = ($status == 'approved');
                    ?>
                    <div class="item-card <?php echo $status == 'rejected' ? 'rejected' : ($is_approved ? 'approved' : ''); ?>">
                        
                        <?php if($proof): ?>
                            <a href="<?php echo $proof['file_path']; ?>" target="_blank">
                                <img src="<?php echo $proof['file_path']; ?>" class="item-img">
                            </a>
                        <?php else: ?>
                            <div class="item-img" style="display:flex; align-items:center; justify-content:center; color:#555;">لا يوجد ملف</div>
                        <?php endif; ?>
                        
                        <div class="item-body">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <span style="font-weight:bold; color:#fff;">تصميم بند #<?php echo $i+1; ?></span>
                                
                                <?php if($proof): ?>
                                    <button type="submit" name="delete_proof" value="<?php echo $proof['id']; ?>" 
                                            onclick="return confirm('حذف هذا التصميم؟')" 
                                            style="background:none; border:none; color:var(--d-red); cursor:pointer;" title="حذف الملف">
                                        <i class="fa-solid fa-trash"></i> 🗑️
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($proof && !empty($proof['client_comment'])): ?>
                                <div class="<?php echo ($status=='rejected')?'feedback-box':'feedback-info'; ?>">
                                    💬 <?php echo htmlspecialchars($proof['client_comment']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if($is_approved): ?>
                                <div class="status-badge st-approved" style="margin-top:10px;">✅ معتمد</div>
                            <?php elseif($status == 'rejected'): ?>
                                <div class="status-badge st-rejected" style="margin-top:10px;">❌ مرفوض (تعديل مطلوب)</div>
                                <label style="color:#aaa; font-size:0.8rem; margin-top:10px; display:block;">رفع التعديل:</label>
                                <input type="file" name="design_files[<?php echo $i; ?>]" style="color:#fff; font-size:0.8rem; width:100%;">
                            <?php else: ?>
                                <div class="status-badge st-pending" style="margin-top:10px;">⏳ <?php echo $proof ? 'تم الرفع (محفوظ)' : 'بانتظار الرفع'; ?></div>
                                <input type="file" name="design_files[<?php echo $i; ?>]" style="color:#fff; font-size:0.8rem; width:100%; margin-top:10px;">
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="upload_designs_only" class="btn btn-gray" style="flex:1;">💾 حفظ ورفع التصاميم (بدون إرسال)</button>
                    <button type="submit" name="send_to_review" class="btn btn-gold" style="flex:1;" onclick="return confirm('هل أنت متأكد من إرسال التصاميم للعميل للمراجعة؟');">🚀 إرسال للمراجعة</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'client_rev'): ?>
        <div class="main-card">
            <h3 class="card-title">🧐 مراجعة العميل</h3>
            <?php 
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
            $host = $_SERVER['HTTP_HOST'];
            $path = dirname($_SERVER['PHP_SELF']); 
            $base_url = str_replace('/modules', '', "$protocol://$host$path"); 
            $client_link = $base_url . "/client_review.php?token=" . $job['access_token'];
            
            $approved_count = 0; $rejected_count = 0;
            foreach($latest_proofs as $p) {
                if($p && $p['status'] == 'approved') $approved_count++;
                if($p && $p['status'] == 'rejected') $rejected_count++;
            }
            ?>
            
            <div style="text-align:center; padding:20px; background:#111; border-radius:10px; margin-bottom:20px;">
                <p style="color:#aaa;">رابط المراجعة للعميل:</p>
                <input type="text" value="<?php echo $client_link; ?>" readonly style="width:100%; background:#000; color:var(--d-green); text-align:center; padding:10px; border:1px dashed #444; margin-bottom:15px; direction:ltr; font-family:monospace;">
                <a href="<?php echo get_wa_link($job['client_phone'], "مرحباً، يرجى مراجعة التصاميم واعتمادها:\n$client_link"); ?>" target="_blank" class="wa-btn"><i class="fa-brands fa-whatsapp"></i> إرسال واتساب</a>
            </div>

            <h4 style="color:#fff;">حالة البنود (<?php echo "$approved_count / $items_count"; ?> معتمد):</h4>
            <div class="item-grid">
                <?php for($i=0; $i<$items_count; $i++): 
                    $proof = $latest_proofs[$i];
                    $status = $proof['status'] ?? 'pending';
                ?>
                <div class="item-card <?php echo $status; ?>">
                    <?php if($proof): ?>
                        <a href="<?php echo $proof['file_path']; ?>" target="_blank"><img src="<?php echo $proof['file_path']; ?>" class="item-img"></a>
                    <?php else: ?>
                        <div class="item-img"></div>
                    <?php endif; ?>
                    <div class="item-body">
                        <span style="color:#fff; font-weight:bold;">بند #<?php echo $i+1; ?></span>
                        
                        <?php if($proof && !empty($proof['client_comment'])): ?>
                            <div class="<?php echo ($status=='rejected')?'feedback-box':'feedback-info'; ?>">
                                💬 <?php echo htmlspecialchars($proof['client_comment']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if($status == 'approved'): ?>
                            <span class="status-badge st-approved">✅ معتمد</span>
                        <?php elseif($status == 'rejected'): ?>
                            <span class="status-badge st-rejected">❌ مرفوض</span>
                        <?php else: ?>
                            <span class="status-badge st-pending">⏳ قيد الانتظار</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div style="margin-top:20px; border-top:1px solid #333; padding-top:20px;">
                <?php if($rejected_count > 0): ?>
                    <div style="text-align:center; color:var(--d-red); margin-bottom:10px; font-weight:bold;">⚠️ هناك بنود مرفوضة، يجب إعادتها للتصميم للتعديل.</div>
                    <form method="POST"><input type="hidden" name="return_reason" value="تعديلات مطلوبة"><button name="manual_rollback" class="btn btn-red">↩️ إعادة للتصميم (للتعديل)</button></form>
                <?php elseif($approved_count == $items_count): ?>
                    <div style="text-align:center; color:var(--d-green); margin-bottom:10px; font-weight:bold;">🎉 جميع التصاميم معتمدة!</div>
                    <form method="POST"><button name="finalize_review" class="btn btn-gold">إتمام واعتماد نهائي ➡️</button></form>
                <?php else: ?>
                    <p style="text-align:center; color:#666;">بانتظار رد العميل على باقي البنود...</p>
                    <form method="POST"><input type="hidden" name="return_reason" value="تراجع يدوي"><button name="manual_rollback" class="btn btn-gray" style="width:auto;">تراجع للتصميم</button></form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($curr == 'handover'): ?>
        <div class="main-card">
            <h3 class="card-title">📁 تسليم الملفات النهائية (Handover)</h3>
            <form method="POST" enctype="multipart/form-data" style="background:#111; padding:15px; border-radius:8px;">
                <label style="color:#aaa;">رابط خارجي (Drive/Dropbox):</label>
                <input type="text" name="source_link" style="width:100%; padding:10px; background:#222; border:1px solid #444; color:#fff; margin-bottom:10px;">
                <label style="color:#aaa;">أو رفع ملفات المصدر (Zip/AI/PSD):</label>
                <input type="file" name="source_files[]" multiple style="color:#fff; margin-bottom:15px; display:block;">
                <button type="submit" name="upload_handover_files" class="btn btn-gray">📤 رفع الملفات</button>
            </form>
            <form method="POST" style="margin-top:20px;">
                <button type="submit" name="finish_handover" class="btn btn-gold">✅ تأكيد التسليم والتحويل للحسابات</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if($curr == 'accounting'): ?>
        <div class="main-card" style="text-align:center;">
            <h2 style="color:var(--d-green);">💰 قسم الحسابات</h2>
            <?php if($is_financial): ?>
                <a href="invoices.php" class="btn btn-gray" style="display:inline-block; width:auto; margin-bottom:10px;">الفواتير</a>
                <form method="POST"><button name="archive_job" class="btn btn-gold" style="width:auto;">✅ أرشفة العملية</button></form>
            <?php else: ?>
                <p style="color:#aaa;">العملية لدى الإدارة المالية لإغلاق الحساب.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($curr == 'completed'): ?>
        <div class="main-card" style="text-align:center;">
            <h2 style="color:var(--d-green);">✅ مكتملة ومؤرشفة</h2>
            <form method="POST" onsubmit="return confirm('هل تريد إعادة فتح العملية؟');"><button name="reopen_job" class="btn btn-red" style="width:auto;">🔄 إعادة فتح</button></form>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>