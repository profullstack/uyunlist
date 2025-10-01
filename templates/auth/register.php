<?php
$title = 'Register';
ob_start();
?>

<h1>Create Account</h1>

<p>Join Onion Classifieds - the privacy-first marketplace accessible only via Tor.</p>

<form method="post" action="/register">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    
    <div class="form-group">
        <label for="handle">Handle (Username)</label>
        <input type="text" id="handle" name="handle" value="<?= htmlspecialchars($old['handle'] ?? '') ?>" required>
        <?php if (isset($errors['handle'])): ?>
            <div class="error"><?= htmlspecialchars($errors['handle']) ?></div>
        <?php endif; ?>
        <small>3-50 characters. This will be your public username.</small>
    </div>
    
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <?php if (isset($errors['password'])): ?>
            <div class="error"><?= htmlspecialchars($errors['password']) ?></div>
        <?php endif; ?>
        <small>Minimum 8 characters. Use a strong, unique password.</small>
    </div>
    
    <div class="form-group">
        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required>
        <?php if (isset($errors['password_confirm'])): ?>
            <div class="error"><?= htmlspecialchars($errors['password_confirm']) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="form-group">
        <button type="submit">Create Account</button>
    </div>
</form>

<p>Already have an account? <a href="/login">Login here</a></p>

<div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff;">
    <h3>Privacy Notice</h3>
    <ul>
        <li>No email address required - just a handle and password</li>
        <li>Your IP address is protected by Tor</li>
        <li>We don't track or log personal information</li>
        <li>All communications are encrypted</li>
        <li>Account data is stored securely with industry-standard encryption</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>