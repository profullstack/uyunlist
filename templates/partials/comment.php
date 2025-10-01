<!-- Comment Template Partial -->
<div id="comment-<?= $comment['id'] ?>" style="margin-bottom: 20px; border-left: 3px solid #dee2e6; padding-left: 15px;">
    <div style="display: flex; align-items: start; gap: 10px;">
        <!-- Avatar -->
        <div style="flex-shrink: 0;">
            <?php if (!empty($comment['avatar_path'])): ?>
                <img src="/avatar/<?= $comment['user_id'] ?>" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666;">
                    <?= strtoupper(substr($comment['user_handle'], 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Comment Content -->
        <div style="flex: 1;">
            <div style="margin-bottom: 8px;">
                <strong><?= htmlspecialchars($comment['user_handle']) ?></strong>
                <span style="color: #666; font-size: 14px; margin-left: 10px;">
                    <?= date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) ?>
                </span>
                
                <?php if ($comment['updated_at'] !== $comment['created_at']): ?>
                    <span style="color: #666; font-size: 12px; margin-left: 5px;">(edited)</span>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 10px; white-space: pre-wrap; line-height: 1.5;">
                <?= htmlspecialchars($comment['body']) ?>
            </div>
            
            <!-- Comment Actions -->
            <div style="display: flex; gap: 15px; font-size: 14px;">
                <?php if ($current_user && !$comment['parent_id']): ?>
                    <!-- Reply button (only for top-level comments) -->
                    <button onclick="toggleReplyForm(<?= $comment['id'] ?>)" style="background: none; border: none; color: #007bff; cursor: pointer; text-decoration: underline;">
                        💬 Reply
                    </button>
                <?php endif; ?>
                
                <?php if ($current_user && $current_user['id'] == $comment['user_id']): ?>
                    <!-- Edit button for comment owner -->
                    <button onclick="toggleEditForm(<?= $comment['id'] ?>)" style="background: none; border: none; color: #6c757d; cursor: pointer; text-decoration: underline;">
                        ✏️ Edit
                    </button>
                    
                    <!-- Delete button for comment owner -->
                    <form method="post" action="/delete-comment/<?= $comment['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this comment?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; text-decoration: underline;">
                            🗑️ Delete
                        </button>
                    </form>
                <?php elseif ($current_user && ($is_owner || ($current_user['is_admin'] ?? false))): ?>
                    <!-- Delete button for listing owner or admin -->
                    <form method="post" action="/delete-comment/<?= $comment['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this comment?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; text-decoration: underline;">
                            🗑️ Delete
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($current_user && $current_user['id'] != $comment['user_id']): ?>
                    <!-- Report button -->
                    <a href="/report-comment/<?= $comment['id'] ?>" style="color: #dc3545; text-decoration: none;">
                        🚩 Report
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Reply Form (hidden by default) -->
            <?php if ($current_user && !$comment['parent_id']): ?>
                <div id="reply-form-<?= $comment['id'] ?>" style="display: none; margin-top: 15px; padding: 15px; background-color: #f8f9fa; border-radius: 3px;">
                    <form method="post" action="/add-comment">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                        <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                        
                        <div class="form-group">
                            <textarea name="body" placeholder="Write your reply..." rows="3" required maxlength="2000" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" style="padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                Post Reply
                            </button>
                            <button type="button" onclick="toggleReplyForm(<?= $comment['id'] ?>)" style="padding: 6px 12px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Edit Form (hidden by default) -->
            <?php if ($current_user && $current_user['id'] == $comment['user_id']): ?>
                <div id="edit-form-<?= $comment['id'] ?>" style="display: none; margin-top: 15px; padding: 15px; background-color: #fff3cd; border-radius: 3px;">
                    <form method="post" action="/edit-comment/<?= $comment['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="form-group">
                            <textarea name="body" rows="3" required maxlength="2000" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;"><?= htmlspecialchars($comment['body']) ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" style="padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                Update Comment
                            </button>
                            <button type="button" onclick="toggleEditForm(<?= $comment['id'] ?>)" style="padding: 6px 12px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Replies (nested comments) -->
    <?php if (!empty($comment['replies'])): ?>
        <div style="margin-left: 30px; margin-top: 15px; border-left: 2px solid #e9ecef; padding-left: 15px;">
            <?php foreach ($comment['replies'] as $reply): ?>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <!-- Reply Avatar -->
                        <div style="flex-shrink: 0;">
                            <?php if (!empty($reply['avatar_path'])): ?>
                                <img src="/avatar/<?= $reply['user_id'] ?>" alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666; font-size: 12px;">
                                    <?= strtoupper(substr($reply['user_handle'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Reply Content -->
                        <div style="flex: 1;">
                            <div style="margin-bottom: 5px;">
                                <strong style="font-size: 14px;"><?= htmlspecialchars($reply['user_handle']) ?></strong>
                                <span style="color: #666; font-size: 12px; margin-left: 8px;">
                                    <?= date('M j, Y \a\t g:i A', strtotime($reply['created_at'])) ?>
                                </span>
                                
                                <?php if ($reply['updated_at'] !== $reply['created_at']): ?>
                                    <span style="color: #666; font-size: 11px; margin-left: 5px;">(edited)</span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin-bottom: 8px; white-space: pre-wrap; line-height: 1.4; font-size: 14px;">
                                <?= htmlspecialchars($reply['body']) ?>
                            </div>
                            
                            <!-- Reply Actions -->
                            <div style="display: flex; gap: 10px; font-size: 12px;">
                                <?php if ($current_user && $current_user['id'] == $reply['user_id']): ?>
                                    <form method="post" action="/delete-comment/<?= $reply['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this reply?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; text-decoration: underline; font-size: 12px;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                <?php elseif ($current_user && ($is_owner || ($current_user['is_admin'] ?? false))): ?>
                                    <form method="post" action="/delete-comment/<?= $reply['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this reply?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; text-decoration: underline; font-size: 12px;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($current_user && $current_user['id'] != $reply['user_id']): ?>
                                    <a href="/report-comment/<?= $reply['id'] ?>" style="color: #dc3545; text-decoration: none; font-size: 12px;">
                                        🚩 Report
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.querySelector('textarea').focus();
    } else {
        form.style.display = 'none';
    }
}

function toggleEditForm(commentId) {
    const form = document.getElementById('edit-form-' + commentId);
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        form.querySelector('textarea').focus();
    } else {
        form.style.display = 'none';
    }
}
</script>