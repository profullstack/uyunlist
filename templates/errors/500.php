<?php
$title = 'Server Error';
ob_start();
?>

<div style="text-align: center; padding: 50px 0;">
    <h1 style="font-size: 72px; color: #dc3545; margin-bottom: 20px;">500</h1>
    <h2>Internal Server Error</h2>
    <p>Something went wrong on our end. Please try again later.</p>
    <p><a href="/">Return to Home</a></p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>