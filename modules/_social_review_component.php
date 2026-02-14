<?php
// modules/_social_review_component.php (V1.0)
// A specialized component for reviewing social media posts.
// This component handles multiple images per post and text-based feedback.

// Expects a $post variable to be available in the scope where it's included.
// $post should be an associative array representing a row from the `social_posts` table.

$images = [];
if (!empty($post['design_path'])) {
    $decoded = json_decode($post['design_path'], true);
    $images = is_array($decoded) ? $decoded : [$post['design_path']]; // Handle legacy single-image strings
}

$status = $post['status'] ?? 'pending_design_review';
$feedback = $post['client_feedback'] ?? '';

$status_map = [
    'pending_design_review' => ['label' => '⏳ بانتظار المراجعة', 'color' => '#ffc107'],
    'design_approved' => ['label' => '✅ معتمد', 'color' => '#27ae60'],
    'design_rejected' => ['label' => '❌ تعديل مطلوب', 'color' => '#e74c3c'],
];

$current_status = $status_map[$status] ?? $status_map['pending_design_review'];
?>

<style>
    .social-post-review-card {
        background: #0a0a0a;
        border: 1px solid #333;
        border-left: 4px solid <?php echo $current_status['color']; ?>;
        border-radius: 8px;
        margin-bottom: 20px;
        overflow: hidden;
    }
    .social-post-header {
        padding: 15px;
        background: #1a1a1a;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .social-post-title {
        color: #fff;
        font-size: 1.1rem;
        font-weight: bold;
    }
    .social-post-status {
        font-size: 0.9rem;
        font-weight: bold;
        color: <?php echo $current_status['color']; ?>;
    }
    .social-post-body {
        display: flex;
        gap: 20px;
        padding: 20px;
    }
    .social-image-gallery {
        flex-basis: 300px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }
    .gallery-thumb {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 6px;
        border: 2px solid #444;
        transition: transform 0.2s;
    }
    .gallery-thumb:hover {
        transform: scale(1.05);
        border-color: var(--royal-gold);
    }
    .social-content-and-feedback {
        flex: 1;
    }
    .content-box {
        background: #111;
        padding: 15px;
        border-radius: 6px;
        color: #ccc;
        font-size: 0.95rem;
        margin-bottom: 15px;
        max-height: 150px;
        overflow-y: auto;
    }
    .feedback-box-social {
        background: rgba(231, 76, 60, 0.05);
        border: 1px solid #e74c3c;
        padding: 15px;
        border-radius: 6px;
    }
</style>

<div class="social-post-review-card">
    <div class="social-post-header">
        <span class="social-post-title">بوست #<?php echo htmlspecialchars($post['post_index']); ?></span>
        <span class="social-post-status"><?php echo $current_status['label']; ?></span>
    </div>
    <div class="social-post-body">
        <div class="social-image-gallery">
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $img): ?>
                    <a href="<?php echo htmlspecialchars($img); ?>" target="_blank">
                        <img src="<?php echo htmlspecialchars($img); ?>" class="gallery-thumb">
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; font-size:0.9rem;">لم يتم رفع تصميم بعد.</p>
            <?php endif; ?>
        </div>
        <div class="social-content-and-feedback">
            <p style="color: #aaa; margin:0 0 5px 0;"><strong>📜 المحتوى المعتمد:</strong></p>
            <div class="content-box">
                <?php echo nl2br(htmlspecialchars($post['content_text'] ?? 'لا يوجد محتوى نصي.')); ?>
            </div>
            
            <?php if($status === 'design_rejected' && !empty($feedback)): ?>
                <p style="color: #aaa; margin:15px 0 5px 0;"><strong>💬 ملاحظات العميل للتعديل:</strong></p>
                <div class="feedback-box-social">
                    <p style="margin:0; color:#ffb8b8;"><?php echo nl2br(htmlspecialchars($feedback)); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
