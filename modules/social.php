<?php
// modules/social.php - (Royal Social V52.0 - Full Ideas Workflow Fixed)

ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. التأسيس وإصلاح الجداول
// [تحديث هام]: إضافة أعمدة للأفكار لتصبح منفصلة لكل بوست
$conn->query("ALTER TABLE social_posts MODIFY design_path TEXT");
$conn->query("ALTER TABLE social_posts ADD COLUMN IF NOT EXISTS idea_text TEXT");
$conn->query("ALTER TABLE social_posts ADD COLUMN IF NOT EXISTS idea_status VARCHAR(50) DEFAULT 'pending'");
$conn->query("ALTER TABLE social_posts ADD COLUMN IF NOT EXISTS idea_feedback TEXT");

$conn->query("CREATE TABLE IF NOT EXISTS social_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    post_index INT NOT NULL,
    idea_text TEXT,
    idea_status VARCHAR(50) DEFAULT 'pending',
    idea_feedback TEXT,
    content_text TEXT,
    design_path TEXT,
    status VARCHAR(50) DEFAULT 'pending', 
    client_feedback TEXT,
    platform VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// 2. الدوال المساعدة
function safe_redirect($id) {
    echo "<script>window.location.href = 'job_details.php?id=$id';</script>";
    exit;
}

// 3. استخراج البيانات الفنية
$raw_text = $job['job_details'] ?? '';
function get_spec($pattern, $text) {
    preg_match($pattern, $text, $matches);
    return isset($matches[1]) ? trim($matches[1]) : null;
}

$specs = [
    'platforms' => get_spec('/(?:المنصات|المستهدفة):\s*(.*)/u', $raw_text) ?? 'غير محدد',
    'posts_num' => get_spec('/(?:عدد البنود|عدد البوستات\/الفيديوهات):\s*(\d+)/u', $raw_text),
    'goal'      => get_spec('/(?:الهدف|Goal):\s*(.*)/u', $raw_text) ?? 'غير محدد',
    'audience'  => get_spec('/(?:الجمهور|Audience):\s*(.*)/u', $raw_text) ?? 'عام',
    'budget'    => get_spec('/(?:الميزانية|Budget):\s*(.*)/u', $raw_text) ?? '-',
];

$posts_count = ($job['quantity'] > 0) ? intval($job['quantity']) : intval($specs['posts_num'] ?? 1);

// رابط العميل
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$base_url = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = str_replace('/modules', '', $base_url); 
$client_link = $base_url . "/client_review.php?token=" . $job['access_token'];

// 4. معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_name = $_SESSION['name'] ?? 'Team';

    // === ميزات جديدة: التعليقات، الحذف المتقدم، التحكم الجبري ===
    if (isset($_POST['add_internal_comment'])) {
        if(!empty($_POST['comment_text'])) {
            $c_text = $conn->real_escape_string($_POST['comment_text']);
            $timestamp = date('Y-m-d H:i');
            $new_note = "\n[💬 $user_name ($timestamp)]: $c_text";
            $conn->query("UPDATE job_orders SET notes = CONCAT(IFNULL(notes, ''), '$new_note') WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    if (isset($_POST['force_stage_change'])) {
        $target_stage = $_POST['target_stage'];
        $conn->query("UPDATE job_orders SET current_stage='$target_stage' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    if (isset($_POST['delete_file'])) {
        $fid = intval($_POST['file_id']);
        $f = $conn->query("SELECT file_path FROM job_files WHERE id=$fid")->fetch_assoc();
        if($f && file_exists($f['file_path'])) { unlink($f['file_path']); }
        $conn->query("DELETE FROM job_files WHERE id=$fid");
        safe_redirect($job['id']);
    }

    if (isset($_POST['delete_design_img'])) {
        $pid = intval($_POST['post_id']);
        $img_to_del = $_POST['img_path'];
        $row = $conn->query("SELECT design_path FROM social_posts WHERE id=$pid")->fetch_assoc();
        if ($row && !empty($row['design_path'])) {
            $images = json_decode($row['design_path'], true) ?? [];
            $new_images = array_filter($images, function($img) use ($img_to_del) { return $img !== $img_to_del; });
            if (file_exists($img_to_del)) { unlink($img_to_del); }
            $json_paths = json_encode(array_values($new_images));
            $conn->query("UPDATE social_posts SET design_path='$json_paths' WHERE id=$pid");
        }
        safe_redirect($job['id']);
    }

    // A. مرحلة الفكرة (تم التعديل لتكون بنود منفصلة)
    if (isset($_POST['save_idea_batch']) || isset($_POST['send_idea_batch'])) {
        // إنشاء السجلات إن لم تكن موجودة
        $check = $conn->query("SELECT id FROM social_posts WHERE job_id={$job['id']}");
        if($check->num_rows == 0){
            for ($i=1; $i <= $posts_count; $i++) { 
                $conn->query("INSERT INTO social_posts (job_id, post_index) VALUES ({$job['id']}, $i)");
            }
        }

        if(isset($_POST['ideas'])){
            foreach ($_POST['ideas'] as $pid => $text) {
                $safe_text = $conn->real_escape_string($text);
                $conn->query("UPDATE social_posts SET idea_text='$safe_text', idea_status='pending' WHERE id=$pid");
            }
        }

        if (!empty($_FILES['ref_files']['name'][0])) {
            if (!file_exists('uploads/briefs')) @mkdir('uploads/briefs', 0777, true);
            foreach ($_FILES['ref_files']['name'] as $i => $name) {
                if ($_FILES['ref_files']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/briefs/" . time() . "_ref_$i.$ext";
                    if(move_uploaded_file($_FILES['ref_files']['tmp_name'][$i], $target)){
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ({$job['id']}, '$target', 'idea', 'ملف استدلالي', '$user_name')");
                    }
                }
            }
        }

        if(isset($_POST['send_idea_batch'])) {
            $conn->query("UPDATE job_orders SET current_stage = 'idea_review' WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    // B. المحتوى
    if (isset($_POST['save_content']) || isset($_POST['send_content'])) {
        if(isset($_POST['content'])){
            foreach ($_POST['content'] as $pid => $text) {
                $safe_text = $conn->real_escape_string($text);
                $conn->query("UPDATE social_posts SET content_text='$safe_text', status='pending_review' WHERE id=$pid");
            }
        }

        if (!empty($_FILES['content_docs']['name'][0])) {
            if (!file_exists('uploads/briefs')) @mkdir('uploads/briefs', 0777, true);
            foreach ($_FILES['content_docs']['name'] as $i => $name) {
                if ($_FILES['content_docs']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/briefs/" . time() . "_doc_$i.$ext";
                    if(move_uploaded_file($_FILES['content_docs']['tmp_name'][$i], $target)){
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ({$job['id']}, '$target', 'content', 'ملف محتوى', '$user_name')");
                    }
                }
            }
        }

        if(isset($_POST['send_content'])) {
            $conn->query("UPDATE job_orders SET current_stage = 'content_review' WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    // C. التصميم
    if (isset($_POST['upload_designs']) || isset($_POST['send_designs'])) {
        if (!empty($_FILES['design_files']['name'])) {
            if (!file_exists('uploads/proofs')) @mkdir('uploads/proofs', 0777, true);
            foreach ($_FILES['design_files']['name'] as $pid => $files) {
                $uploaded_paths = [];
                $existing_row = $conn->query("SELECT design_path FROM social_posts WHERE id=$pid")->fetch_assoc();
                $current_paths = !empty($existing_row['design_path']) ? json_decode($existing_row['design_path'], true) : [];
                if(!is_array($current_paths)) $current_paths = [];

                if(is_array($files)) {
                    foreach($files as $key => $name) {
                        if (!empty($name) && $_FILES['design_files']['error'][$pid][$key] == 0) {
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $target = "uploads/proofs/" . time() . "_post_{$pid}_{$key}.$ext";
                            if(move_uploaded_file($_FILES['design_files']['tmp_name'][$pid][$key], $target)){
                                $current_paths[] = $target;
                            }
                        }
                    }
                }
                
                if (!empty($current_paths)) {
                    $json_paths = json_encode(array_values($current_paths));
                    $conn->query("UPDATE social_posts SET design_path='$json_paths', status='pending_design_review' WHERE id=$pid");
                }
            }
        }

        if (!empty($_FILES['source_files']['name'][0])) {
            if (!file_exists('uploads/source')) @mkdir('uploads/source', 0777, true);
            foreach ($_FILES['source_files']['name'] as $i => $name) {
                if ($_FILES['source_files']['error'][$i] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $target = "uploads/source/" . time() . "_src_$i.$ext";
                    if(move_uploaded_file($_FILES['source_files']['tmp_name'][$i], $target)){
                        $conn->query("INSERT INTO job_files (job_id, file_path, stage, description, uploaded_by) VALUES ({$job['id']}, '$target', 'design', 'ملف مصدر (AI/PSD)', '$user_name')");
                    }
                }
            }
        }

        if(isset($_POST['send_designs'])) {
            $conn->query("UPDATE job_orders SET current_stage = 'design_review' WHERE id={$job['id']}");
        }
        safe_redirect($job['id']);
    }

    // D. النشر
    if (isset($_POST['finish_publishing'])) {
        $chk = $conn->query("SELECT id FROM invoices WHERE job_id={$job['id']}");
        if($chk->num_rows == 0) {
            $client_id = $job['client_id']; $price = $job['price'] ?? 0;
            $conn->query("INSERT INTO invoices (client_id, job_id, total_amount, remaining_amount, inv_date, status) VALUES ($client_id, {$job['id']}, $price, $price, NOW(), 'unpaid')");
        }
        $conn->query("UPDATE job_orders SET current_stage='accounting' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // E. إنهاء وأرشفة
    if (isset($_POST['archive_job'])) {
        $conn->query("UPDATE job_orders SET current_stage='completed' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // F. إعادة الفتح
    if (isset($_POST['reopen_job'])) {
        $conn->query("UPDATE job_orders SET current_stage='briefing' WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }

    // أدوات مساعدة
    if (isset($_POST['return_stage'])) {
        $prev = $_POST['prev_target'];
        $reason = $conn->real_escape_string($_POST['return_reason']);
        $note = "\n[⚠️ تراجع]: $reason";
        $conn->query("UPDATE job_orders SET current_stage='$prev', notes = CONCAT(IFNULL(notes, ''), '$note') WHERE id={$job['id']}");
        safe_redirect($job['id']);
    }
}

// 5. تهيئة العرض
$workflow = [
    'briefing'        => '1. الأفكار',
    'idea_review'     => '2. مراجعة الأفكار',
    'content_writing' => '3. كتابة المحتوى',
    'content_review'  => '4. مراجعة المحتوى',
    'designing'       => '5. التصميم',
    'design_review'   => '6. مراجعة التصميم',
    'publishing'      => '7. النشر',
    'accounting'      => '8. الحسابات',
    'completed'       => '9. الأرشيف'
];
$curr = $job['current_stage'];
if(!array_key_exists($curr, $workflow)) $curr = 'briefing';

$keys = array_keys($workflow);
$curr_idx = array_search($curr, $keys);
$prev_stage_key = isset($keys[$curr_idx-1]) ? $keys[$curr_idx-1] : null;
$next_stage_key = isset($keys[$curr_idx+1]) ? $keys[$curr_idx+1] : null;
?>

<style>
    :root { 
        --royal-gold: #d4af37; 
        --royal-gold-dim: #aa8c2c;
        --royal-dark: #121212; 
        --royal-panel: #1e1e1e; 
        --royal-green: #27ae60; 
        --royal-red: #c0392b; 
    }
    
    .social-layout { display: flex; gap: 20px; align-items: flex-start; }
    .social-main { flex: 3; min-width: 0; }
    .social-sidebar { flex: 1; min-width: 280px; background: #151515; border: 1px solid #333; border-radius: 12px; padding: 20px; position: sticky; top: 20px; max-height: 90vh; overflow-y: auto; }

    @media (max-width: 900px) { 
        .social-layout { flex-direction: column; } 
        .social-sidebar { width: 100%; order: 2; position: static; max-height: none; } 
        .social-main { width: 100%; order: 1; margin-bottom: 20px; }
    }

    .stage-container { display: flex; overflow-x: auto; gap: 8px; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 1px solid #333; }
    .stage-pill { background: #2c2c2c; color: #777; padding: 6px 15px; border-radius: 20px; font-size: 0.8rem; white-space: nowrap; transition: 0.3s; }
    .stage-pill.active { background: var(--royal-gold); color: #000; font-weight: bold; transform: scale(1.05); }
    
    .royal-card { background: var(--royal-panel); padding: 25px; border-radius: 12px; border: 1px solid #333; margin-bottom: 20px; position: relative; overflow: hidden; }
    .royal-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--royal-gold); }
    
    .card-h { color: var(--royal-gold); margin: 0 0 20px 0; border-bottom: 1px dashed #444; padding-bottom: 10px; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
    
    .gallery { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
    .gallery-item { position: relative; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #444; }
    .gallery-img { width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
    .gallery-item:hover .gallery-img { transform: scale(1.1); }
    .del-btn { position: absolute; top: 0; right: 0; background: rgba(0,0,0,0.8); color: red; border: none; width: 25px; height: 25px; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; z-index: 2; }

    .post-card { background: #000; border: 1px solid #333; border-radius: 8px; padding: 20px; margin-bottom: 20px; transition: 0.3s; }
    .post-badge { display: inline-block; background: #222; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; color: #aaa; margin-bottom: 10px; }
    
    .preview-grid { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; flex-wrap: wrap; }
    .preview-img-box { position: relative; width: 80px; height: 80px; border: 1px solid #444; border-radius: 4px; overflow: hidden; }
    .preview-img-box img { width: 100%; height: 100%; object-fit: cover; }

    textarea, input[type="text"] { width: 100%; background: #151515; border: 1px solid #444; color: #fff; padding: 12px; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
    .action-bar { display: flex; gap: 10px; margin-top: 20px; }
    .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; color: #fff; flex: 1; transition: 0.2s; }
    .btn-gold { background: linear-gradient(135deg, var(--royal-gold), var(--royal-gold-dim)); color: #000; }
    .btn-gray { background: #333; color: #ccc; }
    .btn-red { background: var(--royal-red); }
    .btn-sm { padding: 8px 15px; font-size: 0.8rem; flex: none; width: auto; }

    .timeline { position: relative; padding-right: 20px; border-right: 2px solid #333; }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; right: -26px; top: 5px; width: 10px; height: 10px; background: #555; border-radius: 50%; border: 2px solid #151515; transition: 0.3s; }
    .timeline-item.active::before { background: var(--royal-gold); box-shadow: 0 0 10px var(--royal-gold); }
    .timeline-item.active .t-title { color: var(--royal-gold); font-weight: bold; }
    .t-title { color: #888; font-size: 0.9rem; }

    .comments-box { background: #000; padding: 10px; border-radius: 6px; max-height: 200px; overflow-y: auto; font-size: 0.85rem; border: 1px solid #333; margin-bottom: 10px; }
    .comment-input { width: 100%; background: #222; border: 1px solid #444; padding: 8px; color: #fff; border-radius: 4px; margin-bottom: 5px; }
    .admin-controls { display: flex; gap: 5px; margin-top: 10px; background: #222; padding: 5px; border-radius: 5px; }
    
    .status-alert { padding:10px; border-radius:5px; margin-bottom:10px; font-size:0.9rem; }
    .status-pending { background: rgba(255,193,7,0.1); color: #ffc107; border: 1px solid #ffc107; }
    .status-approved { background: rgba(39,174,96,0.1); color: #27ae60; border: 1px solid #27ae60; }
    .status-rejected { background: rgba(192,57,43,0.1); color: #e74c3c; border: 1px solid #e74c3c; }
</style>

<div class="container">
    
    <div class="social-layout">
        <div class="social-sidebar">
            <h3 style="color:#fff; border-bottom:2px solid var(--royal-gold); padding-bottom:10px; margin-top:0;">📂 ملف الحملة</h3>
            
            <div style="margin-bottom:20px;">
                <h4 style="color:var(--royal-gold); margin-bottom:10px; font-size:0.9rem;">📊 البيانات الأساسية:</h4>
                <div style="background:#0a0a0a; padding:10px; border-radius:6px; font-size:0.9rem; color:#ccc; line-height:1.6;">
                    <strong>المنصات:</strong> <?php echo $specs['platforms']; ?><br>
                    <strong>البوستات:</strong> <?php echo $posts_count; ?><br>
                    <strong>الهدف:</strong> <?php echo $specs['goal']; ?>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <h4 style="color:var(--royal-gold); margin-bottom:10px; font-size:0.9rem;">💬 التعليقات الداخلية:</h4>
                <div class="comments-box">
                    <?php echo nl2br($job['notes'] ?? 'لا توجد ملاحظات'); ?>
                </div>
                <form method="POST">
                    <input type="text" name="comment_text" class="comment-input" placeholder="اكتب ملاحظة..." required>
                    <button type="submit" name="add_internal_comment" class="btn btn-gray btn-sm" style="width:100%;">إرسال</button>
                </form>
            </div>

            <div style="margin-bottom:20px;">
                <h4 style="color:var(--royal-gold); margin-bottom:10px; font-size:0.9rem;">📋 مسار العمل:</h4>
                <div class="timeline">
                    <?php foreach($workflow as $k => $v): $active = ($k == $curr) ? 'active' : ''; ?>
                    <div class="timeline-item <?php echo $active; ?>"><span class="t-title"><?php echo $v; ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="border-top:1px dashed #333; padding-top:15px;">
                <span style="color:#aaa; font-size:0.8rem; display:block; margin-bottom:5px;">⚙️ تحكم إداري (تجاوز):</span>
                <div class="admin-controls">
                    <?php if($prev_stage_key): ?>
                    <form method="POST" style="flex:1;"><input type="hidden" name="target_stage" value="<?php echo $prev_stage_key; ?>"><button name="force_stage_change" class="btn btn-red btn-sm" style="width:100%;">« تراجع</button></form>
                    <?php endif; ?>
                    <?php if($next_stage_key): ?>
                    <form method="POST" style="flex:1;"><input type="hidden" name="target_stage" value="<?php echo $next_stage_key; ?>"><button name="force_stage_change" class="btn btn-gold btn-sm" style="width:100%;">تمرير »</button></form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="social-main">
            <div class="stage-container">
                <?php foreach($workflow as $key => $label): ?>
                    <div class="stage-pill <?php echo ($key == $curr) ? 'active' : ''; ?>"><?php echo $label; ?></div>
                <?php endforeach; ?>
            </div>

            <div class="royal-card">
                <h3 class="card-h">🌐 الحملة: <?php echo $job['job_name']; ?></h3>
                <h4 style="color:#aaa; font-size:0.9rem; margin-top:0;">💡 الملفات الاستدلالية (Moodboard) & المرفقات:</h4>
                <?php 
                $files = $conn->query("SELECT * FROM job_files WHERE job_id={$job['id']} ORDER BY id DESC");
                if($files->num_rows > 0): ?>
                    <div class="gallery">
                    <?php while($f = $files->fetch_assoc()): 
                        $is_img = in_array(strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp']);
                        $desc = $f['description'] ?: $f['file_type']; 
                    ?>
                        <div class="gallery-item" title="<?php echo $desc; ?>">
                            <a href="<?php echo $f['file_path']; ?>" target="_blank">
                                <?php if($is_img): ?><img src="<?php echo $f['file_path']; ?>" class="gallery-img"><?php else: ?><div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#222; font-size:2rem;">📄</div><?php endif; ?>
                            </a>
                            <form method="POST" onsubmit="return confirm('حذف الملف نهائياً من السيرفر؟');"><input type="hidden" name="file_id" value="<?php echo $f['id']; ?>"><button name="delete_file" class="del-btn">×</button></form>
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: echo "<span style='color:#555; font-size:0.8rem;'> لا يوجد ملفات مرفقة.</span>"; endif; ?>
            </div>

            <?php if($curr == 'briefing'): ?>
            <div class="royal-card">
                <h3 class="card-h">💡 صياغة الأفكار (Concepts)</h3>
                <p style="color:#aaa; margin-bottom:20px;">قم بصياغة فكرة منفصلة لكل منشور:</p>
                <form method="POST" enctype="multipart/form-data">
                    <?php 
                    $posts_q = $conn->query("SELECT * FROM social_posts WHERE job_id={$job['id']} ORDER BY post_index");
                    $loop_count = ($posts_q->num_rows > 0) ? $posts_q->num_rows : $posts_count;
                    for($i=0; $i<$loop_count; $i++): 
                        $p = ($posts_q->num_rows > 0) ? $posts_q->fetch_assoc() : null;
                        $idx = $p ? $p['post_index'] : ($i+1);
                        $pid = $p ? $p['id'] : 0; 
                        $val = $p ? $p['idea_text'] : '';
                        $status = $p['idea_status'] ?? 'pending';
                        $feedback = $p['idea_feedback'] ?? '';
                    ?>
                        <div class="post-card" style="<?php if($status=='idea_rejected') echo 'border:1px solid var(--royal-red);'; ?>">
                            <span class="post-badge">فكرة رقم #<?php echo $idx; ?></span>
                            <?php if($status == 'idea_rejected'): ?>
                                <div class="status-alert status-rejected">❌ مرفوضة: <?php echo $feedback; ?></div>
                            <?php elseif($status == 'idea_approved'): ?>
                                <div class="status-alert status-approved">✅ معتمدة</div>
                            <?php endif; ?>
                            <textarea name="ideas[<?php echo $pid ?: ($i+1); ?>]" rows="3" placeholder="اكتب الفكرة المقترحة لهذا البوست..."><?php echo $val; ?></textarea>
                        </div>
                    <?php endfor; ?>

                    <div style="margin-top:15px; background:#111; padding:10px; border-radius:6px;">
                        <label style="color:#aaa; display:block; margin-bottom:5px;">📂 رفع ملفات استدلالية (Moodboard):</label>
                        <input type="file" name="ref_files[]" multiple style="color:#fff; width:100%;">
                    </div>
                    <div class="action-bar">
                        <button type="submit" name="save_idea_batch" class="btn btn-gray">💾 حفظ مسودة</button>
                        <button type="submit" name="send_idea_batch" class="btn btn-gold">حفظ وإرسال للعميل للمراجعة ➡️</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if($curr == 'idea_review'): ?>
            <div class="royal-card" style="text-align:center; padding:40px;">
                <h2 style="color:#fff;">⏳</h2>
                <h3 style="color:var(--royal-gold);">بانتظار موافقة العميل على الأفكار</h3>
                <p style="color:#aaa;">تم إرسال الأفكار للعميل للمراجعة الفردية.</p>
                <div class="action-bar">
                    <a href="https://wa.me/<?php echo $job['client_phone']; ?>?text=<?php echo urlencode("مرحباً، يرجى مراجعة الأفكار المقترحة:\n$client_link"); ?>" target="_blank" class="btn" style="background:#25D366; text-decoration:none;">📱 تذكير عبر واتساب</a>
                    <form method="POST" style="display:inline;"><input type="hidden" name="prev_target" value="briefing"><textarea name="return_reason" style="display:none;">تراجع يدوي</textarea><button name="return_stage" class="btn btn-gray">↩️ تراجع للتعديل</button></form>
                </div>
            </div>
            <?php endif; ?>

            <?php if($curr == 'content_writing'): ?>
            <div class="royal-card">
                <h3 class="card-h">✍️ كتابة المحتوى (<?php echo $posts_count; ?> بوست)</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?php 
                    $posts_q = $conn->query("SELECT * FROM social_posts WHERE job_id={$job['id']} ORDER BY post_index");
                    while($p = $posts_q->fetch_assoc()): 
                        $pid = $p['id']; 
                        $val = $p['content_text'];
                        $idea = $p['idea_text'];
                        $status = $p['status'];
                        $feedback = $p['client_feedback'];
                    ?>
                        <div class="post-card" style="<?php if($status=='content_rejected') echo 'border:1px solid var(--royal-red);'; ?>">
                            <span class="post-badge">بوست #<?php echo $p['post_index']; ?></span>
                            
                            <div style="background:#1a1a1a; border-right:3px solid var(--royal-gold); padding:10px; margin-bottom:10px; color:#ddd; font-size:0.9rem;">
                                <strong style="color:var(--royal-gold);">💡 الفكرة المعتمدة:</strong><br>
                                <?php echo nl2br($idea); ?>
                            </div>

                            <?php if($status == 'content_rejected'): ?>
                                <div class="status-alert status-rejected">❌ ملاحظات العميل: <?php echo $feedback; ?></div>
                            <?php endif; ?>

                            <textarea name="content[<?php echo $pid; ?>]" rows="4" placeholder="اكتب الكابشن (Caption) ووصف التصميم هنا..."><?php echo $val; ?></textarea>
                        </div>
                    <?php endwhile; ?>
                    <div style="margin:15px 0; background:#111; padding:10px; border-radius:6px;">
                        <label style="color:#aaa;">📎 ملفات مساعدة (Word/Excel):</label>
                        <input type="file" name="content_docs[]" multiple style="color:#fff; display:block; margin-top:5px; width:100%;">
                    </div>
                    <div class="action-bar">
                        <button type="submit" name="save_content" class="btn btn-gray">💾 حفظ مسودة</button>
                        <button type="submit" name="send_content" class="btn btn-gold">حفظ وإرسال للمراجعة ➡️</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if($curr == 'content_review'): ?>
            <div class="royal-card">
                <h3 class="card-h">👁️ معاينة المحتوى المرسل</h3>
                <div style="background:#111; padding:15px; border-radius:8px; margin-bottom:20px; border:1px dashed #444;">
                    <p style="margin-top:0; color:#aaa; font-size:0.9rem;">🔗 رابط المراجعة:</p>
                    <input type="text" value="<?php echo $client_link; ?>" readonly style="width:100%; background:#000; color:var(--royal-gold); padding:10px; border:1px solid #333; direction:ltr;">
                </div>
                <div class="action-bar">
                    <a href="https://wa.me/<?php echo $job['client_phone']; ?>?text=<?php echo urlencode("تم تجهيز المحتوى، يرجى المراجعة:\n$client_link"); ?>" target="_blank" class="btn" style="background:#25D366; text-decoration:none;">📱 تذكير واتساب</a>
                    <form method="POST" style="flex:1;"><input type="hidden" name="prev_target" value="content_writing"><textarea name="return_reason" style="display:none;">تراجع يدوي</textarea><button name="return_stage" class="btn btn-gray">↩️ تراجع</button></form>
                </div>
            </div>
            <?php endif; ?>

            <?php if($curr == 'designing'): ?>
            <div class="royal-card">
                <h3 class="card-h">🎨 مرحلة التصميم (Studio)</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?php 
                    $posts_q = $conn->query("SELECT * FROM social_posts WHERE job_id={$job['id']} ORDER BY post_index");
                    while($p = $posts_q->fetch_assoc()): 
                        $images = [];
                        if(!empty($p['design_path'])) {
                            $decoded = json_decode($p['design_path'], true);
                            $images = is_array($decoded) ? $decoded : [$p['design_path']];
                        }
                    ?>
                        <div class="post-card" style="<?php if($p['status']=='design_rejected') echo 'border:1px solid var(--royal-red);'; ?>">
                            <span class="post-badge">تصميم بوست #<?php echo $p['post_index']; ?></span>
                            
                            <?php if($p['status'] == 'design_rejected'): ?>
                                <div class="status-alert status-rejected">❌ تعديل مطلوب: <?php echo $p['client_feedback']; ?></div>
                            <?php endif; ?>

                            <div style="display:flex; gap:15px; align-items:flex-start; flex-wrap:wrap;">
                                <div style="flex:1; min-width:250px;">
                                    <label style="color:#aaa; font-size:0.8rem;">رفع التصميم:</label>
                                    <input type="file" name="design_files[<?php echo $p['id']; ?>][]" multiple style="color:#fff; margin-top:5px; width:100%; border:1px dashed #555; padding:10px;">
                                </div>
                                
                                <?php if(!empty($images)): ?>
                                    <div class="preview-grid">
                                        <?php foreach($images as $img): ?>
                                            <div class="preview-img-box">
                                                <a href="<?php echo $img; ?>" target="_blank"><img src="<?php echo $img; ?>"></a>
                                                <button type="submit" name="delete_design_img" value="1" 
                                                        formaction="" 
                                                        onclick="this.form.post_id.value='<?php echo $p['id']; ?>'; this.form.img_path.value='<?php echo $img; ?>'; return confirm('حذف الصورة؟');"
                                                        style="position:absolute; top:0; right:0; background:rgba(200,0,0,0.8); color:#fff; border:none; cursor:pointer;">×</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="background:#151515; padding:12px; border-radius:6px; margin-top:15px; color:#ddd; font-size:0.9rem; border-top:2px solid var(--royal-gold);">
                                <strong>📜 المحتوى النصي المعتمد:</strong><br>
                                <?php echo nl2br($p['content_text']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
                    <input type="hidden" name="post_id" value="">
                    <input type="hidden" name="img_path" value="">

                    <div style="margin:20px 0; border-top:1px solid #333; padding-top:15px;">
                        <label style="color:#aaa;">📂 رفع ملفات المصدر (PSD/AI) - اختياري:</label>
                        <input type="file" name="source_files[]" multiple style="color:#fff; margin-top:5px; width:100%;">
                    </div>
                    <div class="action-bar">
                        <button type="submit" name="upload_designs" class="btn btn-gray">📤 حفظ الملفات فقط</button>
                        <button type="submit" name="send_designs" class="btn btn-gold">رفع وإرسال للمراجعة ➡️</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if($curr == 'design_review'): ?>
            <div class="royal-card">
                <h3 class="card-h">🖼️ معاينة التصاميم المرسلة</h3>
                <div style="background:#111; padding:15px; border-radius:8px; margin-bottom:20px; border:1px dashed #444;">
                    <p style="margin-top:0; color:#aaa; font-size:0.9rem;">🔗 رابط المراجعة:</p>
                    <input type="text" value="<?php echo $client_link; ?>" readonly style="width:100%; background:#000; color:var(--royal-gold); padding:10px; border:1px solid #333; direction:ltr;">
                </div>

                <div class="action-bar">
                    <a href="https://wa.me/<?php echo $job['client_phone']; ?>?text=<?php echo urlencode("تم رفع التصاميم، يرجى الاعتماد:\n$client_link"); ?>" target="_blank" class="btn" style="background:#25D366; text-decoration:none;">📱 تذكير واتساب</a>
                    <form method="POST" style="flex:1;"><input type="hidden" name="prev_target" value="designing"><textarea name="return_reason" style="display:none;">تراجع يدوي</textarea><button name="return_stage" class="btn btn-gray">↩️ تراجع</button></form>
                </div>
            </div>
            <?php endif; ?>

            <?php if($curr == 'publishing'): ?>
            <div class="royal-card">
                <h3 class="card-h">📅 النشر (المعاينة النهائية)</h3>
                <p style="color:#aaa; margin-bottom:20px;">تأكد من مطابقة الصور للنصوص قبل النشر النهائي:</p>
                
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:20px; margin-bottom:30px;">
                <?php 
                $posts_q = $conn->query("SELECT * FROM social_posts WHERE job_id={$job['id']} ORDER BY post_index");
                while($p = $posts_q->fetch_assoc()): 
                    $images = !empty($p['design_path']) ? json_decode($p['design_path'], true) : [];
                    $cover = $images[0] ?? '';
                ?>
                    <div class="post-card" style="margin:0; height:100%;">
                        <span class="post-badge">#<?php echo $p['post_index']; ?></span>
                        <?php if($cover): ?>
                            <div style="height:200px; background:#111; display:flex; align-items:center; justify-content:center; overflow:hidden; border-radius:6px; margin-bottom:10px;">
                                <a href="<?php echo $cover; ?>" target="_blank"><img src="<?php echo $cover; ?>" style="max-width:100%; max-height:200px;"></a>
                            </div>
                        <?php else: ?><div style="height:200px; background:#111; color:#555; display:flex; align-items:center; justify-content:center;">بلا تصميم</div><?php endif; ?>
                        <div style="font-size:0.85rem; color:#ccc; max-height:100px; overflow-y:auto;"><?php echo nl2br($p['content_text']); ?></div>
                    </div>
                <?php endwhile; ?>
                </div>

                <form method="POST">
                    <button name="finish_publishing" class="btn btn-gold">✅ تأكيد النشر وإغلاق العملية</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if($curr == 'accounting'): ?>
            <div class="royal-card" style="text-align:center; padding:40px;">
                <h2 style="color:var(--royal-green); font-size:2rem;">💰 قسم الحسابات</h2>
                <div class="action-bar" style="justify-content:center;">
                    <a href="invoices.php" class="btn btn-gray" style="display:inline-block; width:auto; text-decoration:none;">عرض الفاتورة</a>
                    <form method="POST" style="display:inline-block;">
                        <button name="archive_job" class="btn btn-gold" style="width:auto;">✅ إنهاء وأرشفة نهائية</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if($curr == 'completed'): ?>
            <div class="royal-card" style="text-align:center; padding:40px;">
                <h2 style="color:var(--royal-green); font-size:2rem;">✅ العملية مكتملة ومؤرشفة</h2>
                <div style="margin-top:30px; border-top:1px solid #333; padding-top:20px;">
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من إعادة فتح العملية؟');">
                        <button name="reopen_job" class="btn btn-red" style="width:auto; padding:10px 30px;">🔄 إعادة فتح العملية</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ob_end_flush(); ?>