<?php
$title = 'Edit Listing';
ob_start();
$val = fn(string $f) => htmlspecialchars((string)($old[$f] ?? $listing[$f] ?? ''));
$isDraft = !($listing['is_published'] ?? false);
?>

<h1>Edit Listing</h1>

<?php if ($isDraft): ?>
    <div style="margin: 15px 0; padding: 12px 15px; background:#fff3cd; border-left:4px solid #ffc107; color:#856404;">
        <strong>Draft — not published yet.</strong> Review and edit below, then
        <a href="/pay-to-publish/<?= (int)$listing['id'] ?>">proceed to payment</a> to publish it.
    </div>
<?php endif; ?>

<!-- Preview -->
<div style="border:1px solid #ddd; border-radius:5px; padding:20px; margin-bottom:25px; background:#fafafa;">
    <div style="font-size:13px; text-transform:uppercase; color:#888; margin-bottom:8px;">Preview</div>
    <h2 style="margin:0 0 6px;"><?= $val('title') ?: '(no title)' ?></h2>
    <div style="color:#666; font-size:14px; margin-bottom:10px;">
        <?php if (($listing['price_sats'] ?? '') !== '' && (float)$listing['price_sats'] > 0): ?>
            <strong><?= htmlspecialchars(rtrim(rtrim(number_format((float)$listing['price_sats'], 8, '.', ''), '0'), '.')) ?> BTC</strong> ·
        <?php endif; ?>
        <?= $val('location') ? $val('location') . ' · ' : '' ?>
        <?php
        $catName = '';
        foreach ($categories as $top) {
            if ((string)$top['id'] === (string)($listing['category_id'] ?? '')) { $catName = $top['name']; }
            foreach ($top['children'] ?? [] as $sub) {
                if ((string)$sub['id'] === (string)($listing['category_id'] ?? '')) { $catName = $top['name'] . ' › ' . $sub['name']; }
            }
        }
        echo htmlspecialchars($catName);
        ?>
    </div>
    <?php if (!empty($images)): ?>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
            <?php foreach ($images as $img): ?>
                <img src="/<?= htmlspecialchars(ltrim((string)$img['path'], '/')) ?>" alt=""
                     style="width:90px; height:90px; object-fit:cover; border-radius:4px; border:1px solid #ccc;">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div style="white-space:pre-wrap;"><?= $val('body') ?></div>
</div>

<h2>Edit</h2>
<form method="post" action="/edit-listing/<?= (int)$listing['id'] ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="form-group">
        <label for="title">Title *</label>
        <input type="text" id="title" name="title" value="<?= $val('title') ?>" required maxlength="200">
        <?php if (isset($errors['title'])): ?><div class="error"><?= htmlspecialchars($errors['title']) ?></div><?php endif; ?>
    </div>

    <div class="form-group">
        <label for="category_id">Category *</label>
        <select id="category_id" name="category_id" required>
            <option value="">Select a category</option>
            <?php foreach ($categories as $top):
                $selCat = (string)($old['category_id'] ?? $listing['category_id'] ?? ''); ?>
                <optgroup label="<?= htmlspecialchars($top['name']) ?>">
                    <option value="<?= $top['id'] ?>" <?= $selCat == $top['id'] ? 'selected' : '' ?>><?= htmlspecialchars($top['name']) ?> (general)</option>
                    <?php foreach ($top['children'] ?? [] as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= $selCat == $sub['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sub['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['category_id'])): ?><div class="error"><?= htmlspecialchars($errors['category_id']) ?></div><?php endif; ?>
    </div>

    <div class="form-group">
        <label for="body">Description *</label>
        <textarea id="body" name="body" required maxlength="10000" rows="8"><?= $val('body') ?></textarea>
        <?php if (isset($errors['body'])): ?><div class="error"><?= htmlspecialchars($errors['body']) ?></div><?php endif; ?>
    </div>

    <div class="form-group">
        <label for="price_sats">Price (BTC)</label>
        <input type="number" id="price_sats" name="price_sats" step="0.00000001" min="0" value="<?= $val('price_sats') ?>">
        <?php if (isset($errors['price_sats'])): ?><div class="error"><?= htmlspecialchars($errors['price_sats']) ?></div><?php endif; ?>
        <small>Leave empty for free / contact for price.</small>
    </div>

    <div class="form-group">
        <label for="location">Location</label>
        <input type="text" id="location" name="location" value="<?= $val('location') ?>" maxlength="100">
        <?php if (isset($errors['location'])): ?><div class="error"><?= htmlspecialchars($errors['location']) ?></div><?php endif; ?>
    </div>

    <div class="form-group">
        <label for="images">Add images</label>
        <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp">
        <?php if (isset($errors['images'])): ?><div class="error"><?= htmlspecialchars($errors['images']) ?></div><?php endif; ?>
        <small>Up to 20 images. New uploads are added to the listing. Metadata (EXIF/GPS) is stripped automatically.</small>
    </div>

    <div class="form-group">
        <button type="submit">Save changes</button>
        <?php if ($isDraft): ?>
            <a href="/pay-to-publish/<?= (int)$listing['id'] ?>" style="margin-left: 10px;">Save &amp; continue to payment →</a>
        <?php else: ?>
            <a href="/listing/<?= (int)$listing['id'] ?>" style="margin-left: 10px;">View listing</a>
        <?php endif; ?>
        <a href="/my-listings" style="margin-left: 10px;">Cancel</a>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
