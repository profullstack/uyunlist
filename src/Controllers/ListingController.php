<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;

class ListingController extends BaseController
{
    public function show(array $params): void
    {
        $listingId = (int)$params['id'];
        
        // Get listing with category and user info
        $listing = $this->database->queryOne(
            'SELECT l.*, c.name as category_name, u.handle as user_handle, u.about as user_about,
                    u.avatar_path, u.preferred_currency,
                    u.wallet_btc, u.wallet_xmr, u.wallet_eth, u.wallet_sol, u.wallet_doge
             FROM listings l
             JOIN categories c ON l.category_id = c.id
             JOIN users u ON l.user_id = u.id
             WHERE l.id = ?',
            [$listingId]
        );

        if (!$listing) {
            throw new Exception('Listing not found', 404);
        }

        // Drafts (unpublished) are visible only to their owner, as a preview.
        $isOwner = $this->session->isLoggedIn() &&
                   $this->session->getUserId() === (int)$listing['user_id'];
        if (!$listing['is_published'] && !$isOwner) {
            throw new Exception('Listing not found', 404);
        }

        // Get listing images
        $images = $this->database->query(
            'SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id',
            [$listingId]
        );

        // Get threaded comments
        $commentController = new \App\Controllers\CommentController($this->config, $this->database, $this->session, $this->router);
        $comments = $commentController->getThreadedComments($listingId);

        // Increment view count
        $this->database->execute(
            'UPDATE listings SET view_count = view_count + 1 WHERE id = ?',
            [$listingId]
        );

        $this->render('listings/show', [
            'listing' => $listing,
            'images' => $images,
            'comments' => $comments,
            'is_owner' => $isOwner
        ]);
    }

    public function showCreate(): void
    {
        $this->render('listings/create', [
            'categories' => $this->categoryTree()
        ]);
    }

    public function create(): void
    {
        $data = $this->sanitizeArray($_POST);
        
        // Validate input
        $errors = $this->validateInput([
            'title' => 'required|min:3|max:200',
            'body' => 'required|min:10|max:10000',
            'category_id' => 'required|numeric',
            'location' => 'max:100',
            'price_sats' => 'numeric'
        ], $data);

        // Validate category exists
        if (empty($errors['category_id'])) {
            $category = $this->database->queryOne(
                'SELECT id FROM categories WHERE id = ? AND is_active = true',
                [(int)$data['category_id']]
            );
            if (!$category) {
                $errors['category_id'] = 'Invalid category selected';
            }
        }

        // Enforce the strict no-porn policy on the listing text.
        if (empty($errors['title']) && empty($errors['body'])
            && \App\Core\ContentPolicy::violatesNoPorn((string)($data['title'] ?? ''), (string)($data['body'] ?? ''))) {
            $errors['body'] = \App\Core\ContentPolicy::NO_PORN_MESSAGE;
        }

        // Handle image uploads
        $uploadedImages = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $imageResult = $this->handleImageUploads($_FILES['images']);
            if (!$imageResult['success']) {
                $errors['images'] = $imageResult['error'];
            } else {
                $uploadedImages = $imageResult['images'];
            }
        }

        if (!empty($errors)) {
            $this->render('listings/create', [
                'categories' => $this->categoryTree(),
                'errors' => $errors,
                'old' => $data
            ]);
            return;
        }

        try {
            $this->database->beginTransaction();

            // Create listing (unpublished initially - requires payment)
            $listingId = $this->database->insert('listings', [
                'user_id' => $this->session->getUserId(),
                'category_id' => (int)$data['category_id'],
                'title' => $data['title'],
                'body' => $data['body'],
                'price_sats' => !empty($data['price_sats']) ? (int)($data['price_sats'] * 100000000) : 0,
                'location' => $data['location'] ?? '',
                'is_published' => false, // Requires payment to publish
                'is_featured' => false
            ]);

            // Save uploaded images
            foreach ($uploadedImages as $index => $image) {
                $this->database->insert('listing_images', [
                    'listing_id' => $listingId,
                    'path' => $image['path'],
                    'filename' => $image['filename'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'file_size' => $image['file_size'],
                    'sort_order' => $index
                ]);
            }

            $this->database->commit();

            $this->setFlash('success', 'Listing created! You need to pay to publish it.');
            $this->redirect('/pay-to-publish/' . $listingId);

        } catch (Exception $e) {
            $this->database->rollback();
            
            // Clean up uploaded images on error
            foreach ($uploadedImages as $image) {
                if (file_exists($image['path'])) {
                    unlink($image['path']);
                }
            }

            error_log('Listing creation failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to create listing. Please try again.');
            $this->redirectBack('/create-listing');
        }
    }

