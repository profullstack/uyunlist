<?php
$title = 'Home';
ob_start();
?>

<div style="text-align: center; margin-bottom: 40px;">
    <h1>🧅 Welcome to Onion Classifieds</h1>
    <p style="font-size: 18px; color: #666;">The privacy-first marketplace accessible only via Tor</p>
    
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 30px 0; text-align: center;">
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">
            <h3><?= number_format($stats['total_listings']) ?></h3>
            <p>Active Listings</p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">
            <h3><?= number_format($stats['total_users']) ?></h3>
            <p>Registered Users</p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">
            <h3><?= $stats['total_categories'] ?></h3>
            <p>Categories</p>
        </div>
    </div>
</div>

<!-- Search Form -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
    <form method="get" action="/search">
        <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px;">
            <input type="text" name="q" placeholder="Search listings..." style="padding: 10px;">
            <button type="submit">Search</button>
        </div>
    </form>
</div>

<!-- Categories -->
<div style="margin-bottom: 40px;">
    <h2>Browse Categories</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
        <?php foreach ($categories as $category): ?>
            <div style="padding: 15px; background: white; border: 1px solid #ddd; border-radius: 5px;">
                <a href="<?= htmlspecialchars($category['path']) ?>" style="text-decoration: none; color: #333;">
                    <strong><?= htmlspecialchars($category['name']) ?></strong>
                </a>
                <?php if (!empty($category['description'])): ?>
                    <br><small style="color: #666;"><?= htmlspecialchars($category['description']) ?></small>
                <?php endif; ?>
                <?php if (!empty($category['children'])): ?>
                    <div style="margin-top: 8px; line-height: 1.9;">
                        <?php foreach ($category['children'] as $sub): ?>
                            <a href="<?= htmlspecialchars($sub['path']) ?>" style="display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; background: #f1f3f5; border-radius: 3px; font-size: 13px; text-decoration: none; color: #495057;"><?= htmlspecialchars($sub['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Featured Listings -->
<?php if (!empty($featured_listings)): ?>
<div style="margin-bottom: 40px;">
    <h2>Featured Listings</h2>
    <div class="listing-grid">
        <?php foreach ($featured_listings as $listing): ?>
            <div class="listing-card">
                <h3><a href="/listing/<?= $listing['id'] ?>" style="text-decoration: none; color: #333;"><?= htmlspecialchars($listing['title']) ?></a></h3>
                <p class="price">
                    <?php if ($listing['price_sats'] > 0): ?>
                        <?= number_format($listing['price_sats'] / 100000000, 8) ?> BTC
                    <?php else: ?>
                        Free
                    <?php endif; ?>
                </p>
                <p><?= htmlspecialchars(substr($listing['body'], 0, 100)) ?><?= strlen($listing['body']) > 100 ? '...' : '' ?></p>
                <p class="location"><?= htmlspecialchars($listing['location']) ?></p>
                <small>
                    in <a href="/category/<?= $listing['category_id'] ?>"><?= htmlspecialchars($listing['category_name']) ?></a>
                    by <?= htmlspecialchars($listing['user_handle']) ?>
                    • <?= date('M j, Y', strtotime($listing['created_at'])) ?>
                </small>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Listings -->
<div>
    <h2>Recent Listings</h2>
    <?php if (empty($recent_listings)): ?>
        <p>No listings yet. <?php if ($current_user): ?><a href="/create-listing">Be the first to post!</a><?php else: ?><a href="/register">Register</a> to start posting.<?php endif; ?></p>
    <?php else: ?>
        <div class="listing-grid">
            <?php foreach ($recent_listings as $listing): ?>
                <div class="listing-card">
                    <h3><a href="/listing/<?= $listing['id'] ?>" style="text-decoration: none; color: #333;"><?= htmlspecialchars($listing['title']) ?></a></h3>
                    <p class="price">
                        <?php if ($listing['price_sats'] > 0): ?>
                            <?= number_format($listing['price_sats'] / 100000000, 8) ?> BTC
                        <?php else: ?>
                            Free
                        <?php endif; ?>
                    </p>
                    <p><?= htmlspecialchars(substr($listing['body'], 0, 100)) ?><?= strlen($listing['body']) > 100 ? '...' : '' ?></p>
                    <p class="location"><?= htmlspecialchars($listing['location']) ?></p>
                    <small>
                        in <a href="/category/<?= $listing['category_id'] ?>"><?= htmlspecialchars($listing['category_name']) ?></a>
                        by <?= htmlspecialchars($listing['user_handle']) ?>
                        • <?= date('M j, Y', strtotime($listing['created_at'])) ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="/search" style="padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 3px;">View All Listings</a>
        </div>
    <?php endif; ?>
</div>

<!-- Privacy Notice -->
<div style="margin-top: 50px; padding: 20px; background-color: #e7f3ff; border-left: 4px solid #007bff;">
    <h3>🔒 Your Privacy is Protected</h3>
    <ul>
        <li>This site is only accessible via Tor for maximum anonymity</li>
        <li>No tracking, no analytics, no external resources</li>
        <li>All communications are encrypted end-to-end</li>
        <li>No email addresses required - just a handle and password</li>
        <li>Payments are made directly in cryptocurrency</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>