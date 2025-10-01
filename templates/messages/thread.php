<?php
$title = 'Conversation with ' . htmlspecialchars($other_user_handle);
ob_start();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h1>Conversation with <?= htmlspecialchars($other_user_handle) ?></h1>
        <a href="/messages" style="color: #007bff; text-decoration: none;">← Back to Messages</a>
    </div>
    
    <div style="text-align: right;">
        <form method="post" action="/delete-conversation/<?= $conversation['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this entire conversation?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">
                🗑️ Delete Conversation
            </button>
        </form>
    </div>
</div>

<!-- Messages -->
<div style="border: 1px solid #ddd; border-radius: 5px; background: white; margin-bottom: 20px; max-height: 600px; overflow-y: auto;">
    <?php if (empty($messages)): ?>
        <div style="padding: 40px; text-align: center; color: #666;">
            <p>No messages in this conversation yet.</p>
        </div>
    <?php else: ?>
        <div style="padding: 20px;">
            <?php foreach ($messages as $message): ?>
                <div style="margin-bottom: 20px; display: flex; <?= $message['sender_id'] == $session->getUserId() ? 'justify-content: flex-end;' : 'justify-content: flex-start;' ?>">
                    <div style="max-width: 70%; <?= $message['sender_id'] == $session->getUserId() ? 'background: #007bff; color: white;' : 'background: #f8f9fa; color: #333;' ?> padding: 12px 16px; border-radius: 18px; position: relative;">
                        
                        <?php if ($message['sender_id'] != $session->getUserId()): ?>
                            <div style="font-weight: bold; font-size: 14px; margin-bottom: 5px;">
                                <?= htmlspecialchars($message['sender_handle']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="white-space: pre-wrap; line-height: 1.4;">
                            <?= htmlspecialchars($message['body']) ?>
                        </div>
                        
                        <div style="font-size: 12px; margin-top: 8px; opacity: 0.8;">
                            <?= date('M j, Y \a\t g:i A', strtotime($message['created_at'])) ?>
                        </div>
                        
                        <!-- Message actions for sender -->
                        <?php if ($message['sender_id'] == $session->getUserId()): ?>
                            <div style="margin-top: 8px; font-size: 12px;">
                                <form method="post" action="/report-message/<?= $message['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to report this message?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="reason" value="inappropriate">
                                    <button type="submit" style="background: none; border: none; color: rgba(255,255,255,0.8); cursor: pointer; text-decoration: underline; font-size: 12px;">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Report button for received messages -->
                            <div style="margin-top: 8px; font-size: 12px;">
                                <form method="post" action="/report-message/<?= $message['id'] ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to report this message?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="reason" value="inappropriate">
                                    <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; text-decoration: underline; font-size: 12px;">
                                        🚩 Report
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Reply Form -->
<div style="border: 1px solid #ddd; border-radius: 5px; padding: 20px; background: white;">
    <h3>Send Message</h3>
    
    <form method="post" action="/message/<?= $conversation['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <div class="form-group">
            <textarea name="body" placeholder="Type your message..." rows="4" required maxlength="5000" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;"></textarea>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <small style="color: #666;">Maximum 5000 characters</small>
            <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">
                📤 Send Message
            </button>
        </div>
    </form>
</div>

<!-- Pagination for messages -->
<?php if ($pagination['total_pages'] > 1): ?>
    <div style="margin-top: 20px; text-align: center;">
        <?php if ($pagination['has_prev']): ?>
            <a href="?page=<?= $pagination['prev_page'] ?>" style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                ← Older Messages
            </a>
        <?php endif; ?>
        
        <span style="margin: 0 15px;">
            Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
        </span>
        
        <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['next_page'] ?>" style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                Newer Messages →
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div style="margin-top: 30px; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
    <h3>🛡️ Safety Reminders</h3>
    <ul>
        <li>Never share personal information like addresses or phone numbers</li>
        <li>Meet in public places for in-person transactions</li>
        <li>Use escrow services for high-value transactions</li>
        <li>Trust your instincts - if something feels wrong, it probably is</li>
        <li>Report any suspicious or inappropriate behavior</li>
    </ul>
</div>

<!-- Auto-refresh option (no JS, just meta refresh) -->
<?php if (!empty($_GET['auto_refresh'])): ?>
    <meta http-equiv="refresh" content="30">
    <div style="margin-top: 20px; text-align: center; padding: 10px; background: #d1ecf1; border-radius: 3px;">
        <span>🔄 Auto-refreshing every 30 seconds</span>
        <a href="?auto_refresh=0" style="margin-left: 15px; color: #0c5460;">Turn off</a>
    </div>
<?php else: ?>
    <div style="margin-top: 20px; text-align: center;">
        <a href="?auto_refresh=1" style="color: #007bff; text-decoration: none;">🔄 Enable auto-refresh</a>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>