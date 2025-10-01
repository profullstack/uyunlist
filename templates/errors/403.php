<?php
$title = 'Access Forbidden';
ob_start();
?>

<div style="text-align: center; padding: 50px 0;">
    <h1 style="font-size: 72px; color: #dc3545; margin-bottom: 20px;">403</h1>
    <h2>Access Forbidden</h2>
    <p>You don't have permission to access this resource.</p>
    <p><a href="/">Return to Home</a></p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>