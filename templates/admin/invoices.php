<?php
$title = $title ?? 'Admin · Invoices';
ob_start();

$modBtn = function (string $action, int $id, string $label, string $csrf, string $color = ''): string {
    $style = $color ? " style=\"color:{$color};\"" : '';
    return '<form method="post" action="/admin/moderate" style="display:inline;">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '">'
        . '<input type="hidden" name="redirect" value="/admin/invoices">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit"' . $style . '>' . htmlspecialchars($label) . '</button></form> ';
};
?>

<h1>Invoices</h1>

<nav class="admin-nav" style="margin:1rem 0;display:flex;gap:1rem;flex-wrap:wrap;">
    <a href="/admin">Dashboard</a>
    <a href="/admin/users">Users</a>
    <a href="/admin/listings">Listings</a>
    <a href="/admin/reports">Reports</a>
    <a href="/admin/invoices">Invoices</a>
</nav>

<p style="opacity:.8;font-size:.9em;">Payments go directly to your wallet — verify one arrived, then <strong>Mark paid</strong> to process it (publishes the listing).</p>

<?php if (empty($invoices)): ?>
    <p>No invoices yet.</p>
<?php else: ?>
<table style="width:100%;border-collapse:collapse;">
    <tr>
        <th align="left">Status</th><th align="left">User</th><th align="left">Purpose</th>
        <th align="left">Amount</th><th align="left">Pay to</th><th align="left">When</th><th align="left">Actions</th>
    </tr>
    <?php foreach ($invoices as $i): $id = (int)$i['id']; ?>
        <tr>
            <td><?= htmlspecialchars($i['status']) ?></td>
            <td><?= htmlspecialchars((string)($i['handle'] ?? '—')) ?></td>
            <td><?= htmlspecialchars($i['purpose']) ?></td>
            <td>
                <?= rtrim(rtrim(number_format((float)$i['crypto_amount'], 8, '.', ''), '0'), '.') ?>
                <?= htmlspecialchars(strtoupper($i['currency'])) ?>
                <span style="opacity:.7;">($<?= number_format((float)$i['fiat_amount'], 2) ?>)</span>
            </td>
            <td><code style="word-break:break-all;font-size:11px;"><?= htmlspecialchars($i['address_in']) ?></code></td>
            <td><?= htmlspecialchars((string)$i['created_at']) ?></td>
            <td>
                <?php if ($i['status'] !== 'settled' && $i['status'] !== 'cancelled'): ?>
                    <?= $modBtn('invoice_confirm', $id, 'Mark paid', $csrf_token) ?>
                    <?= $modBtn('invoice_cancel', $id, 'Cancel', $csrf_token, '#b00') ?>
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
