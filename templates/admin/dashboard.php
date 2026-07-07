<?php
$title = $title ?? 'Admin';
ob_start();
?>

<h1>Admin Dashboard</h1>

<nav class="admin-nav" style="margin:1rem 0;display:flex;gap:1rem;flex-wrap:wrap;">
    <a href="/admin">Dashboard</a>
    <a href="/admin/users">Users</a>
    <a href="/admin/listings">Listings</a>
    <a href="/admin/reports">Reports<?= ($stats['reports_pending'] ?? 0) > 0 ? ' (' . (int)$stats['reports_pending'] . ')' : '' ?></a>
    <a href="/admin/invoices">Invoices</a>
</nav>

<div class="admin-stats" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <?php
    $cards = [
        'Users' => $stats['users'] ?? 0,
        'Admins' => $stats['admins'] ?? 0,
        'Listings' => $stats['listings'] ?? 0,
        'Live listings' => $stats['listings_live'] ?? 0,
        'Reports (pending)' => $stats['reports_pending'] ?? 0,
        'Reports (total)' => $stats['reports_total'] ?? 0,
    ];
    foreach ($cards as $label => $value): ?>
        <div style="border:1px solid #444;border-radius:6px;padding:.75rem 1rem;min-width:120px;">
            <div style="font-size:1.6rem;font-weight:bold;"><?= (int)$value ?></div>
            <div style="opacity:.8;"><?= htmlspecialchars($label) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<h2>Recent listings</h2>
<table style="width:100%;border-collapse:collapse;">
    <tr><th align="left">Title</th><th align="left">By</th><th align="left">Status</th><th align="left">When</th></tr>
    <?php foreach ($recent_listings as $l): ?>
        <tr>
            <td><a href="/listing/<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['title']) ?></a></td>
            <td><?= htmlspecialchars($l['user_handle']) ?></td>
            <td><?= $l['is_published'] ? 'Live' : 'Hidden' ?></td>
            <td><?= htmlspecialchars((string)$l['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2 style="margin-top:1.5rem;">Recent reports</h2>
<table style="width:100%;border-collapse:collapse;">
    <tr><th align="left">Reason</th><th align="left">By</th><th align="left">Status</th><th align="left">When</th></tr>
    <?php foreach ($recent_reports as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['reason']) ?></td>
            <td><?= htmlspecialchars((string)($r['reporter'] ?? '—')) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
            <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
