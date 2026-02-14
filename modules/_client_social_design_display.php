<?php
// modules/_client_social_design_display.php (V1.0)
// Displays a single social media post with its designs and content for client approval.
// Expects a `$post` variable (array) to be in the current scope.

$images = [];
if (!empty($post['design_path'])) {
    $decoded = json_decode($post['design_path'], true);
    $images = is_array($decoded) ? $decoded : [$post['design_path']]; 
}
?>

<style>
    .social-design-card { background: #1a1a1a; border-radius: 8px; margin-bottom: 20px; border: 1px solid #333; overflow:hidden; }
    .social-design-header { padding: 15px; border-bottom: 1px solid #444; background: #222;}
    .social-design-body { display: flex; flex-wrap: wrap; gap: 20px; padding: 20px; }
    .social-design-gallery { flex: 1; min-width: 250px; }
    .social-design-content { flex: 2; min-width: 300px; }
    .design-image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; }
    .design-thumb-link { display:block; height: 80px; border-radius: 6px; overflow: hidden; border: 2px solid #555; }
    .design-thumb-link img { width: 100%; height: 100%; object-fit: cover; }
</style>

<div class="social-design-card">
    <div class="social-design-header">
        <h4 class="card-title" style="margin:0; font-size: 1.1rem;">🎨 تصميم البوست رقم #<?php echo $post['post_index']; ?></h4>
    </div>
    <div class="social-design-body">
        <div class="social-design-gallery">
            <p style="color:#aaa; margin:0 0 10px 0; font-size:0.9rem;"><strong>المرفقات المرئية:</strong></p>
            <?php if(!empty($images)): ?>
                <div class="design-image-grid">
                    <?php foreach($images as $img): ?>
                        <a href="<?php echo htmlspecialchars($img); ?>" target="_blank" class="design-thumb-link">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="Design variant">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color:#666;">لا توجد مرفقات.</p>
            <?php endif; ?>
        </div>
        <div class="social-design-content">
             <p style="color:#aaa; margin:0 0 10px 0; font-size:0.9rem;"><strong>النص المعتمد:</strong></p>
            <div class="content-box" style="margin-bottom: 15px;">
                <?php echo nl2br(htmlspecialchars($post['content_text'])); ?>
            </div>
        </div>
    </div>
    <div class="feedback-form control-panel" style="padding: 20px; border-top: 1px dashed #444; background: #111;">
        <label class="radio-label"><input type="radio" name="item_status[<?php echo $post['id']; ?>]" value="design_approved" checked><span>✅ موافقة على التصميم والمحتوى</span></label>
        <label class="radio-label"><input type="radio" name="item_status[<?php echo $post['id']; ?>]" value="design_rejected"><span>❌ طلب تعديل</span></label>
        <textarea name="item_feedback[<?php echo $post['id']; ?>]" placeholder="اكتب ملاحظاتك على التصميم هنا..."></textarea>
    </div>
</div>
