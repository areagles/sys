<?php
// client_review.php - (Arab Eagles Portal V52.1 - Final Integrated Version + Full Protection)
require 'config.php';

// --- 1. التحقق من التوكن والأمان ---
if(!isset($_GET['token']) || empty($_GET['token'])) 
    die("<div style='background:#000;height:100vh;display:flex;align-items:center;justify-content:center;'><h3 style='color:#d4af37;font-family:Cairo;'>⛔ الرابط غير صالح</h3></div>");

$token = $conn->real_escape_string($_GET['token']);

// --- [هام جداً] إصلاح ترميز الأحرف لقراءة العربية بشكل صحيح ---
$conn->set_charset("utf8mb4");

// جلب بيانات الطلب والعميل
$sql = "SELECT j.*, c.name as client_name FROM job_orders j JOIN clients c ON j.client_id = c.id WHERE j.access_token = '$token'";
$res = $conn->query($sql);

if($res->num_rows == 0) 
    die("<div style='background:#000;height:100vh;display:flex;align-items:center;justify-content:center;'><h3 style='color:#d4af37;font-family:Cairo;'>⛔ الرابط منتهي أو غير صحيح</h3></div>");

$job = $res->fetch_assoc();
$job_id = $job['id'];
$job_type = $job['job_type'];
$curr = $job['current_stage'];
$client_name = $job['client_name'];

// تسجيل وقت المشاهدة
$conn->query("UPDATE job_orders SET client_viewed_at = NOW() WHERE id = $job_id AND client_viewed_at IS NULL");

