<?php
$title = htmlspecialchars($category['name']) . ' - Browse Category';
ob_start();
?>

<div style="margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <?php if (!empty($parent)): ?>
                <div style="font-size: 14px; color: #666; margin-bottom: 4px;">
                    <a href="/<?= htmlspecialchars($parent['slug']) ?>" style="color: #007bff; text-decoration: none;"><?= htmlspecialchars($parent['name']) ?></a>
                    &rsaquo; <?= htmlspecialchars($category['name']) ?>
                </div>
            <?php endif; ?>
            <h1><?= htmlspecialchars($category['name']) ?></h1>
            <?php if (!empty($category['description'])): ?>
                <p style="color: #666; margin: 5px 0;"><?= htmlspecialchars($category['description']) ?></p>
            <?php endif; ?>
        </div>
        
        <div style="text-align: right;">
            <a href="/" style="color: #007bff; text-decoration: none;">← All Categories</a>
            <?php if ($current_user): ?>
                <br>
                <a href="/create-listing" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">
                    ➕ Post in <?= htmlspecialchars($category['name']) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($subcategories)): ?>
        <!-- Subcategories -->
        <div style="margin-bottom: 15px; line-height: 2;">
            <?php foreach ($subcategories as $sub): ?>
                <a href="<?= htmlspecialchars($sub['path']) ?>" style="display: inline-block; margin: 0 6px 6px 0; padding: 4px 12px; background: #fff; border: 1px solid #ddd; border-radius: 15px; font-size: 14px; text-decoration: none; color: #333;">
                    <?= htmlspecialchars($sub['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Search within category -->
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
        <form method="get" action="/search">
            <input type="hidden" name="category" value="<?= $category['id'] ?>">
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px;">
                <input type="text" name="q" placeholder="Search within <?= htmlspecialchars($category['name']) ?>..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" style="padding: 10px;">
                <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">Search</button>
            </div>
        </form>
    </div>
</div>

<!-- Listings -->
<?php if (empty($listings)): ?>
    <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 5px;">
        <h2>No Listings in This Category</h2>
        <p>Be the first to post in <?= htmlspecialchars($category['name']) ?>!</p>
        <?php if ($current_user): ?>
            <a href="/create-listing" style="display: inline-block; margin-top: 15px; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">
                Create First Listing
            </a>
        <?php else: ?>
            <div style="margin-top: 15px;">
                <a href="/register" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 3px; margin-right: 10px;">Register</a>
                <a href="/login" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">Login</a>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <p><?= number_format($pagination['total']) ?> listing<?= $pagination['total'] !== 1 ? 's' : '' ?> in <?= htmlspecialchars($category['name']) ?></p>
        
        <!-- Sort options -->
        <div>
            <label for="sort" style="margin-right: 10px;">Sort by:</label>
            <select id="sort" onchange="updateSort(this.value)" style="padding: 5px;">
                <option value="newest" <?= ($_GET['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= ($_GET['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="price_low" <?= ($_GET['sort'] ?? '') === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_high" <?= ($_GET['sort'] ?? '') === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
            </select>
        </div>
    </div>

    <div class="listing-grid">
        <?php foreach ($listings as $listing): ?>
            <div class="listing-card">
                <h3>
                    <a href="/listing/<?= $listing['id'] ?>" style="text-decoration: none; color: #333;">
                        <?= htmlspecialchars($listing['title']) ?>
                    </a>
                </h3>
                
                <p class="price">
                    <?php if ($listing['price_sats'] > 0): ?>
                        <?= number_format($listing['price_sats'] / 100000000, 8) ?> BTC
                    <?php else: ?>
                        Free
                    <?php endif; ?>
                </p>
                
                <p><?= htmlspecialchars(substr($listing['body'], 0, 150)) ?><?= strlen($listing['body']) > 150 ? '...' : '' ?></p>
                
                <?php if (!empty($listing['location'])): ?>
                    <p class="location"><?= htmlspecialchars($listing['location']) ?></p>
                <?php endif; ?>
                
                <small>
                    by <?= htmlspecialchars($listing['user_handle']) ?>
                    • <?= date('M j, Y', strtotime($listing['created_at'])) ?>
                    • <?= number_format($listing['view_count'] ?? 0) ?> views
                    <?php if (($listing['comment_count'] ?? 0) > 0): ?>
                        • <?= $listing['comment_count'] ?> comment<?= $listing['comment_count'] !== 1 ? 's' : '' ?>
                    <?php endif; ?>
                </small>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div style="margin-top: 30px; text-align: center;">
            <?php if ($pagination['has_prev']): ?>
                <a href="?page=<?= $pagination['prev_page'] ?><?= !empty($_GET['sort']) ? '&sort=' . urlencode($_GET['sort']) : '' ?>" 
                   style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                    ← Previous
                </a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">
                Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
            </span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?page=<?= $pagination['next_page'] ?><?= !empty($_GET['sort']) ? '&sort=' . urlencode($_GET['sort']) : '' ?>" 
                   style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                    Next →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function updateSort(sortValue) {
    const url = new URL(window.location);
    if (sortValue === 'newest') {
        url.searchParams.delete('sort');
    } else {
        url.searchParams.set('sort', sortValue);
    }
    url.searchParams.delete('page'); // Reset to first page
    window.location.href = url.toString();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>