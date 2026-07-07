<?php
$title = 'Search Results';
ob_start();
?>

<h1>Search Listings</h1>

<!-- Search Form -->
<div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
    <form method="get" action="/search">
        <div style="display: grid; grid-template-columns: 1fr 200px auto; gap: 10px; margin-bottom: 15px;">
            <input type="text" name="q" placeholder="Search listings..." value="<?= htmlspecialchars($query) ?>" style="padding: 10px;">
            
            <select name="category" style="padding: 10px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($category_id ?? null) == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">Search</button>
        </div>
        
        <!-- Advanced Filters -->
        <details style="margin-top: 15px;">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">Advanced Filters</summary>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                <div>
                    <label for="location" style="display: block; margin-bottom: 5px;">Location:</label>
                    <input type="text" id="location" name="location" placeholder="City, region..." value="<?= htmlspecialchars($location) ?>" style="width: 100%; padding: 8px;">
                </div>
                
                <div>
                    <label for="min_price" style="display: block; margin-bottom: 5px;">Min Price (USD):</label>
                    <input type="number" id="min_price" name="min_price" step="0.00000001" min="0" value="<?= htmlspecialchars($min_price ?? '') ?>" style="width: 100%; padding: 8px;">
                </div>
                
                <div>
                    <label for="max_price" style="display: block; margin-bottom: 5px;">Max Price (USD):</label>
                    <input type="number" id="max_price" name="max_price" step="0.00000001" min="0" value="<?= htmlspecialchars($max_price ?? '') ?>" style="width: 100%; padding: 8px;">
                </div>
            </div>
        </details>
    </form>
</div>

<!-- Search Results -->
<?php if (!empty($query) || $category_id || !empty($location) || $min_price || $max_price): ?>
    <div style="margin-bottom: 20px;">
        <h2>Search Results</h2>
        
        <!-- Active Filters -->
        <?php if (!empty($query) || $category_id || !empty($location) || $min_price || $max_price): ?>
            <div style="margin-bottom: 15px;">
                <strong>Active filters:</strong>
                <?php if (!empty($query)): ?>
                    <span style="display: inline-block; padding: 4px 8px; background: #007bff; color: white; border-radius: 3px; margin: 2px; font-size: 12px;">
                        Text: "<?= htmlspecialchars($query) ?>"
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($category_id)): ?>
                    <?php
                    $selectedCategory = array_filter($categories, fn($cat) => $cat['id'] == ($category_id ?? null));
                    $selectedCategory = reset($selectedCategory);
                    ?>
                    <span style="display: inline-block; padding: 4px 8px; background: #28a745; color: white; border-radius: 3px; margin: 2px; font-size: 12px;">
                        Category: <?= htmlspecialchars($selectedCategory['name'] ?? 'Unknown') ?>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($location)): ?>
                    <span style="display: inline-block; padding: 4px 8px; background: #ffc107; color: #000; border-radius: 3px; margin: 2px; font-size: 12px;">
                        Location: <?= htmlspecialchars($location) ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($min_price): ?>
                    <span style="display: inline-block; padding: 4px 8px; background: #6c757d; color: white; border-radius: 3px; margin: 2px; font-size: 12px;">
                        Min: $<?= number_format($min_price, 2) ?>
                    </span>
                <?php endif; ?>
                
                <?php if ($max_price): ?>
                    <span style="display: inline-block; padding: 4px 8px; background: #6c757d; color: white; border-radius: 3px; margin: 2px; font-size: 12px;">
                        Max: $<?= number_format($max_price, 2) ?>
                    </span>
                <?php endif; ?>
                
                <a href="/search" style="margin-left: 10px; color: #dc3545; text-decoration: none; font-size: 14px;">Clear all filters</a>
            </div>
        <?php endif; ?>
        
        <p><?= number_format($total_count) ?> result<?= $total_count !== 1 ? 's' : '' ?> found</p>
    </div>

    <?php if (empty($listings)): ?>
        <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 5px;">
            <h3>No Results Found</h3>
            <p>Try adjusting your search terms or filters.</p>
            
            <div style="margin-top: 20px;">
                <a href="/search" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 3px; margin-right: 10px;">
                    Clear Filters
                </a>
                <a href="/" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 3px;">
                    Browse All
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="listing-grid">
            <?php foreach ($listings as $listing): ?>
                <div class="listing-card">
                    <h3>
                        <a href="/listing/<?= $listing['id'] ?>" style="text-decoration: none; color: #333;">
                            <?= htmlspecialchars($listing['title']) ?>
                        </a>
                    </h3>
                    
                    <p class="price">
                        <?php if ((int)($listing['price_usd_cents'] ?? 0) > 0): ?>
                            <?= htmlspecialchars(\App\Core\Price::label($listing)) ?>
                        <?php else: ?>
                            Free
                        <?php endif; ?>
                    </p>
                    
                    <p><?= htmlspecialchars(substr($listing['body'], 0, 150)) ?><?= strlen($listing['body']) > 150 ? '...' : '' ?></p>
                    
                    <?php if (!empty($listing['location'])): ?>
                        <p class="location"><?= htmlspecialchars($listing['location']) ?></p>
                    <?php endif; ?>
                    
                    <small>
                        in <a href="/category/<?= $listing['category_id'] ?>"><?= htmlspecialchars($listing['category_name']) ?></a>
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
        <?php if ($pagination && $pagination['total_pages'] > 1): ?>
            <div style="margin-top: 30px; text-align: center;">
                <?php
                $queryParams = http_build_query(array_filter([
                    'q' => $query,
                    'category' => $category_id,
                    'location' => $location,
                    'min_price' => $min_price,
                    'max_price' => $max_price
                ]));
                ?>
                
                <?php if ($pagination['has_prev']): ?>
                    <a href="?<?= $queryParams ?>&page=<?= $pagination['prev_page'] ?>" 
                       style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <span style="margin: 0 15px;">
                    Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                </span>
                
                <?php if ($pagination['has_next']): ?>
                    <a href="?<?= $queryParams ?>&page=<?= $pagination['next_page'] ?>" 
                       style="display: inline-block; padding: 8px 12px; margin: 0 5px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php else: ?>
    <!-- No search performed yet -->
    <div style="text-align: center; padding: 50px;">
        <h2>🔍 Search Onion Classifieds</h2>
        <p>Use the search form above to find listings by keyword, category, location, or price range.</p>
        
        <div style="margin-top: 30px;">
            <h3>Popular Categories</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 15px;">
                <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
                    <a href="/category/<?= $cat['id'] ?>" style="display: block; padding: 15px; background: white; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; text-align: center;">
                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Search Tips -->
<div style="margin-top: 40px; padding: 20px; background-color: #e7f3ff; border-left: 4px solid #007bff; border-radius: 5px;">
    <h3>🔍 Search Tips</h3>
    <ul>
        <li><strong>Keywords:</strong> Use specific terms related to what you're looking for</li>
        <li><strong>Categories:</strong> Browse by category for better organized results</li>
        <li><strong>Location:</strong> Filter by city or region to find local items</li>
        <li><strong>Price Range:</strong> Set min/max prices to find items in your budget</li>
        <li><strong>Combine Filters:</strong> Use multiple filters together for precise results</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>