// --- 2. معالجة الردود (Backend Processing) ---
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- أ) معالجة السوشيال ميديا ---
    if ($job_type == 'social') {
        
        // 1. مراجعة الأفكار (Batch Review) - النظام الجديد
        if (isset($_POST['review_idea_batch'])) {
            $has_rejection = false;
            $all_valid = true;
            foreach ($_POST['status'] as $pid => $st) {
                $reason = isset($_POST['reason'][$pid]) ? $conn->real_escape_string($_POST['reason'][$pid]) : '';
                if ($st == 'rejected' && empty($reason)) { $all_valid = false; break; }
                
                if ($st == 'rejected') {
                    $has_rejection = true;
                    $conn->query("UPDATE social_posts SET idea_status='idea_rejected', idea_feedback='$reason' WHERE id=$pid");
                } else {
                    $conn->query("UPDATE social_posts SET idea_status='idea_approved', idea_feedback=NULL WHERE id=$pid");
                }
            }
            if ($all_valid) {
                // إذا كان هناك رفض نعود لصفحة الأفكار (briefing)، وإلا ننتقل لكتابة المحتوى
                $next = $has_rejection ? 'briefing' : 'content_writing';
                $conn->query("UPDATE job_orders SET current_stage='$next' WHERE id=$job_id");
                header("Location: client_review.php?token=$token&done=1"); exit;
            }
        } 
        // 2. مراجعة المحتوى والتصميم (كما هي)
        elseif (isset($_POST['review_content_batch']) || isset($_POST['review_design_batch'])) {
            $has_rejection = false;
            $type = isset($_POST['review_content_batch']) ? 'content' : 'design';
            $all_valid = true;
            foreach ($_POST['status'] as $pid => $st) {
                $reason = isset($_POST['reason'][$pid]) ? $conn->real_escape_string($_POST['reason'][$pid]) : '';
                if ($st == 'rejected' && empty($reason)) { $all_valid = false; break; }
                if ($st == 'rejected') {
                    $has_rejection = true;
                    $conn->query("UPDATE social_posts SET status='".$type."_rejected', client_feedback='$reason' WHERE id=$pid");
                } else {
                    $conn->query("UPDATE social_posts SET status='".$type."_approved', client_feedback=NULL WHERE id=$pid");
                }
            }
            if ($all_valid) {
                $next = ($type == 'content') ? ($has_rejection ? 'content_writing' : 'designing') : ($has_rejection ? 'designing' : 'publishing');
                $conn->query("UPDATE job_orders SET current_stage='$next' WHERE id=$job_id");
                header("Location: client_review.php?token=$token&done=1"); exit;
            }
        }
    } 
    // --- ب) معالجة التصميم فقط ---
    elseif ($job_type == 'design_only' && isset($_POST['review_design_only_batch'])) {
        $all_valid = true;
        foreach ($_POST['status'] as $pid => $st) {
            $reason = isset($_POST['reason'][$pid]) ? $conn->real_escape_string($_POST['reason'][$pid]) : '';
            if ($st == 'rejected' && empty($reason)) { $all_valid = false; break; }
            $conn->query("UPDATE job_proofs SET status='$st', client_comment='$reason' WHERE id=$pid");
        }
        if ($all_valid) { header("Location: client_review.php?token=$token&done=1"); exit; }
    } 
    // --- ج) معالجة الدفعات العامة ---
    elseif (isset($_POST['review_generic_batch'])) {
        $all_valid = true;
        foreach ($_POST['status'] as $proof_id => $action) {
            $comment = isset($_POST['reason'][$proof_id]) ? $conn->real_escape_string($_POST['reason'][$proof_id]) : '';
            if ($action == 'rejected' && empty(trim($comment))) { $all_valid = false; break; }
            $conn->query("UPDATE job_proofs SET status='$action', client_comment='$comment' WHERE id=$proof_id");
        }
        if ($all_valid) { header("Location: client_review.php?token=$token&done=1"); exit; }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة العملاء | Arab Eagles</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --gold-gradient: linear-gradient(135deg, #d4af37 0%, #AA8E2F 100%);
            --dark-bg: #050505;
            --panel-bg: #121212;
            --text-main: #ffffff;
            --text-sub: #b0b0b0;
            --green: #27ae60;
            --red: #c0392b;
            --border-radius: 12px;
        }

        body { 
            background-color: var(--dark-bg); 
            color: var(--text-main); 
            font-family: 'Cairo', sans-serif; 
            margin: 0; padding: 20px; padding-bottom: 100px;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(212, 175, 55, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(212, 175, 55, 0.05) 0%, transparent 20%);
        }

        .container { max-width: 900px; margin: 0 auto; }
        
        header { text-align: center; margin-bottom: 40px; padding-top: 20px; position: relative; }
        .brand-name { font-size: 2.5rem; font-weight: 900; margin: 0; letter-spacing: 1px; color: var(--gold); text-transform: uppercase; }
        .welcome-msg { font-size: 1.1rem; color: var(--text-sub); margin-top: 5px; }

        /* البطاقات */
        .royal-card { 
            background: var(--panel-bg); 
            border: 1px solid #333; 
            border-radius: var(--border-radius); 
            margin-bottom: 30px; 
            overflow: hidden; 
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .royal-card:hover { border-color: #444; }
        .royal-card.error-highlight { border: 1px solid var(--red); box-shadow: 0 0 15px rgba(192, 57, 43, 0.3); }

        .card-header { 
            background: linear-gradient(90deg, #1a1a1a 0%, #252525 100%); 
            padding: 15px 25px; 
            border-bottom: 1px solid #333; 
        }
        .card-title { color: var(--gold); font-weight: 700; font-size: 1.15rem; }
        .card-body { padding: 25px; }

        .content-display { 
            background: #080808; padding: 20px; border-radius: 8px; 
            border: 1px dashed #444; line-height: 1.8; font-size: 1rem; 
            color: #ddd; white-space: pre-wrap; margin-bottom: 20px; 
        }

        /* Post Preview */
        .post-full-preview { background: #111; border-radius: 8px; overflow: hidden; margin-bottom: 20px; border: 1px solid #333; }
        .post-text-part { padding: 20px; border-bottom: 1px solid #333; font-size: 1rem; line-height: 1.6; color: #eee; }

        /* Carousel */
        .carousel-wrapper {
            display: flex; overflow-x: auto; gap: 10px; padding: 15px; 
            background: #000; scroll-behavior: smooth;
        }
        .carousel-wrapper::-webkit-scrollbar { height: 6px; }
        .carousel-wrapper::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
        .carousel-slide { flex: 0 0 auto; max-width: 100%; border: 1px solid #333; overflow: hidden; }
        .carousel-slide img { display: block; max-height: 350px; width: auto; max-width: 100%; object-fit: contain; }

        /* Controls */
        .control-panel { margin-top: 20px; padding-top: 15px; border-top: 1px solid #222; }
        .radio-group { display: flex; gap: 10px; margin-bottom: 10px; }
        .radio-option { flex: 1; }
        .radio-option input { display: none; }
        .radio-option label { 
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px; width: 100%; cursor: pointer; border-radius: 8px; 
            background: #181818; border: 1px solid #333; color: #888; font-weight: bold; transition: 0.2s;
            box-sizing: border-box;
        }
        .radio-option input:checked + label[data-type="approve"] { background: rgba(39, 174, 96, 0.2); color: var(--green); border-color: var(--green); }
        .radio-option input:checked + label[data-type="reject"] { background: rgba(192, 57, 43, 0.2); color: var(--red); border-color: var(--red); }
        
        textarea.reject-input { 
            width: 100%; background: #080808; border: 1px solid var(--red); color: #fff; 
            padding: 15px; border-radius: 8px; display: none; font-family: 'Cairo'; 
            box-sizing: border-box; min-height: 80px; resize: vertical;
        }

        .submit-sticky {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: rgba(10, 10, 10, 0.95); backdrop-filter: blur(10px);
            padding: 15px; border-top: 1px solid #333;
            text-align: center; z-index: 1000;
        }
        .btn-main {
            background: var(--gold-gradient); color: #000; border: none; 
            padding: 12px 60px; font-size: 1.1rem; font-weight: bold; 
            border-radius: 50px; cursor: pointer; font-family: 'Cairo';
            box-shadow: 0 5px 20px rgba(212, 175, 55, 0.2); transition: 0.3s;
        }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4); }

        .validation-error { color: var(--red); font-size: 0.9rem; margin-top: 5px; display: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1 class="brand-name">🦅 ARAB EAGLES</h1>
        <p class="welcome-msg">مرحباً بك، <?php echo htmlspecialchars($client_name); ?></p>
        <p style="font-size: 0.9rem; color: #666;">مشروع: <?php echo htmlspecialchars($job['job_name']); ?></p>
    </header>

    <?php if(isset($_GET['done'])): ?>
        <div style="text-align:center; padding:100px 20px;">
            <div style="font-size:5rem; margin-bottom:20px;">✅</div>
            <h1 style="color:var(--gold);">تم استلام ردك بنجاح</h1>
            <p style="color:#bbb; max-width: 500px; margin: 20px auto;">شكراً لتعاونك. سيقوم الفريق بالعمل على ملاحظاتك فوراً.</p>
            <a href="#" onclick="window.close()" class="btn-main" style="text-decoration:none; display:inline-block;">إغلاق الصفحة</a>
        </div>
    <?php else: ?>

        <form method="POST" id="reviewForm" onsubmit="return validateForm()">

        <?php if($job_type == 'social' && $curr == 'idea_review'): ?>
            <?php 
            // تأمين الاستعلام للتأكد من جلب البوستات بشكل صحيح
            $posts = $conn->query("SELECT * FROM social_posts WHERE job_id='$job_id' ORDER BY post_index"); 
            while($p = $posts->fetch_assoc()): 
            ?>
            <div class="royal-card item-card" data-id="<?php echo $p['id']; ?>">
                <div class="card-header"><span class="card-title">💡 فكرة بوست رقم #<?php echo $p['post_index']; ?></span></div>
                <div class="card-body">
                    <div class="content-display"><?php echo nl2br(htmlspecialchars($p['idea_text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
                    <div class="control-panel">
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="i_app_<?php echo $p['id']; ?>" value="approved" onchange="toggleReason('ir_<?php echo $p['id']; ?>', false)">
                                <label for="i_app_<?php echo $p['id']; ?>" data-type="approve">✅ اعتماد</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="i_rej_<?php echo $p['id']; ?>" value="rejected" onchange="toggleReason('ir_<?php echo $p['id']; ?>', true)">
                                <label for="i_rej_<?php echo $p['id']; ?>" data-type="reject">❌ تعديل</label>
                            </div>
                        </div>
                        <textarea name="reason[<?php echo $p['id']; ?>]" id="ir_<?php echo $p['id']; ?>" class="reject-input" placeholder="سبب رفض الفكرة..."></textarea>
                        <div class="validation-error">⚠️ مطلوب قرار.</div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <input type="hidden" name="review_idea_batch" value="1">

        <?php elseif($job_type == 'social' && $curr == 'content_review'): ?>
            <?php 
            $posts = $conn->query("SELECT * FROM social_posts WHERE job_id='$job_id' ORDER BY post_index"); 
            while($p = $posts->fetch_assoc()): 
            ?>
            <div class="royal-card item-card" data-id="<?php echo $p['id']; ?>">
                <div class="card-header"><span class="card-title">📝 محتوى بوست رقم #<?php echo $p['post_index']; ?></span></div>
                <div class="card-body">
                    <div class="content-display"><?php echo nl2br(htmlspecialchars($p['content_text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
                    <div class="control-panel">
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="c_app_<?php echo $p['id']; ?>" value="approved" onchange="toggleReason('cr_<?php echo $p['id']; ?>', false)">
                                <label for="c_app_<?php echo $p['id']; ?>" data-type="approve">✅ اعتماد</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="c_rej_<?php echo $p['id']; ?>" value="rejected" onchange="toggleReason('cr_<?php echo $p['id']; ?>', true)">
                                <label for="c_rej_<?php echo $p['id']; ?>" data-type="reject">❌ تعديل</label>
                            </div>
                        </div>
                        <textarea name="reason[<?php echo $p['id']; ?>]" id="cr_<?php echo $p['id']; ?>" class="reject-input" placeholder="التعديل المطلوب..."></textarea>
                        <div class="validation-error">⚠️ مطلوب قرار.</div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <input type="hidden" name="review_content_batch" value="1">

        <?php elseif($job_type == 'social' && $curr == 'design_review'): ?>
            <?php $posts = $conn->query("SELECT * FROM social_posts WHERE job_id=$job_id ORDER BY post_index"); while($p = $posts->fetch_assoc()): 
                $images = []; if (!empty($p['design_path'])) { $decoded = json_decode($p['design_path'], true); if (is_array($decoded)) { $images = $decoded; } else { $images[] = $p['design_path']; } }
            ?>
            <div class="royal-card item-card" data-id="<?php echo $p['id']; ?>">
                <div class="card-header"><span class="card-title">🎨 معاينة نهائية - بوست #<?php echo $p['post_index']; ?></span></div>
                <div class="card-body">
                    
                    <div class="post-full-preview">
                        <div class="post-text-part">
                            <strong style="color:var(--gold); display:block; margin-bottom:10px;">📄 النص المعتمد:</strong>
                            <?php echo nl2br(htmlspecialchars($p['content_text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?>
                        </div>
                        <?php if(!empty($images)): ?>
                            <div class="carousel-wrapper">
                                <?php foreach($images as $img_path): ?>
                                    <div class="carousel-slide"><a href="<?php echo $img_path; ?>" target="_blank"><img src="<?php echo $img_path; ?>" alt="Design Preview"></a></div>
                                <?php endforeach; ?>
                            </div>
                            <?php if(count($images) > 1): ?><div style="text-align:center; font-size:0.8rem; color:#666; padding:5px;">(اسحب لرؤية باقي الصور)</div><?php endif; ?>
                        <?php else: ?><div style="padding:20px; text-align:center;">🚫 لا يوجد تصميم</div><?php endif; ?>
                    </div>

                    <div class="control-panel">
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="d_app_<?php echo $p['id']; ?>" value="approved" onchange="toggleReason('dr_<?php echo $p['id']; ?>', false)">
                                <label for="d_app_<?php echo $p['id']; ?>" data-type="approve">✅ اعتماد نهائي</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="d_rej_<?php echo $p['id']; ?>" value="rejected" onchange="toggleReason('dr_<?php echo $p['id']; ?>', true)">
                                <label for="d_rej_<?php echo $p['id']; ?>" data-type="reject">❌ طلب تعديل</label>
                            </div>
                        </div>
                        <textarea name="reason[<?php echo $p['id']; ?>]" id="dr_<?php echo $p['id']; ?>" class="reject-input" placeholder="ملاحظات التعديل (على التصميم أو النص)..."></textarea>
                        <div class="validation-error">⚠️ مطلوب قرار.</div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <input type="hidden" name="review_design_batch" value="1">

        <?php elseif($job_type == 'design_only'): ?>
            <?php 
            $proofs = $conn->query("SELECT * FROM job_proofs WHERE job_id=$job_id AND status='pending'");
            if($proofs->num_rows > 0):
                while($p = $proofs->fetch_assoc()):
                    $ext = strtolower(pathinfo($p['file_path'], PATHINFO_EXTENSION));
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            ?>
            <div class="royal-card item-card">
                <div class="card-header"><span class="card-title">🎨 مراجعة تصميم: <?php echo $p['description']; ?></span></div>
                <div class="card-body">
                    <div style="background:#0a0a0a; border:1px solid #333; border-radius:8px; padding:15px; margin-bottom:20px; text-align:center;">
                        <?php if($is_image): ?>
                            <a href="<?php echo $p['file_path']; ?>" target="_blank"><img src="<?php echo $p['file_path']; ?>" style="max-width:100%; border-radius:5px; max-height:400px;"></a>
                        <?php else: ?>
                            <div style="padding:20px;">
                                <h3 style="color:#fff;"><?php echo basename($p['file_path']); ?></h3>
                                <a href="<?php echo $p['file_path']; ?>" target="_blank" class="btn-main" style="text-decoration:none; display:inline-block; font-size:0.9rem;">👁️ تحميل / معاينة</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="control-panel">
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="do_app_<?php echo $p['id']; ?>" value="approved" onchange="toggleReason('dor_<?php echo $p['id']; ?>', false)">
                                <label for="do_app_<?php echo $p['id']; ?>" data-type="approve">✅ اعتماد التصميم</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="do_rej_<?php echo $p['id']; ?>" value="rejected" onchange="toggleReason('dor_<?php echo $p['id']; ?>', true)">
                                <label for="do_rej_<?php echo $p['id']; ?>" data-type="reject">❌ طلب تعديلات</label>
                            </div>
                        </div>
                        <textarea name="reason[<?php echo $p['id']; ?>]" id="dor_<?php echo $p['id']; ?>" class="reject-input" placeholder="ملاحظات التعديل..."></textarea>
                        <div class="validation-error">⚠️ مطلوب قرار.</div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <input type="hidden" name="review_design_only_batch" value="1">
            <?php else: ?>
                <div style="text-align:center; padding:50px; color:#777;">⏳ لا توجد تصاميم معلقة.</div>
            <?php endif; ?>

        <?php else: ?>
            <?php 
            $proofs = $conn->query("SELECT * FROM job_proofs WHERE job_id=$job_id AND status='pending' ORDER BY id DESC");
            if($proofs->num_rows > 0):
                while($p = $proofs->fetch_assoc()):
                    $ext = strtolower(pathinfo($p['file_path'], PATHINFO_EXTENSION));
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            ?>
            <div class="royal-card item-card">
                <div class="card-header"><span class="card-title">📂 مراجعة ملف: <?php echo $p['description']; ?></span></div>
                <div class="card-body">
                    <div style="background:#0a0a0a; border:1px solid #333; border-radius:8px; padding:15px; margin-bottom:20px; text-align:center;">
                        <?php if($is_image): ?>
                            <a href="<?php echo $p['file_path']; ?>" target="_blank"><img src="<?php echo $p['file_path']; ?>" style="max-width:100%; max-height:300px;"></a>
                        <?php else: ?>
                            <a href="<?php echo $p['file_path']; ?>" target="_blank" class="btn-main" style="text-decoration:none; display:inline-block; font-size:0.9rem;">👁️ تحميل الملف</a>
                        <?php endif; ?>
                    </div>
                    <div class="control-panel">
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="g_app_<?php echo $p['id']; ?>" value="approved" onchange="toggleReason('gr_<?php echo $p['id']; ?>', false)">
                                <label for="g_app_<?php echo $p['id']; ?>" data-type="approve">✅ اعتماد</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="status[<?php echo $p['id']; ?>]" id="g_rej_<?php echo $p['id']; ?>" value="rejected" onchange="toggleReason('gr_<?php echo $p['id']; ?>', true)">
                                <label for="g_rej_<?php echo $p['id']; ?>" data-type="reject">❌ تعديل</label>
                            </div>
                        </div>
                        <textarea name="reason[<?php echo $p['id']; ?>]" id="gr_<?php echo $p['id']; ?>" class="reject-input" placeholder="ملاحظات..."></textarea>
                        <div class="validation-error">⚠️ مطلوب قرار.</div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <input type="hidden" name="review_generic_batch" value="1">
            <?php else: ?>
                <div style="text-align:center; padding:50px; color:#777;">لا توجد عناصر للمراجعة حالياً.</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php 
        // عرض زر الإرسال إذا كان هناك أي محتوى معروض
        $show_submit = false;
        if($job_type == 'social' && in_array($curr, ['idea_review','content_review','design_review'])) $show_submit = true;
        elseif(isset($proofs) && $proofs->num_rows > 0) $show_submit = true;
        
        if($show_submit): ?>
            <div class="submit-sticky">
                <button type="submit" class="btn-main">إرسال الرد النهائي 🚀</button>
            </div>
        <?php endif; ?>

        </form>
    <?php endif; ?>
</div>

<script>
    function toggleReason(elementId, show) {
        const el = document.getElementById(elementId);
        if(show) { el.style.display = 'block'; el.focus(); } 
        else { el.style.display = 'none'; el.value = ''; }
    }

    function validateForm() {
        let isValid = true;
        let firstError = null;
        const cards = document.querySelectorAll('.item-card');
        
        cards.forEach(card => {
            card.classList.remove('error-highlight');
            const errorMsg = card.querySelector('.validation-error');
            if(errorMsg) errorMsg.style.display = 'none';

            const approved = card.querySelector('input[value="approved"]:checked');
            const rejected = card.querySelector('input[value="rejected"]:checked');
            
            if (!approved && !rejected) {
                isValid = false;
                card.classList.add('error-highlight');
                if(errorMsg) errorMsg.style.display = 'block';
                if(!firstError) firstError = card;
            } else if (rejected) {
                const reasonBox = card.querySelector('textarea');
                if (!reasonBox || reasonBox.value.trim() === "") {
                    isValid = false;
                    card.classList.add('error-highlight');
                    if(errorMsg) errorMsg.style.display = 'block';
                    if(!firstError) firstError = reasonBox;
                }
            }
        });

        if (!isValid) {
            if(firstError) firstError.scrollIntoView({behavior: 'smooth', block: 'center'});
            return false;
        }
        return confirm("هل أنت متأكد من إرسال الرد النهائي؟");
    }
</script>

<style>
    /* منع تحديد النصوص وسحب الصور */
    body {
        -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;
    }
    img {
        pointer-events: none; /* يمنع التفاعل تماماً مع الصور */
        -webkit-user-drag: none; -khtml-user-drag: none; -moz-user-drag: none; -o-user-drag: none;
    }
    /* طبقة شفافة فوق الصور لمنع الحفظ (احتياطي) */
    .carousel-slide, .royal-card img { position: relative; }
    .carousel-slide::after, .royal-card img::after {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 99;
    }
</style>

<script>
    // 1. منع الزر الأيمن (Context Menu)
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });

    // 2. منع اختصارات لوحة المفاتيح (Save, Print, DevTools)
    document.addEventListener('keydown', function(e) {
        // Ctrl+S (Save), Ctrl+P (Print), Ctrl+U (Source), F12 (DevTools)
        if ((e.ctrlKey && (e.key === 's' || e.key === 'S' || e.key === 'p' || e.key === 'P' || e.key === 'u' || e.key === 'U')) || e.key === 'F12') {
            e.preventDefault();
            e.stopPropagation();
        }
        // محاولة منع زر Print Screen (يعمل في بعض المتصفحات فقط)
        if (e.key === 'PrintScreen') {
            navigator.clipboard.writeText(''); // محاولة مسح الحافظة
            alert('⚠️ لقطة الشاشة غير مسموحة حفاظاً على الحقوق.');
            // إخفاء المحتوى فوراً (تكتيك متقدم)
            document.body.style.display = 'none';
            setTimeout(() => document.body.style.display = 'block', 1000);
        }
    });

    // 3. منع روابط الصور من الفتح في نافذة جديدة (لإجبار العميل على البقاء هنا)
    document.addEventListener("DOMContentLoaded", function() {
        const imgLinks = document.querySelectorAll('a[href$=".jpg"], a[href$=".png"], a[href$=".jpeg"], a[href$=".webp"]');
        imgLinks.forEach(link => {
            link.removeAttribute('target'); // إلغاء الفتح في نافذة جديدة
            link.href = 'javascript:void(0);'; // تعطيل الرابط
            link.style.cursor = 'default';
            // إذا ضغط العميل، لا يحدث شيء (لأن الصورة معروضة بالفعل أمامه)
            link.onclick = function(e) { e.preventDefault(); };
        });
    });
</script>

</body>
</html>