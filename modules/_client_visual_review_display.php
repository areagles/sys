<?php
// modules/_client_visual_review_display.php (V2.0 - With Designer Replies)

$ext = strtolower(pathinfo($p['file_path'], PATHINFO_EXTENSION));
$is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
?>

<style>
    .conversation-box { margin-top: 20px; border-top: 1px solid #333; padding-top: 20px; }
    .comment-thread { background: #1a1a1a; border-radius: 8px; padding: 15px; margin-bottom: 10px; border-left: 3px solid #555; }
    .client-comment p { margin-top:0; color: #eee; }
    .designer-reply { margin-top: 10px; background: rgba(212, 175, 55, 0.1); padding: 10px; border-radius: 6px; border-right: 3px solid var(--gold); }
    .designer-reply p { margin:0; color: #d4af37; font-size: 0.95rem; }
    .comment-pin-client { /* Style for pins on client side is similar to designer side */ }
</style>

<div class="royal-card item-card proof-item-<?php echo $p['id']; ?>">
    <!-- ... (Card header and visual review wrapper are the same) ... -->
    <div class="card-body">
        <?php if($is_image): ?>
            <div class="visual-review-wrapper" id="review-wrapper-<?php echo $p['id']; ?>">
                <img src="<?php echo htmlspecialchars($p['file_path']); ?>" data-proof-id="<?php echo $p['id']; ?>">
                <!-- Pins will be loaded here -->
            </div>

            <!-- Conversation Area -->
            <div class="conversation-box" id="conversation-box-<?php echo $p['id']; ?>">
                <h4 style="margin-top:0; color:#fff;">💬 سجل الملاحظات:</h4>
                <!-- Comments will be loaded here -->
            </div>

        <?php else: ?>
            <!-- Non-image file handling -->
        <?php endif; ?>
        
        <div class="control-panel">
            <!-- ... (Radio buttons and reason textarea) ... -->
        </div>
    </div>
</div>

<script>
// This script should be loaded once on the main client_review page
document.addEventListener('DOMContentLoaded', function() {
    // Make sure we only initialize for proofs that are on the page
    if (document.getElementById('review-wrapper-<?php echo $p['id']; ?>')) {
        loadAndDisplayComments(<?php echo $p['id']; ?>);
    }
});

function loadAndDisplayComments(proofId) {
    fetch(`review_api.php?action=get_comments&proof_id=${proofId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const wrapper = document.getElementById(`review-wrapper-${proofId}`);
                const conversationBox = document.getElementById(`conversation-box-${proofId}`);
                conversationBox.innerHTML = '<h4 style="margin-top:0; color:#fff;">💬 سجل الملاحظات:</h4>'; // Reset

                if(data.comments.length === 0) {
                    conversationBox.innerHTML += '<p style="color:#666;">لا توجد ملاحظات مسجلة على هذا التصميم بعد.</p>';
                    return;
                }

                let pinCounter = 1;
                data.comments.forEach(comment => {
                    // Create the pin on the image
                    const pin = document.createElement('div');
                    pin.className = 'comment-pin'; // Ensure this class is styled
                    pin.style.left = `${comment.pos_x}%`;
                    pin.style.top = `${comment.pos_y}%`;
                    pin.textContent = pinCounter;
                    wrapper.appendChild(pin);

                    // Create the text entry in the conversation box
                    const thread = document.createElement('div');
                    thread.className = 'comment-thread';
                    let threadHTML = `
                        <div class="client-comment">
                            <p><strong>#${pinCounter}:</strong> ${comment.comment_text}</p>
                        </div>
                    `;
                    if (comment.designer_reply) {
                        threadHTML += `
                            <div class="designer-reply">
                                <p><strong>رد المصمم:</strong> ${comment.designer_reply}</p>
                            </div>
                        `;
                    }
                    thread.innerHTML = threadHTML;
                    conversationBox.appendChild(thread);

                    pinCounter++;
                });
            }
        });
}
</script>
