<?php
$title = 'Messages';
ob_start();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h1>Messages</h1>
    <div style="color: #666;">
        <?php if (!empty($conversations)): ?>
            <?= count($conversations) ?> conversation<?= count($conversations) !== 1 ? 's' : '' ?>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($conversations)): ?>
    <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 5px;">
        <h2>No Messages Yet</h2>
        <p>You don't have any conversations yet. Start browsing listings to connect with sellers!</p>
        <a href="/" style="display: inline-block; margin-top: 15px; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
            Browse Listings
        </a>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 15px;">
        <?php foreach ($conversations as $conversation): ?>
            <div style="border: 1px solid #ddd; border-radius: 5px; padding: 20px; background: white; <?= $conversation['unread_count'] > 0 ? 'border-left: 4px solid #007bff;' : '' ?>">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <!-- Other User Avatar -->
                    <div style="flex-shrink: 0;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666;">
                            <?= strtoupper(substr($conversation['other_user_handle'], 0, 1)) ?>
                        </div>
                    </div>
                    
                    <!-- Conversation Info -->
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <h3 style="margin: 0; font-size: 18px;">
                                <a href="/message/<?= $conversation['id'] ?>" style="text-decoration: none; color: #333;">
                                    <?= htmlspecialchars($conversation['other_user_handle']) ?>
                                </a>
                            </h3>
                            
                            <div style="text-align: right; flex-shrink: 0;">
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <span style="display: inline-block; padding: 2px 8px; background: #007bff; color: white; border-radius: 10px; font-size: 12px; margin-bottom: 5px;">
                                        <?= $conversation['unread_count'] ?> new
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($conversation['last_message_at']): ?>
                                    <div style="color: #666; font-size: 14px;">
                                        <?= date('M j, g:i A', strtotime($conversation['last_message_at'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($conversation['last_message']): ?>
                            <div style="color: #666; font-size: 14px; margin-bottom: 8px;">
                                <?php if ($conversation['last_sender_id'] == $session->getUserId()): ?>
                                    <strong>You:</strong>
                                <?php endif; ?>
                                <?= htmlspecialchars(substr($conversation['last_message'], 0, 100)) ?><?= strlen($conversation['last_message']) > 100 ? '...' : '' ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 15px; font-size: 14px;">
                            <a href="/message/<?= $conversation['id'] ?>" style="color: #007bff; text-decoration: none;">
                                💬 View Conversation
                            </a>
                            
                            <form method="post" action="/delete-conversation/<?= $conversation['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this entire conversation?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; text-decoration: underline;">
                                    🗑️ Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div style="margin-top: 30px; text-align: center;">
            <?php if ($pagination['has_prev']): ?>
                <a href="?page=<?= $pagination['prev_page'] ?>" style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                    ← Previous
                </a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">
                Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
            </span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['next_page'] ?>" style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                    Next →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-top: 40px; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #007bff;">
    <h3>💬 Messaging Guidelines</h3>
    <ul>
        <li>Be respectful and professional in all communications</li>
        <li>Don't share personal information like phone numbers or addresses</li>
        <li>Use the messaging system for transaction-related discussions</li>
        <li>Report any suspicious or inappropriate messages</li>
        <li>Messages are private between you and the other user</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>