<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;

class ImageController extends BaseController
{
    public function serve(array $params): void
    {
        $imageId = (int)$params['id'];
        
        // Get image info from database
        $image = $this->database->queryOne(
            'SELECT li.*, l.user_id, l.is_published 
             FROM listing_images li 
             JOIN listings l ON li.listing_id = l.id 
             WHERE li.id = ?',
            [$imageId]
        );

        if (!$image) {
            throw new Exception('Image not found', 404);
        }

        // Check access permissions
        $canAccess = false;
        
        // Published listings are public
        if ($image['is_published']) {
            $canAccess = true;
        }
        // Owner can always see their images
        elseif ($this->session->isLoggedIn() && $this->session->getUserId() === (int)$image['user_id']) {
            $canAccess = true;
        }
        // Admins can see all images
        elseif ($this->session->isLoggedIn()) {
            $user = $this->getCurrentUser();
            if ($user && $user['is_admin']) {
                $canAccess = true;
            }
        }

        if (!$canAccess) {
            throw new Exception('Access denied', 403);
        }

        // Build full file path
        $fullPath = __DIR__ . '/../../' . $image['path'];
        
        if (!file_exists($fullPath)) {
            throw new Exception('Image file not found', 404);
        }

        // Determine content type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        // Security check - ensure it's actually an image
        if (!str_starts_with($mimeType, 'image/')) {
            throw new Exception('Invalid file type', 403);
        }

        // Set appropriate headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        // Set filename for download
        $filename = $image['filename'] ?: 'image_' . $imageId . '.' . pathinfo($fullPath, PATHINFO_EXTENSION);
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');

        // Output file
        readfile($fullPath);
        exit;
    }

    public function serveAvatar(array $params): void
    {
        $userId = (int)$params['user_id'];
        
        // Get user info
        $user = $this->database->queryOne(
            'SELECT avatar_path FROM users WHERE id = ?',
            [$userId]
        );

        if (!$user || empty($user['avatar_path'])) {
            // Serve default avatar or 404
            throw new Exception('Avatar not found', 404);
        }

        // Build full file path
        $fullPath = __DIR__ . '/../../' . $user['avatar_path'];
        
        if (!file_exists($fullPath)) {
            throw new Exception('Avatar file not found', 404);
        }

        // Determine content type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        // Security check - ensure it's actually an image
        if (!str_starts_with($mimeType, 'image/')) {
            throw new Exception('Invalid file type', 403);
        }

        // Set appropriate headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: public, max-age=86400'); // Cache for 1 day
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
        
        // Output file
        readfile($fullPath);
        exit;
    }

    public function thumbnail(array $params): void
    {
        $imageId = (int)$params['id'];
        $size = (int)($_GET['size'] ?? 300);
        
        // Limit thumbnail sizes for security
        $allowedSizes = [100, 200, 300, 400];
        if (!in_array($size, $allowedSizes)) {
            $size = 300;
        }

        // Get image info from database
        $image = $this->database->queryOne(
            'SELECT li.*, l.user_id, l.is_published 
             FROM listing_images li 
             JOIN listings l ON li.listing_id = l.id 
             WHERE li.id = ?',
            [$imageId]
        );

        if (!$image) {
            throw new Exception('Image not found', 404);
        }

        // Check access permissions (same as serve method)
        $canAccess = false;
        
        if ($image['is_published']) {
            $canAccess = true;
        } elseif ($this->session->isLoggedIn() && $this->session->getUserId() === (int)$image['user_id']) {
            $canAccess = true;
        } elseif ($this->session->isLoggedIn()) {
            $user = $this->getCurrentUser();
            if ($user && $user['is_admin']) {
                $canAccess = true;
            }
        }

        if (!$canAccess) {
            throw new Exception('Access denied', 403);
        }

        // Build paths
        $fullPath = __DIR__ . '/../../' . $image['path'];
        $thumbnailDir = __DIR__ . '/../../uploads/thumbnails/';
        $thumbnailPath = $thumbnailDir . $imageId . '_' . $size . '.jpg';
        
        if (!file_exists($fullPath)) {
            throw new Exception('Image file not found', 404);
        }

        // Create thumbnail directory if it doesn't exist
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        // Generate thumbnail if it doesn't exist or is older than original
        if (!file_exists($thumbnailPath) || filemtime($thumbnailPath) < filemtime($fullPath)) {
            $this->generateThumbnail($fullPath, $thumbnailPath, $size);
        }

        // Serve thumbnail
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($thumbnailPath));
        header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        readfile($thumbnailPath);
        exit;
    }

    private function generateThumbnail(string $sourcePath, string $thumbnailPath, int $size): void
    {
        // Square, center-cropped, metadata-stripped JPEG thumbnail via Imagick.
        // See App\Core\ImageProcessor.
        \App\Core\ImageProcessor::squareThumb($sourcePath, $thumbnailPath, $size);
    }
}