<?php
$title = 'Profile';
ob_start();
?>

<h1>My Profile</h1>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-bottom: 30px;">
    <div>
        <h2>Account Info</h2>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
            <p><strong>Handle:</strong> <?= htmlspecialchars($user['handle']) ?></p>
            <p><strong>Member since:</strong> <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
            <p><strong>Listings posted:</strong> <?= (int)$listing_count ?></p>
            <p><strong>Messages sent:</strong> <?= (int)$message_count ?></p>
            <?php if ($user['is_admin']): ?>
                <p><strong>Role:</strong> <span style="color: #dc3545;">Administrator</span></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div>
        <h2>Update Profile</h2>
        
        <form method="post" action="/profile" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="form-group">
                <label for="about">About Me</label>
                <textarea id="about" name="about" placeholder="Tell others about yourself..."><?= htmlspecialchars($old['about'] ?? $user['about']) ?></textarea>
                <?php if (isset($errors['about'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['about']) ?></div>
                <?php endif; ?>
                <small>Maximum 1000 characters. This will be visible to other users.</small>
            </div>
            
            <div class="form-group">
                <label for="avatar">Avatar Image</label>
                <?php if (!empty($user['avatar_path'])): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="/<?= htmlspecialchars($user['avatar_path']) ?>" alt="Current avatar" style="max-width: 100px; max-height: 100px; border-radius: 5px;">
                        <p><small>Current avatar</small></p>
                    </div>
                <?php endif; ?>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                <?php if (isset($errors['avatar'])): ?>
                    <div class="error"><?= htmlspecialchars($errors['avatar']) ?></div>
                <?php endif; ?>
                <small>JPEG, PNG, or WebP. Maximum 2MB. Will be resized to 200x200 pixels.</small>
            </div>
            
            <div class="form-group">
                <button type="submit">Update Profile</button>
            </div>
        </form>
    </div>
</div>

<div style="border-top: 2px solid #dee2e6; padding-top: 30px;">
    <h2>Change Password</h2>
    
    <form method="post" action="/profile" style="max-width: 500px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password">
            <?php if (isset($errors['current_password'])): ?>
                <div class="error"><?= htmlspecialchars($errors['current_password']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password">
            <?php if (isset($errors['new_password'])): ?>
                <div class="error"><?= htmlspecialchars($errors['new_password']) ?></div>
            <?php endif; ?>
            <small>Minimum 8 characters. Use a strong, unique password.</small>
        </div>
        
        <div class="form-group">
            <label for="new_password_confirm">Confirm New Password</label>
            <input type="password" id="new_password_confirm" name="new_password_confirm">
            <?php if (isset($errors['new_password_confirm'])): ?>
                <div class="error"><?= htmlspecialchars($errors['new_password_confirm']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <button type="submit">Change Password</button>
        </div>
    </form>
</div>

<div style="margin-top: 30px; padding: 15px; background-color: #f8d7da; border-left: 4px solid #dc3545;">
    <h3>Account Security</h3>
    <ul>
        <li>Your password is encrypted with Argon2id hashing</li>
        <li>Sessions are secured with CSRF protection</li>
        <li>Always log out when finished</li>
        <li>There is no password recovery - keep your password safe</li>
        <li>Consider using a password manager</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>