<?php
$title = 'My Listings';
ob_start();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h1>My Listings</h1>
    <a href="/create-listing" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">
        ➕ Create New Listing
    </a>
</div>

<?php if (empty($listings)): ?>
    <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 5px;">
        <h2>No Listings Yet</h2>
        <p>You haven't created any listings yet. Start selling on Onion Classifieds!</p>
        <a href="/create-listing" style="display: inline-block; margin-top: 15px; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">
            Create Your First Listing
        </a>
    </div>
<?php else: ?>
    <div style="margin-bottom: 20px;">
        <p>You have <?= count($listings) ?> listing<?= count($listings) !== 1 ? 's' : '' ?> 
        <?php if ($pagination['total'] > count($listings)): ?>
            (<?= number_format($pagination['total']) ?> total)
        <?php endif; ?>
        </p>
    </div>

    <div style="display: grid; gap: 20px;">
        <?php foreach ($listings as $listing): ?>
            <div style="border: 1px solid #ddd; border-radius: 5px; padding: 20px; background: white;">
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start;">
                    <div>
                        <h3 style="margin-bottom: 10px;">
                            <a href="/listing/<?= $listing['id'] ?>" style="text-decoration: none; color: #333;">
                                <?= htmlspecialchars($listing['title']) ?>
                            </a>
                        </h3>
                        
                        <div style="color: #666; font-size: 14px; margin-bottom: 10px;">
                            <span><?= htmlspecialchars($listing['category_name']) ?></span>
                            <?php if (!empty($listing['location'])): ?>
                                <span> • <?= htmlspecialchars($listing['location']) ?></span>
                            <?php endif; ?>
                            <span> • <?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <?php if ((int)($listing['price_usd_cents'] ?? 0) > 0): ?>
                                <span style="font-weight: bold; color: #28a745;">
                                    <?= htmlspecialchars(\App\Core\Price::label($listing)) ?>
                                </span>
                            <?php else: ?>
                                <span style="font-weight: bold; color: #28a745;">Free</span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="font-size: 14px; color: #666;">
                            <?= htmlspecialchars(substr($listing['body'], 0, 150)) ?><?= strlen($listing['body']) > 150 ? '...' : '' ?>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <?php if ($listing['is_published']): ?>
                                <span style="display: inline-block; padding: 4px 8px; background: #28a745; color: white; border-radius: 3px; font-size: 12px;">
                                    ✅ Published
                                </span>
                                <span style="margin-left: 10px; font-size: 14px; color: #666;">
                                    <?= number_format($listing['view_count']) ?> views
                                </span>
                            <?php else: ?>
                                <span style="display: inline-block; padding: 4px 8px; background: #ffc107; color: #000; border-radius: 3px; font-size: 12px;">
                                    ⏳ Awaiting Payment
                                </span>
                                <a href="/pay-to-publish/<?= $listing['id'] ?>" style="margin-left: 10px; color: #007bff; text-decoration: none; font-size: 14px;">
                                    Pay to Publish
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; min-width: 120px;">
                        <a href="/listing/<?= $listing['id'] ?>" style="padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 3px; text-align: center; font-size: 14px;">
                            View
                        </a>
                        <a href="/edit-listing/<?= $listing['id'] ?>" style="padding: 8px 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 3px; text-align: center; font-size: 14px;">
                            Edit
                        </a>
                        <form method="post" action="/delete-listing/<?= $listing['id'] ?>" onsubmit="return confirm('Are you sure you want to delete this listing?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" style="width: 100%; padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px;">
                                Delete
                            </button>
                        </form>
                        
                        <?php if (!$listing['is_published']): ?>
                            <a href="/pay-to-publish/<?= $listing['id'] ?>" style="padding: 8px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 3px; text-align: center; font-size: 14px;">
                                💰 Publish
                            </a>
                        <?php endif; ?>
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
    <h3>💡 Tips for Better Listings</h3>
    <ul>
        <li><strong>Use clear photos:</strong> Well-lit, high-quality images get more views</li>
        <li><strong>Write detailed descriptions:</strong> Include condition, specifications, and features</li>
        <li><strong>Price competitively:</strong> Research similar items to set fair prices</li>
        <li><strong>Respond quickly:</strong> Fast responses lead to more sales</li>
        <li><strong>Keep listings updated:</strong> Edit or delete sold items promptly</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>