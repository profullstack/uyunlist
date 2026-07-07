<?php
$title = $title ?? 'Admin · Users';
$me = $current_user['id'] ?? null;
ob_start();
?>

<h1>Users</h1>

<nav class="admin-nav" style="margin:1rem 0;display:flex;gap:1rem;flex-wrap:wrap;">
    <a href="/admin">Dashboard</a>
    <a href="/admin/users">Users</a>
    <a href="/admin/listings">Listings</a>
    <a href="/admin/reports">Reports</a>
    <a href="/admin/invoices">Invoices</a>
</nav>

<table style="width:100%;border-collapse:collapse;">
    <tr><th align="left">Handle</th><th align="left">Admin</th><th align="left">Listings</th><th align="left">Joined</th><th align="left">Actions</th></tr>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['handle']) ?></td>
            <td><?= $u['is_admin'] ? '✅' : '—' ?></td>
            <td><?= (int)$u['listing_count'] ?></td>
            <td><?= htmlspecialchars((string)$u['created_at']) ?></td>
            <td>
                <?php if (!$u['is_admin']): ?>
                    <form method="post" action="/admin/moderate" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="redirect" value="/admin/users">
                        <input type="hidden" name="action" value="user_promote">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit">Make admin</button>
                    </form>
                <?php elseif ((int)$u['id'] !== (int)$me): ?>
                    <form method="post" action="/admin/moderate" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="redirect" value="/admin/users">
                        <input type="hidden" name="action" value="user_demote">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit">Remove admin</button>
                    </form>
                <?php endif; ?>
                <?php if ((int)$u['id'] !== (int)$me): ?>
                    <form method="post" action="/admin/moderate" style="display:inline;"
                          onsubmit="return confirm('Delete <?= htmlspecialchars($u['handle'], ENT_QUOTES) ?> and ALL their listings/messages? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="redirect" value="/admin/users">
                        <input type="hidden" name="action" value="user_delete">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" style="color:#b00;">Delete</button>
                    </form>
                <?php else: ?>
                    <em>you</em>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
