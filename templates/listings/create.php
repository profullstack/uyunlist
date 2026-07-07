<?php
$title = 'Create Listing';
ob_start();
?>

<h1>Create New Listing</h1>

<p>Create a new listing on Onion Classifieds. Your listing will need to be paid for before it becomes visible to other users.</p>

<div style="margin: 15px 0; padding: 12px 15px; background-color: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">
    <strong>🚫 No porn / no sexually explicit content.</strong>
    This is strictly enforced — listings advertising pornographic or explicit sexual content/services are automatically rejected and may result in a ban.
</div>

<form method="post" action="/create-listing" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    
    <div class="form-group">
        <label for="title">Title *</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($old['title'] ?? '') ?>" required maxlength="200">
        <?php if (isset($errors['title'])): ?>
            <div class="error"><?= htmlspecialchars($errors['title']) ?></div>
        <?php endif; ?>
        <small>Be descriptive but concise. This is what people will see first.</small>
    </div>
    
    <div class="form-group">
        <label for="category_id">Category *</label>
        <select id="category_id" name="category_id" required>
            <option value="">Select a category</option>
            <?php foreach ($categories as $top): ?>
                <optgroup label="<?= htmlspecialchars($top['name']) ?>">
                    <option value="<?= $top['id'] ?>" <?= ($old['category_id'] ?? '') == $top['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($top['name']) ?> (general)
                    </option>
                    <?php foreach ($top['children'] ?? [] as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= ($old['category_id'] ?? '') == $sub['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?>
            <div class="error"><?= htmlspecialchars($errors['category_id']) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <label for="body">Description *</label>
        <textarea id="body" name="body" required maxlength="10000" rows="8"><?= htmlspecialchars($old['body'] ?? '') ?></textarea>
        <?php if (isset($errors['body'])): ?>
            <div class="error"><?= htmlspecialchars($errors['body']) ?></div>
        <?php endif; ?>
        <small>Provide detailed information about your item or service. Include condition, specifications, etc.</small>
    </div>
    
    <div class="form-group">
        <label for="price_sats">Price (BTC)</label>
        <input type="number" id="price_sats" name="price_sats" step="0.00000001" min="0" value="<?= htmlspecialchars($old['price_sats'] ?? '') ?>">
        <?php if (isset($errors['price_sats'])): ?>
            <div class="error"><?= htmlspecialchars($errors['price_sats']) ?></div>
        <?php endif; ?>
        <small>Enter price in Bitcoin (BTC). Leave empty for free items or "contact for price".</small>
    </div>
    
    <div class="form-group">
        <label for="location">Location</label>
        <input type="text" id="location" name="location" value="<?= htmlspecialchars($old['location'] ?? '') ?>" maxlength="100">
        <?php if (isset($errors['location'])): ?>
            <div class="error"><?= htmlspecialchars($errors['location']) ?></div>
        <?php endif; ?>
        <small>General location (city, region). Be as specific or vague as you're comfortable with.</small>
    </div>
    
    <div class="form-group">
        <label for="images">Images</label>
        <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
        <?php if (isset($errors['images'])): ?>
            <div class="error"><?= htmlspecialchars($errors['images']) ?></div>
        <?php endif; ?>
        <small>Upload up to 5 images. JPEG, PNG, or WebP format. Maximum 5MB per image.</small>
    </div>
    
    <div class="form-group">
        <button type="submit">Create Listing</button>
        <a href="/" style="margin-left: 10px;">Cancel</a>
    </div>
</form>

<div style="margin-top: 30px; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
    <h3>💰 Payment Required</h3>
    <p>To prevent spam, all listings require a small payment to publish:</p>
    <ul>
        <li><strong>Listing Fee:</strong> $<?= number_format($config->get('LISTING_PRICE_CENTS', 100) / 100, 2) ?> USD (paid in cryptocurrency)</li>
        <li><strong>Supported Currencies:</strong> Bitcoin (BTC), Monero (XMR), Ethereum (ETH), Solana (SOL), Dogecoin (DOGE)</li>
        <li><strong>Instant Publishing:</strong> Your listing goes live immediately after payment confirmation</li>
    </ul>
</div>

<div style="margin-top: 20px; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #007bff;">
    <h3>📝 Listing Guidelines</h3>
    <ul>
        <li>Be honest and accurate in your descriptions</li>
        <li>Use clear, well-lit photos</li>
        <li>Respond promptly to inquiries</li>
        <li>Follow all applicable laws and regulations</li>
        <li>No illegal items or services</li>
        <li><strong>No pornography or sexually explicit content of any kind (strictly enforced)</strong></li>
        <li>No personal information in listings (use messaging system)</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>