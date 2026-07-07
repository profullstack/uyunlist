<?php
/**
 * Standalone payment-status fragment shown inside an iframe on the pay page.
 * No JavaScript — it auto-updates with a <meta http-equiv="refresh"> tag.
 * Expects: $invoice, $status, $listingId.
 */
$isSettled = ($status === 'settled') || ($invoice['status'] ?? '') === 'settled';
$isExpired = !$isSettled && strtotime((string)$invoice['expires_at']) < time();
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!$isSettled && !$isExpired): ?>
        <meta http-equiv="refresh" content="5">
    <?php endif; ?>
    <style>
        html,body{margin:0;padding:0;background:transparent;}
        body{font-family:system-ui,sans-serif;color:#222;text-align:center;padding:14px 10px;}
        .ok{color:#155724;} .warn{color:#721c24;} .muted{color:#666;font-size:13px;margin-top:6px;}
        a{color:#007bff;}
        .big{font-size:16px;font-weight:bold;}
    </style>
</head>
<body>
    <?php if ($isSettled): ?>
        <div class="big ok">✅ Payment received!</div>
        <?php if ($listingId): ?>
            <p><a href="/listing/<?= (int)$listingId ?>" target="_top">View your published listing →</a></p>
        <?php else: ?>
            <p><a href="/my-listings" target="_top">Continue →</a></p>
        <?php endif; ?>
    <?php elseif ($isExpired): ?>
        <div class="big warn">⏰ This payment expired.</div>
        <p><a href="/my-listings" target="_top">Create a new listing →</a></p>
    <?php else: ?>
        <div class="big">⏳ Waiting for payment…</div>
        <div class="muted">Auto-checking every 5 seconds.</div>
    <?php endif; ?>
</body>
</html>
