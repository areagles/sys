<?php
// modules/designer_room_snippet.php - غرفة المصمم (مشارك بين جميع الأقسام)
// هذا الملف يتم تضمينه داخل الموديولات الأخرى
?>

<div style="background:#222; padding:15px; border-radius:8px; border-right:4px solid var(--gold);">
    <h4 style="margin-top:0; color:var(--gold);">🎨 غرفة المصمم (مراجعة العميل)</h4>
    
    <form method="POST" enctype="multipart/form-data" style="margin-bottom:20px; border-bottom:1px solid #444; padding-bottom:15px;">
        <label style="color:#fff;">رفع ملف/بروفة جديدة:</label>
        <div style="display:flex; gap:10px; margin-top:5px;">
            <input type="text" name="proof_desc" placeholder="وصف (مثال: الشعار - الخيار الأول)" style="background:#000; color:#fff; border:1px solid #444; padding:8px; flex:1;" required>
            <input type="file" name="proof_file" style="color:#fff;" required>
        </div>
        <button type="submit" name="upload_proof" class="btn-royal" style="width:auto; padding:8px 20px; margin-top:10px; font-size:0.9rem;">📤 رفع وحفظ</button>
    </form>

    <div style="margin-bottom:20px;">
        <strong style="color:#aaa; font-size:0.9rem;">الملفات المرفوعة:</strong>
        <?php 
        // إنشاء الجدول إذا لم يوجد (للحماية)
        $conn->query("CREATE TABLE IF NOT EXISTS job_proofs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(50),
            description VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending',
            client_comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $proofs = $conn->query("SELECT * FROM job_proofs WHERE job_id={$job['id']}");
        if($proofs && $proofs->num_rows > 0):
            while($p = $proofs->fetch_assoc()):
                $st_color = ($p['status']=='approved')?'#2ecc71':(($p['status']=='rejected')?'#e74c3c':'#f39c12');
                $st_text = ($p['status']=='approved')?'معتمد':(($p['status']=='rejected')?'مرفوض':'قيد الانتظار');
        ?>
            <div style="background:#111; padding:8px; margin-top:5px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <a href="<?php echo $p['file_path']; ?>" target="_blank" style="color:#fff; text-decoration:none;">📄 <?php echo $p['description']; ?></a>
                    <span style="font-size:0.8rem; color:<?php echo $st_color; ?>; margin-right:10px;">(<?php echo $st_text; ?>)</span>
                </div>
                <?php if($p['status'] == 'rejected'): ?>
                    <div style="color:#e74c3c; font-size:0.8rem;">سبب الرفض: <?php echo $p['client_comment']; ?></div>
                <?php endif; ?>
            </div>
        <?php endwhile; else: echo "<div style='color:#666; font-size:0.8rem;'>لم يتم رفع ملفات بعد.</div>"; endif; ?>
    </div>

    <?php 
    if(empty($job['access_token'])) {
        $new_token = bin2hex(random_bytes(16));
        $conn->query("UPDATE job_orders SET access_token='$new_token' WHERE id={$job['id']}");
        $job['access_token'] = $new_token;
    }
    $client_link = "http://" . $_SERVER['HTTP_HOST'] . "/client_review.php?token=" . $job['access_token'];
    $wa_msg = "مرحباً عزيزي العميل،\nبخصوص مشروع ({$job['job_name']}).\nيرجى التكرم بالدخول للرابط التالي لمراجعة التصميمات والموافقة عليها:\n$client_link";
    ?>
    
    <div style="background:#000; padding:10px; border-radius:5px; border:1px dashed #444;">
        <p style="margin:0 0 5px 0; color:#aaa; font-size:0.9rem;">رابط المراجعة للعميل:</p>
        <input type="text" value="<?php echo $client_link; ?>" readonly style="width:100%; background:#222; color:#0f0; border:none; padding:5px; font-size:0.8rem; direction:ltr; text-align:left;">
        
        <a href="https://wa.me/<?php echo $job['client_phone']; ?>?text=<?php echo urlencode($wa_msg); ?>" target="_blank" class="btn-royal" style="display:block; text-align:center; text-decoration:none; margin-top:10px; background:#25D366; color:#fff;">
            📱 إرسال للعميل عبر واتساب
        </a>
    </div>
</div>

<?php
// 4. معالجة الرفع (Backend Logic)
if(isset($_POST['upload_proof']) && !empty($_FILES['proof_file']['name'])){
    $desc = $conn->real_escape_string($_POST['proof_desc']);
    
    if (!file_exists('uploads/proofs')) mkdir('uploads/proofs', 0777, true);
    $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
    $filename = "proof_" . time() . "_" . rand(100,999) . ".$ext";
    $target = "uploads/proofs/" . $filename;
    
    if(move_uploaded_file($_FILES['proof_file']['tmp_name'], $target)){
        $conn->query("INSERT INTO job_proofs (job_id, file_path, description, file_type) VALUES ({$job['id']}, '$target', '$desc', '$ext')");
        echo "<script>window.location.href = window.location.href;</script>";
    }
}
?>