    public function showEdit(array $params): void
    {
        $listingId = (int)$params['id'];
        $userId = $this->session->getUserId();

        // Get listing - only owner can edit
        $listing = $this->database->queryOne(
            'SELECT * FROM listings WHERE id = ? AND user_id = ?',
            [$listingId, $userId]
        );

        if (!$listing) {
            throw new Exception('Listing not found or access denied', 404);
        }

        // Get existing images
        $images = $this->database->query(
            'SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id',
            [$listingId]
        );

        $this->render('listings/edit', [
            'listing' => $listing,
            'categories' => $this->categoryTree(),
            'images' => $images
        ]);
    }

    public function update(array $params): void
    {
        $listingId = (int)$params['id'];
        $userId = $this->session->getUserId();
        $data = $this->sanitizeArray($_POST);

        // Verify ownership
        $listing = $this->database->queryOne(
            'SELECT * FROM listings WHERE id = ? AND user_id = ?',
            [$listingId, $userId]
        );

        if (!$listing) {
            throw new Exception('Listing not found or access denied', 404);
        }

        // Validate input
        $errors = $this->validateInput([
            'title' => 'required|min:3|max:200',
            'body' => 'required|min:10|max:10000',
            'category_id' => 'required|numeric',
            'location' => 'max:100',
            'price_sats' => 'numeric'
        ], $data);

        // Validate category exists
        if (empty($errors['category_id'])) {
            $category = $this->database->queryOne(
                'SELECT id FROM categories WHERE id = ? AND is_active = true',
                [(int)$data['category_id']]
            );
            if (!$category) {
                $errors['category_id'] = 'Invalid category selected';
            }
        }

        // Enforce the strict no-porn policy on the listing text.
        if (empty($errors['title']) && empty($errors['body'])
            && \App\Core\ContentPolicy::violatesNoPorn((string)($data['title'] ?? ''), (string)($data['body'] ?? ''))) {
            $errors['body'] = \App\Core\ContentPolicy::NO_PORN_MESSAGE;
        }

        // Handle new image uploads
        $uploadedImages = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $imageResult = $this->handleImageUploads($_FILES['images']);
            if (!$imageResult['success']) {
                $errors['images'] = $imageResult['error'];
            } else {
                $uploadedImages = $imageResult['images'];
            }
        }

        // Handle image deletions
        $imagesToDelete = [];
        if (!empty($data['delete_images'])) {
            $imagesToDelete = array_map('intval', $data['delete_images']);
        }

        if (!empty($errors)) {
            $images = $this->database->query(
                'SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id',
                [$listingId]
            );

            $this->render('listings/edit', [
                'listing' => $listing,
                'categories' => $this->categoryTree(),
                'images' => $images,
                'errors' => $errors,
                'old' => $data
            ]);
            return;
        }

