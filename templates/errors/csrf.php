<?php
$title = 'Security Error';
ob_start();
?>

<div style="text-align: center; padding: 50px 0;">
    <h1 style="font-size: 72px; color: #dc3545; margin-bottom: 20px;">403</h1>
    <h2>Security Token Mismatch</h2>
    <p>Your session has expired or the security token is invalid.</p>
    <p>Please refresh the page and try again.</p>
    <p><a href="javascript:history.back()">Go Back</a> | <a href="/">Return to Home</a></p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>