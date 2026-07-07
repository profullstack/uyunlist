<?php
$title = $title ?? 'Admin · Listings';
ob_start();

/** Small helper: a one-button moderation form. */
$modBtn = function (string $action, int $id, string $label, string $csrf): string {
    return '<form method="post" action="/admin/moderate" style="display:inline;">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">'
        . '<input type="hidden" name="redirect" value="/admin/listings">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit">' . htmlspecialchars($label) . '</button></form> ';
};
?>

<h1>Listings</h1>

<nav class="admin-nav" style="margin:1rem 0;display:flex;gap:1rem;flex-wrap:wrap;">
    <a href="/admin">Dashboard</a>
    <a href="/admin/users">Users</a>
    <a href="/admin/listings">Listings</a>
    <a href="/admin/reports">Reports</a>
    <a href="/admin/invoices">Invoices</a>
</nav>

<table style="width:100%;border-collapse:collapse;">
    <tr><th align="left">Title</th><th align="left">By</th><th align="left">Category</th><th align="left">Status</th><th align="left">Actions</th></tr>
    <?php foreach ($listings as $l): $id = (int)$l['id']; ?>
        <tr>
            <td><a href="/listing/<?= $id ?>"><?= htmlspecialchars($l['title']) ?></a></td>
            <td><?= htmlspecialchars($l['user_handle']) ?></td>
            <td><?= htmlspecialchars((string)($l['category_name'] ?? '—')) ?></td>
            <td>
                <?= $l['is_published'] ? 'Live' : 'Hidden' ?><?= $l['is_featured'] ? ' · ⭐' : '' ?>
            </td>
            <td>
                <?php
                echo $l['is_published']
                    ? $modBtn('listing_unpublish', $id, 'Hide', $csrf_token)
                    : $modBtn('listing_publish', $id, 'Publish', $csrf_token);
                echo $l['is_featured']
                    ? $modBtn('listing_unfeature', $id, 'Unfeature', $csrf_token)
                    : $modBtn('listing_feature', $id, 'Feature', $csrf_token);
                echo $modBtn('listing_delete', $id, 'Delete', $csrf_token);
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
