<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;

class AuthController extends BaseController
{
    public function showRegister(): void
    {
        $this->render('auth/register');
    }

    public function register(): void
    {
        $data = $this->sanitizeArray($_POST);
        
        // Validate input
        $errors = $this->validateInput([
            'handle' => 'required|min:3|max:50|unique:users',
            'password' => 'required|min:8|max:128',
            'password_confirm' => 'required'
        ], $data);

        // Check password confirmation
        if (empty($errors) && $data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'Password confirmation does not match';
        }

        if (!empty($errors)) {
            $this->render('auth/register', [
                'errors' => $errors,
                'old' => $data
            ]);
            return;
        }

        try {
            // Hash password using Argon2id
            $passwordHash = password_hash($data['password'], PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,       // 4 iterations
                'threads' => 3          // 3 threads
            ]);

            // Create user
            $userId = $this->database->insert('users', [
                'handle' => $data['handle'],
                'pass_hash' => $passwordHash,
                'about' => '',
                'avatar_path' => '',
                'is_admin' => false
            ]);

            // Log the user in
            $this->session->login($userId);
            
            $this->setFlash('success', 'Account created successfully! Welcome to Onion Classifieds.');
            $this->redirect('/');

        } catch (Exception $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $this->setFlash('error', 'Registration failed. Please try again.');
            $this->render('auth/register', [
                'errors' => [],
                'old' => $data
            ]);
        }
    }

    public function showLogin(): void
    {
        $this->render('auth/login');
    }

    public function login(): void
    {
        $data = $this->sanitizeArray($_POST);
        
        // Validate input
        $errors = $this->validateInput([
            'handle' => 'required',
            'password' => 'required'
        ], $data);

        if (!empty($errors)) {
            $this->render('auth/login', [
                'errors' => $errors,
                'old' => $data
            ]);
            return;
        }

        try {
            // Find user by handle
            $user = $this->database->queryOne(
                'SELECT id, handle, pass_hash FROM users WHERE handle = ?',
                [$data['handle']]
            );

            if (!$user || !password_verify($data['password'], $user['pass_hash'])) {
                $this->render('auth/login', [
                    'errors' => ['login' => 'Invalid handle or password'],
                    'old' => $data
                ]);
                return;
            }

            // Check if password needs rehashing (security upgrade)
            if (password_needs_rehash($user['pass_hash'], PASSWORD_ARGON2ID)) {
                $newHash = password_hash($data['password'], PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost' => 4,
                    'threads' => 3
                ]);
                
                $this->database->update('users', 
                    ['pass_hash' => $newHash], 
                    ['id' => $user['id']]
                );
            }

            // Log the user in
            $this->session->login((int)$user['id']);
            
            $this->setFlash('success', 'Welcome back, ' . htmlspecialchars($user['handle']) . '!');
            
            // Redirect to intended page or home
            $redirectTo = $_GET['redirect'] ?? '/';
            $this->redirect($redirectTo);

        } catch (Exception $e) {
            error_log('Login failed: ' . $e->getMessage());
            $this->setFlash('error', 'Login failed. Please try again.');
            $this->render('auth/login', [
                'errors' => [],
                'old' => $data
            ]);
        }
    }

    public function logout(): void
    {
        $this->session->logout();
        $this->setFlash('success', 'You have been logged out successfully.');
        $this->redirect('/');
    }

    public function showProfile(): void
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        // Get user's listing count
        $listingCount = $this->database->queryOne(
            'SELECT COUNT(*) as count FROM listings WHERE user_id = ?',
            [$user['id']]
        )['count'] ?? 0;

        // Get user's message count
        $messageCount = $this->database->queryOne(
            'SELECT COUNT(*) as count FROM messages WHERE sender_id = ?',
            [$user['id']]
        )['count'] ?? 0;

        $this->render('auth/profile', [
            'user' => $user,
            'listing_count' => $listingCount,
            'message_count' => $messageCount
        ]);
    }

    public function updateProfile(): void
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $data = $this->sanitizeArray($_POST);
        
        // Validate input
        $errors = $this->validateInput([
            'about' => 'max:1000'
        ], $data);

        // Handle password change if provided
        if (!empty($data['new_password'])) {
            $passwordErrors = $this->validateInput([
                'current_password' => 'required',
                'new_password' => 'required|min:8|max:128',
                'new_password_confirm' => 'required'
            ], $data);

            $errors = array_merge($errors, $passwordErrors);

            if (empty($passwordErrors)) {
                // Verify current password
                $currentUser = $this->database->queryOne(
                    'SELECT pass_hash FROM users WHERE id = ?',
                    [$user['id']]
                );

                if (!password_verify($data['current_password'], $currentUser['pass_hash'])) {
                    $errors['current_password'] = 'Current password is incorrect';
                }

                if ($data['new_password'] !== $data['new_password_confirm']) {
                    $errors['new_password_confirm'] = 'Password confirmation does not match';
                }
            }
        }

        // Handle avatar upload
        $avatarPath = $user['avatar_path'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatarResult = $this->handleAvatarUpload($_FILES['avatar']);
            if ($avatarResult['success']) {
                // Delete old avatar if exists
                if (!empty($user['avatar_path']) && file_exists($user['avatar_path'])) {
                    unlink($user['avatar_path']);
                }
                $avatarPath = $avatarResult['path'];
            } else {
                $errors['avatar'] = $avatarResult['error'];
            }
        }

        if (!empty($errors)) {
            $this->render('auth/profile', [
                'user' => $user,
                'errors' => $errors,
                'old' => $data
            ]);
            return;
        }

        try {
            $updateData = [
                'about' => $data['about'] ?? '',
                'avatar_path' => $avatarPath
            ];

            // Update password if provided
            if (!empty($data['new_password'])) {
                $updateData['pass_hash'] = password_hash($data['new_password'], PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost' => 4,
                    'threads' => 3
                ]);
            }

            $this->database->update('users', $updateData, ['id' => $user['id']]);
            
            $this->setFlash('success', 'Profile updated successfully!');
            $this->redirect('/profile');

        } catch (Exception $e) {
            error_log('Profile update failed: ' . $e->getMessage());
            $this->setFlash('error', 'Profile update failed. Please try again.');
            $this->render('auth/profile', [
                'user' => $user,
                'errors' => [],
                'old' => $data
            ]);
        }
    }

    private function handleAvatarUpload(array $file): array
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        $uploadDir = __DIR__ . '/../../uploads/avatars/';

        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and WebP are allowed.'];
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File too large. Maximum size is 2MB.'];
        }

        // Generate unique filename
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg'
        };

        $filename = uniqid('avatar_', true) . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Failed to upload file.'];
        }

        // Process image (resize and strip EXIF)
        try {
            $this->processAvatar($filepath, $mimeType);
        } catch (Exception $e) {
            unlink($filepath);
            return ['success' => false, 'error' => 'Failed to process image.'];
        }

        return ['success' => true, 'path' => 'uploads/avatars/' . $filename];
    }

    private function processAvatar(string $filepath, string $mimeType): void
    {
        $maxWidth = 200;
        $maxHeight = 200;

        // Create image resource
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($filepath),
            'image/png' => imagecreatefrompng($filepath),
            'image/webp' => imagecreatefromwebp($filepath),
            default => throw new Exception('Unsupported image type')
        };

        if (!$image) {
            throw new Exception('Failed to create image resource');
        }

        // Get original dimensions
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Calculate new dimensions
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and WebP
        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        // Resize image
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Save processed image
        match ($mimeType) {
            'image/jpeg' => imagejpeg($newImage, $filepath, 85),
            'image/png' => imagepng($newImage, $filepath, 6),
            'image/webp' => imagewebp($newImage, $filepath, 85),
            default => throw new Exception('Unsupported image type')
        };

        // Clean up
        imagedestroy($image);
        imagedestroy($newImage);
    }
}