<?php
$title = htmlspecialchars($listing['title']);
ob_start();
?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Main Content -->
    <div>
        <div style="margin-bottom: 20px;">
            <h1><?= htmlspecialchars($listing['title']) ?></h1>
            <div style="color: #666; margin-bottom: 10px;">
                <span>in <a href="/category/<?= $listing['category_id'] ?>"><?= htmlspecialchars($listing['category_name']) ?></a></span>
                <?php if (!empty($listing['location'])): ?>
                    <span> • <?= htmlspecialchars($listing['location']) ?></span>
                <?php endif; ?>
                <span> • <?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
                <span> • <?= number_format($listing['view_count']) ?> views</span>
            </div>
            
            <?php if ((int)($listing['price_usd_cents'] ?? 0) > 0): ?>
                <div style="font-size: 24px; font-weight: bold; color: #28a745; margin-bottom: 15px;">
                    <?= htmlspecialchars(\App\Core\Price::label($listing)) ?>
                </div>
            <?php else: ?>
                <div style="font-size: 24px; font-weight: bold; color: #28a745; margin-bottom: 15px;">
                    Free / Contact for Price
                </div>
            <?php endif; ?>
        </div>

        <!-- Images -->
        <?php if (!empty($images)): ?>
            <div style="margin-bottom: 30px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                    <?php foreach ($images as $image): ?>
                        <div style="border: 1px solid #ddd; border-radius: 5px; overflow: hidden;">
                            <img src="/image/<?= $image['id'] ?>" 
                                 alt="Listing image" 
                                 style="width: 100%; height: 200px; object-fit: cover; cursor: pointer;"
                                 onclick="openImageModal('/image/<?= $image['id'] ?>')">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Description -->
        <div style="margin-bottom: 30px;">
            <h2>Description</h2>
            <div style="white-space: pre-wrap; line-height: 1.6;">
                <?= htmlspecialchars($listing['body']) ?>
            </div>
        </div>

        <!-- Owner Actions -->
        <?php if ($is_owner): ?>
            <div style="margin-bottom: 30px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
                <h3>Manage Your Listing</h3>
                <div style="margin-top: 10px;">
                    <a href="/edit-listing/<?= $listing['id'] ?>" style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 3px; margin-right: 10px;">Edit Listing</a>
                    
                    <form method="post" action="/delete-listing/<?= $listing['id'] ?>" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this listing? This action cannot be undone.')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <button type="submit" style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">Delete Listing</button>
                    </form>
                </div>
                
                <?php if (!$listing['is_published']): ?>
                    <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 3px;">
                        <strong>⚠️ Listing Not Published</strong><br>
                        This listing is not visible to other users yet. <a href="/pay-to-publish/<?= $listing['id'] ?>">Pay to publish it now</a>.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Comments Section -->
        <div id="comments" style="margin-top: 40px; border-top: 2px solid #dee2e6; padding-top: 30px;">
            <h2>Comments (<?= count($comments) ?>)</h2>
            
            <!-- Add Comment Form -->
            <?php if ($current_user): ?>
                <div style="margin-bottom: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 5px;">
                    <h3>Add a Comment</h3>
                    <form method="post" action="/add-comment">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                        
                        <div class="form-group">
                            <textarea name="body" placeholder="Share your thoughts, ask questions, or provide additional information..." rows="4" required maxlength="2000" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 3px;"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                💬 Post Comment
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 30px; padding: 20px; background-color: #e7f3ff; border-radius: 5px; text-align: center;">
                    <p>Want to comment on this listing?</p>
                    <a href="/login" style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 3px; margin-right: 10px;">Login</a>
                    <a href="/register" style="display: inline-block; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">Register</a>
                </div>
            <?php endif; ?>

            <!-- Comments List -->
            <?php if (empty($comments)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No comments yet. Be the first to comment!</p>
                </div>
            <?php else: ?>
                <div class="comments-list">
                    <?php foreach ($comments as $comment): ?>
                        <?php include __DIR__ . '/../partials/comment.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Seller Info -->
        <div style="border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
            <h3>Seller Information</h3>
            
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <?php if (!empty($listing['avatar_path'])): ?>
                    <img src="/avatar/<?= $listing['user_id'] ?>" alt="Avatar" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 50px; height: 50px; border-radius: 50%; background: #ddd; margin-right: 15px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666;">
                        <?= strtoupper(substr($listing['user_handle'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <div>
                    <div style="font-weight: bold; font-size: 18px;"><?= htmlspecialchars($listing['user_handle']) ?></div>
                    <div style="color: #666; font-size: 14px;">Member since <?= date('M Y', strtotime($listing['created_at'])) ?></div>
                </div>
            </div>
            
            <?php if (!empty($listing['user_about'])): ?>
                <div style="margin-bottom: 15px; font-size: 14px; color: #666;">
                    <?= htmlspecialchars(substr($listing['user_about'], 0, 200)) ?><?= strlen($listing['user_about']) > 200 ? '...' : '' ?>
                </div>
            <?php endif; ?>
            
            <?php
            // Pay the listing's coin (falls back to the seller's preferred) to
            // the seller's wallet for that coin.
            $pref = strtoupper((string)($listing['price_currency'] ?: ($listing['preferred_currency'] ?? '')));
            $prefAddr = $pref !== '' ? (string)($listing['wallet_' . strtolower($pref)] ?? '') : '';
            if ($pref !== '' && $prefAddr !== ''):
            ?>
                <div style="margin-bottom: 15px; padding: 10px; background:#f8f9fa; border-radius:3px;">
                    <div style="font-size: 13px; color:#666;">Pay the seller</div>
                    <div style="font-weight: bold; margin-bottom:4px;">
                        <?php if ((float)($listing['price_crypto'] ?? 0) > 0 && $pref === strtoupper((string)$listing['price_currency'])): ?>
                            <?= htmlspecialchars(\App\Core\Price::crypto((float)$listing['price_crypto'])) ?>
                        <?php endif; ?>
                        <?= htmlspecialchars($pref) ?>
                    </div>
                    <code style="word-break: break-all; font-size: 12px;"><?= htmlspecialchars($prefAddr) ?></code>
                </div>
            <?php endif; ?>

            <?php if ($current_user && !$is_owner): ?>
                <form method="post" action="/start-conversation">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="other_user_id" value="<?= $listing['user_id'] ?>">
                    <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                    <button type="submit" style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 3px; font-size: 16px; cursor: pointer;">
                        💬 Message Seller
                    </button>
                </form>
            <?php elseif (!$current_user): ?>
                <div style="text-align: center; padding: 15px; background-color: #e7f3ff; border-radius: 3px;">
                    <p style="margin-bottom: 10px;">Want to contact this seller?</p>
                    <a href="/login" style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">Login</a>
                    <span style="margin: 0 5px;">or</span>
                    <a href="/register" style="display: inline-block; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">Register</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Safety Tips -->
        <div style="border: 1px solid #ffc107; border-radius: 5px; padding: 15px; background-color: #fff3cd;">
            <h4>🛡️ Safety Tips</h4>
            <ul style="font-size: 14px; margin: 10px 0; padding-left: 20px;">
                <li>Meet in public places for in-person transactions</li>
                <li>Inspect items before payment</li>
                <li>Use escrow services for high-value items</li>
                <li>Trust your instincts</li>
                <li>Report suspicious activity</li>
            </ul>
        </div>

        <!-- Report Listing -->
        <?php if ($current_user && !$is_owner): ?>
            <div style="margin-top: 20px; text-align: center;">
                <a href="/report/listing/<?= $listing['id'] ?>" style="color: #dc3545; font-size: 14px; text-decoration: none;">
                    🚩 Report this listing
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Modal (Simple, no JS) -->
<style>
.image-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
}

.image-modal img {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 90%;
    margin-top: 5%;
}

.image-modal:target {
    display: block;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    text-decoration: none;
}

.close-modal:hover {
    color: #bbb;
}
</style>

<script>
function openImageModal(imageSrc) {
    // Simple image modal without external JS
    const modal = document.createElement('div');
    modal.className = 'image-modal';
    modal.style.display = 'block';
    modal.innerHTML = `
        <span class="close-modal" onclick="this.parentElement.remove()">&times;</span>
        <img src="${imageSrc}" alt="Full size image">
    `;
    modal.onclick = function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    };
    document.body.appendChild(modal);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>