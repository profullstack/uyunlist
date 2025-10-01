<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;

class CommentController extends BaseController
{
    public function addComment(): void
    {
        $data = $this->sanitizeArray($_POST);
        $userId = $this->session->getUserId();

        // Validate input
        $errors = $this->validateInput([
            'listing_id' => 'required|numeric',
            'body' => 'required|min:1|max:2000',
            'parent_id' => 'numeric'
        ], $data);

        $listingId = (int)$data['listing_id'];
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;

        // Verify listing exists and is published
        if (empty($errors['listing_id'])) {
            $listing = $this->database->queryOne(
                'SELECT id FROM listings WHERE id = ? AND is_published = true',
                [$listingId]
            );
            if (!$listing) {
                $errors['listing_id'] = 'Listing not found or not published';
            }
        }

        // Verify parent comment exists and belongs to same listing
        if ($parentId && empty($errors['parent_id'])) {
            $parentComment = $this->database->queryOne(
                'SELECT id, parent_id FROM listing_comments WHERE id = ? AND listing_id = ? AND is_deleted = false',
                [$parentId, $listingId]
            );
            if (!$parentComment) {
                $errors['parent_id'] = 'Parent comment not found';
            } elseif ($parentComment['parent_id'] !== null) {
                // Prevent nesting beyond 2 levels (parent -> child only)
                $errors['parent_id'] = 'Cannot reply to a reply. Please reply to the main comment.';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', 'Invalid comment data.');
            $this->redirectBack('/listing/' . $listingId);
            return;
        }

        try {
            // Insert comment
            $this->database->insert('listing_comments', [
                'listing_id' => $listingId,
                'user_id' => $userId,
                'parent_id' => $parentId,
                'body' => $data['body']
            ]);

            $this->setFlash('success', 'Comment added successfully.');
            $this->redirect('/listing/' . $listingId . '#comments');

        } catch (Exception $e) {
            error_log('Add comment failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to add comment. Please try again.');
            $this->redirectBack('/listing/' . $listingId);
        }
    }

    public function editComment(array $params): void
    {
        $commentId = (int)$params['id'];
        $userId = $this->session->getUserId();
        $data = $this->sanitizeArray($_POST);

        // Get comment and verify ownership
        $comment = $this->database->queryOne(
            'SELECT * FROM listing_comments WHERE id = ? AND user_id = ? AND is_deleted = false',
            [$commentId, $userId]
        );

        if (!$comment) {
            throw new Exception('Comment not found or access denied', 404);
        }

        // Validate input
        $errors = $this->validateInput([
            'body' => 'required|min:1|max:2000'
        ], $data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Comment cannot be empty and must be less than 2000 characters.');
            $this->redirectBack('/listing/' . $comment['listing_id']);
            return;
        }

        try {
            // Update comment
            $this->database->update('listing_comments', [
                'body' => $data['body']
            ], ['id' => $commentId]);

            $this->setFlash('success', 'Comment updated successfully.');
            $this->redirect('/listing/' . $comment['listing_id'] . '#comment-' . $commentId);

        } catch (Exception $e) {
            error_log('Edit comment failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to update comment. Please try again.');
            $this->redirectBack('/listing/' . $comment['listing_id']);
        }
    }

    public function deleteComment(array $params): void
    {
        $commentId = (int)$params['id'];
        $userId = $this->session->getUserId();

        // Get comment and verify ownership or admin access
        $comment = $this->database->queryOne(
            'SELECT lc.*, l.user_id as listing_owner_id 
             FROM listing_comments lc 
             JOIN listings l ON lc.listing_id = l.id 
             WHERE lc.id = ? AND lc.is_deleted = false',
            [$commentId]
        );

        if (!$comment) {
            throw new Exception('Comment not found', 404);
        }

        $currentUser = $this->getCurrentUser();
        $canDelete = false;

        // Comment owner can delete
        if ($comment['user_id'] == $userId) {
            $canDelete = true;
        }
        // Listing owner can delete comments on their listing
        elseif ($comment['listing_owner_id'] == $userId) {
            $canDelete = true;
        }
        // Admin can delete any comment
        elseif ($currentUser && $currentUser['is_admin']) {
            $canDelete = true;
        }

        if (!$canDelete) {
            throw new Exception('Access denied', 403);
        }

        try {
            // Soft delete the comment
            $this->database->update('listing_comments', [
                'is_deleted' => true,
                'body' => '[Comment deleted]'
            ], ['id' => $commentId]);

            $this->setFlash('success', 'Comment deleted successfully.');
            $this->redirect('/listing/' . $comment['listing_id'] . '#comments');

        } catch (Exception $e) {
            error_log('Delete comment failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to delete comment. Please try again.');
            $this->redirectBack('/listing/' . $comment['listing_id']);
        }
    }

    public function reportComment(array $params): void
    {
        $commentId = (int)$params['id'];
        $userId = $this->session->getUserId();
        $data = $this->sanitizeArray($_POST);

        // Get comment
        $comment = $this->database->queryOne(
            'SELECT * FROM listing_comments WHERE id = ? AND is_deleted = false',
            [$commentId]
        );

        if (!$comment) {
            throw new Exception('Comment not found', 404);
        }

        // Can't report your own comment
        if ($comment['user_id'] == $userId) {
            $this->setFlash('error', 'Cannot report your own comment.');
            $this->redirectBack('/listing/' . $comment['listing_id']);
            return;
        }

        // Validate report
        $errors = $this->validateInput([
            'reason' => 'required',
            'description' => 'max:1000'
        ], $data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Please provide a valid reason for reporting.');
            $this->redirectBack('/listing/' . $comment['listing_id']);
            return;
        }

        try {
            // Create report
            $this->database->insert('reports', [
                'reporter_id' => $userId,
                'reported_user_id' => $comment['user_id'],
                'listing_id' => $comment['listing_id'],
                'reason' => $data['reason'],
                'description' => $data['description'] ?? '',
                'status' => 'pending'
            ]);

            $this->setFlash('success', 'Comment reported successfully. Thank you for helping keep our community safe.');
            $this->redirect('/listing/' . $comment['listing_id'] . '#comments');

        } catch (Exception $e) {
            error_log('Report comment failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to submit report. Please try again.');
            $this->redirectBack('/listing/' . $comment['listing_id']);
        }
    }

    /**
     * Get threaded comments for a listing
     * 
     * @param int $listingId
     * @return array Nested comment structure
     */
    public function getThreadedComments(int $listingId): array
    {
        // Get all comments for the listing
        $comments = $this->database->query(
            'SELECT lc.*, u.handle as user_handle, u.avatar_path 
             FROM listing_comments lc 
             JOIN users u ON lc.user_id = u.id 
             WHERE lc.listing_id = ? AND lc.is_deleted = false 
             ORDER BY lc.created_at ASC',
            [$listingId]
        );

        // Build threaded structure
        $threaded = [];
        $commentMap = [];

        // First pass: create comment map
        foreach ($comments as $comment) {
            $comment['replies'] = [];
            $commentMap[$comment['id']] = $comment;
        }

        // Second pass: build hierarchy
        foreach ($commentMap as $comment) {
            if ($comment['parent_id'] === null) {
                // Top-level comment
                $threaded[] = $comment;
            } else {
                // Reply to another comment
                if (isset($commentMap[$comment['parent_id']])) {
                    $commentMap[$comment['parent_id']]['replies'][] = $comment;
                }
            }
        }

        return $threaded;
    }
}