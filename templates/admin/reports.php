<?php
$title = $title ?? 'Admin · Reports';
ob_start();

$modBtn = function (string $action, int $id, string $label, string $csrf): string {
    return '<form method="post" action="/admin/moderate" style="display:inline;">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">'
        . '<input type="hidden" name="redirect" value="/admin/reports">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit">' . htmlspecialchars($label) . '</button></form> ';
};
?>

<h1>Reports</h1>

<nav class="admin-nav" style="margin:1rem 0;display:flex;gap:1rem;flex-wrap:wrap;">
    <a href="/admin">Dashboard</a>
    <a href="/admin/users">Users</a>
    <a href="/admin/listings">Listings</a>
    <a href="/admin/reports">Reports</a>
</nav>

<?php if (empty($reports)): ?>
    <p>No reports. 🎉</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;">
    <tr>
        <th align="left">Status</th><th align="left">Reason</th><th align="left">Target</th>
        <th align="left">Reporter</th><th align="left">When</th><th align="left">Actions</th>
    </tr>
    <?php foreach ($reports as $r): $id = (int)$r['id']; ?>
        <tr>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td>
                <?= htmlspecialchars($r['reason']) ?>
                <?php if (!empty($r['description'])): ?>
                    <div style="opacity:.75;font-size:.9em;"><?= htmlspecialchars($r['description']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($r['listing_title'])): ?>
                    <a href="/listing/<?= (int)$r['listing_id'] ?>"><?= htmlspecialchars($r['listing_title']) ?></a>
                <?php elseif (!empty($r['reported_handle'])): ?>
                    user: <?= htmlspecialchars($r['reported_handle']) ?>
                <?php elseif (!empty($r['message_id'])): ?>
                    message #<?= (int)$r['message_id'] ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)($r['reporter_handle'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
            <td>
                <?php if ($r['status'] === 'pending'): ?>
                    <?= $modBtn('report_resolve', $id, 'Resolve', $csrf_token) ?>
                    <?= $modBtn('report_dismiss', $id, 'Dismiss', $csrf_token) ?>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