        try {
            $this->database->beginTransaction();

            // Update listing
            $this->database->update('listings', [
                'category_id' => (int)$data['category_id'],
                'title' => $data['title'],
                'body' => $data['body'],
                'price_sats' => !empty($data['price_sats']) ? (int)($data['price_sats'] * 100000000) : 0,
                'location' => $data['location'] ?? ''
            ], ['id' => $listingId]);

            // Delete selected images
            if (!empty($imagesToDelete)) {
                $imagesToDeleteData = $this->database->query(
                    'SELECT path FROM listing_images WHERE id = ANY(?) AND listing_id = ?',
                    [$imagesToDelete, $listingId]
                );

                $this->database->execute(
                    'DELETE FROM listing_images WHERE id = ANY(?) AND listing_id = ?',
                    [$imagesToDelete, $listingId]
                );

                // Delete image files
                foreach ($imagesToDeleteData as $imageData) {
                    $fullPath = __DIR__ . '/../../' . $imageData['path'];
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }
            }

            // Add new images
            if (!empty($uploadedImages)) {
                $maxSortOrder = $this->database->queryOne(
                    'SELECT COALESCE(MAX(sort_order), -1) as max_sort FROM listing_images WHERE listing_id = ?',
                    [$listingId]
                )['max_sort'] ?? -1;

                foreach ($uploadedImages as $index => $image) {
                    $this->database->insert('listing_images', [
                        'listing_id' => $listingId,
                        'path' => $image['path'],
                        'filename' => $image['filename'],
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'file_size' => $image['file_size'],
                        'sort_order' => $maxSortOrder + 1 + $index
                    ]);
                }
            }

            $this->database->commit();

            $this->setFlash('success', 'Listing updated successfully!');
            $this->redirect('/listing/' . $listingId);

        } catch (Exception $e) {
            $this->database->rollback();
            
            // Clean up uploaded images on error
            foreach ($uploadedImages as $image) {
                if (file_exists($image['path'])) {
                    unlink($image['path']);
                }
            }

            error_log('Listing update failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to update listing. Please try again.');
            $this->redirectBack('/edit-listing/' . $listingId);
        }
    }

    public function delete(array $params): void
    {
        $listingId = (int)$params['id'];
        $userId = $this->session->getUserId();

        // Verify ownership
        $listing = $this->database->queryOne(
            'SELECT * FROM listings WHERE id = ? AND user_id = ?',
            [$listingId, $userId]
        );

        if (!$listing) {
            throw new Exception('Listing not found or access denied', 404);
        }

        try {
            $this->database->beginTransaction();

            // Get images to delete
            $images = $this->database->query(
                'SELECT path FROM listing_images WHERE listing_id = ?',
                [$listingId]
            );

            // Delete listing (cascade will handle images table)
            $this->database->delete('listings', ['id' => $listingId]);

            // Delete image files
            foreach ($images as $image) {
                $fullPath = __DIR__ . '/../../' . $image['path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            $this->database->commit();

            $this->setFlash('success', 'Listing deleted successfully.');
            $this->redirect('/my-listings');

        } catch (Exception $e) {
            $this->database->rollback();
            error_log('Listing deletion failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to delete listing. Please try again.');
            $this->redirectBack('/listing/' . $listingId);
        }
    }

    public function myListings(): void
    {
        $userId = $this->session->getUserId();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Get total count
        $totalCount = $this->database->queryOne(
            'SELECT COUNT(*) as count FROM listings WHERE user_id = ?',
            [$userId]
        )['count'] ?? 0;

        // Get listings
        $listings = $this->database->query(
            'SELECT l.*, c.name as category_name 
             FROM listings l 
             JOIN categories c ON l.category_id = c.id 
             WHERE l.user_id = ? 
             ORDER BY l.created_at DESC 
             LIMIT ? OFFSET ?',
            [$userId, $perPage, $offset]
        );

        // Get pagination info
        $pagination = $this->getPagination((int)$totalCount, $perPage, $page);

        $this->render('listings/my-listings', [
            'listings' => $listings,
            'pagination' => $pagination
        ]);
    }

    private function handleImageUploads(array $files): array
    {
        $maxFiles = $this->config->get('UPLOAD_MAX_FILES', 20);
        $maxSize = $this->config->get('UPLOAD_MAX_SIZE', 5242880); // 5MB
        $allowedTypes = $this->config->get('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
        
        $uploadDir = __DIR__ . '/../../uploads/listings/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedImages = [];
        $fileCount = count(array_filter($files['name']));

        if ($fileCount > $maxFiles) {
            return ['success' => false, 'error' => "Maximum {$maxFiles} images allowed"];
        }

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error' => 'Upload error occurred'];
            }

            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and WebP are allowed.'];
            }

            // Validate file size
            if ($files['size'][$i] > $maxSize) {
                return ['success' => false, 'error' => 'File too large. Maximum size is ' . ($maxSize / 1024 / 1024) . 'MB.'];
            }

            // Generate unique filename
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };

            $filename = uniqid('listing_', true) . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                return ['success' => false, 'error' => 'Failed to upload file.'];
            }

            // Process image (resize and strip EXIF)
            try {
                $imageInfo = $this->processListingImage($filepath, $mimeType);
                $uploadedImages[] = [
                    'path' => 'uploads/listings/' . $filename,
                    'filename' => $files['name'][$i],
                    'width' => $imageInfo['width'],
                    'height' => $imageInfo['height'],
                    'file_size' => filesize($filepath)
                ];
            } catch (Exception $e) {
                unlink($filepath);
                return ['success' => false, 'error' => 'Failed to process image: ' . $e->getMessage()];
            }
        }

        return ['success' => true, 'images' => $uploadedImages];
    }

    private function processListingImage(string $filepath, string $mimeType): array
    {
        // Downscale to fit 1200x800 and strip ALL metadata (EXIF/GPS/etc.) via
        // Imagick — on every upload, resized or not. See App\Core\ImageProcessor.
        return \App\Core\ImageProcessor::sanitize($filepath, 1200, 800, 85);
    }
}