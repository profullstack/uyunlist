<?php
$title = 'Login';
ob_start();
?>

<h1>Login</h1>

<p>Access your Onion Classifieds account securely via Tor.</p>

<form method="post" action="/login">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    
    <?php if (isset($errors['login'])): ?>
        <div class="flash error">
            <?= htmlspecialchars($errors['login']) ?>
        </div>
    <?php endif; ?>
    
    <div class="form-group">
        <label for="handle">Handle (Username)</label>
        <input type="text" id="handle" name="handle" value="<?= htmlspecialchars($old['handle'] ?? '') ?>" required>
        <?php if (isset($errors['handle'])): ?>
            <div class="error"><?= htmlspecialchars($errors['handle']) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <?php if (isset($errors['password'])): ?>
            <div class="error"><?= htmlspecialchars($errors['password']) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <button type="submit">Login</button>
    </div>
</form>

<p>Don't have an account? <a href="/register">Register here</a></p>

<div style="margin-top: 30px; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
    <h3>Security Notice</h3>
    <ul>
        <li>Always access this site through Tor Browser</li>
        <li>Verify the .onion address is correct</li>
        <li>Never share your login credentials</li>
        <li>Log out when finished for maximum security</li>
        <li>No password recovery via email - keep your password safe</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>