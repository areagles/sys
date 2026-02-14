<?php
// modules/_visual_review_component.php - (V3.0 - With Live Replies)
// ... (previous PHP part is the same)
?>

<style>
    /* ... (All previous styles are the same) ... */
    .comment-tooltip {
        /* ... existing styles ... */
        min-width: 280px; /* Wider to accommodate replies */
        padding-bottom: 75px; /* More space for reply box */
    }
    
    /* ✨ NEW: Styles for the reply section */
    .designer-reply-box {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #555;
    }
    .designer-reply-content {
        background: rgba(212, 175, 55, 0.1);
        border-left: 3px solid var(--gold);
        padding: 8px;
        font-size: 0.85rem;
        color: #eee;
        margin-bottom: 8px;
        border-radius: 0 4px 4px 0;
    }
    .designer-reply-content small {
        color: #888;
        font-size: 0.7rem;
    }
    .reply-input-group {
        display: flex;
        gap: 5px;
        margin-top: 5px;
    }
    .reply-input {
        flex: 1;
        background: #111;
        border: 1px solid #444;
        color: #fff;
        padding: 5px 8px;
        border-radius: 4px;
        font-size: 13px;
    }
    .btn-send-reply {
        background: var(--gold);
        color: #000;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
</style>

<!-- ... (HTML part is the same) ... -->

<?php if($is_image): ?>
<script>
    // ... (All previous JS functions like loadInternalComments, createInternalPin, toggleCommentStatus are the same) ...

    function createInternalPin(wrapper, comment, number) {
        // ... (pin creation logic is the same) ...

        const tooltip = document.createElement('div');
        tooltip.className = 'comment-tooltip';
        tooltip.id = `comment-tooltip-${comment.id}`;

        // Main comment content
        let contentHTML = `<strong style="color:var(--gold);">ملاحظة #${number}:</strong><hr style="border-color:#444; margin: 5px 0;">` + comment.comment_text;
        tooltip.innerHTML = contentHTML;
        
        // ✨ ADD a container for replies
        const repliesContainer = document.createElement('div');
        repliesContainer.className = 'designer-reply-box';
        repliesContainer.id = `reply-container-${comment.id}`;
        
        // Display existing reply if present
        if (comment.designer_reply) {
            repliesContainer.innerHTML = createReplyHTML(comment.designer_reply, comment.replied_at);
        }

        // ✨ ADD the reply input form
        const replyForm = document.createElement('div');
        replyForm.className = 'reply-input-group';
        replyForm.innerHTML = `
            <input type="text" id="reply-input-${comment.id}" class="reply-input" placeholder="اكتب ردك هنا...">
            <button class="btn-send-reply" onclick="sendDesignerReply(${comment.id})">إرسال</button>
        `;
        repliesContainer.appendChild(replyForm);

        tooltip.appendChild(repliesContainer);
        
        // ... (logic for doneButton is the same) ...
        
        pin.appendChild(tooltip);
        wrapper.appendChild(pin);
    }

    function createReplyHTML(replyText, repliedAt) {
        // A helper to generate the reply HTML block
        const formattedDate = repliedAt ? ` <small>(${new Date(repliedAt).toLocaleString('ar-EG')})</small>` : '';
        return `<div class="designer-reply-content"><strong>رد المصمم:</strong> ${replyText}${formattedDate}</div>`;
    }

    function sendDesignerReply(commentId) {
        const input = document.getElementById(`reply-input-${commentId}`);
        const replyText = input.value.trim();

        if (!replyText) return; // Don't send empty replies

        fetch('review_api.php?action=add_designer_reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId, reply_text: replyText })
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                // ✨ Update the UI instantly
                const replyContainer = document.getElementById(`reply-container-${commentId}`);
                // Remove old reply form and add the new reply content
                replyContainer.innerHTML = createReplyHTML(replyText, new Date()) + replyContainer.querySelector('.reply-input-group').outerHTML;
                input.value = ''; // Clear the input field
            } else {
                alert('خطأ: ' + result.message);
            }
        });
    }

</script>
<?php endif; ?>
