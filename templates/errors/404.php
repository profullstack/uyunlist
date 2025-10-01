<?php
$title = 'Page Not Found';
ob_start();
?>

<div style="text-align: center; padding: 50px 0;">
    <h1 style="font-size: 72px; color: #666; margin-bottom: 20px;">404</h1>
    <h2>Page Not Found</h2>
    <p>The page you're looking for doesn't exist or has been moved.</p>
    <p><a href="/">Return to Home</a></p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>