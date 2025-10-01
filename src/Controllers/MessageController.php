<?php

declare(strict_types=1);

namespace App\Controllers;

use Exception;

class MessageController extends BaseController
{
    public function inbox(): void
    {
        $userId = $this->session->getUserId();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Get conversations with latest message and unread count
        $conversations = $this->database->query(
            'SELECT 
                c.*,
                CASE 
                    WHEN c.user_a = ? THEN u_b.handle 
                    ELSE u_a.handle 
                END as other_user_handle,
                CASE 
                    WHEN c.user_a = ? THEN c.user_b 
                    ELSE c.user_a 
                END as other_user_id,
                m.body as last_message,
                m.created_at as last_message_at,
                m.sender_id as last_sender_id,
                CASE 
                    WHEN c.user_a = ? THEN 
                        (SELECT COUNT(*) FROM messages WHERE convo_id = c.id AND is_read_by_a = false AND sender_id != ?)
                    ELSE 
                        (SELECT COUNT(*) FROM messages WHERE convo_id = c.id AND is_read_by_b = false AND sender_id != ?)
                END as unread_count
             FROM conversations c
             JOIN users u_a ON c.user_a = u_a.id
             JOIN users u_b ON c.user_b = u_b.id
             LEFT JOIN LATERAL (
                 SELECT body, created_at, sender_id 
                 FROM messages 
                 WHERE convo_id = c.id 
                 ORDER BY created_at DESC 
                 LIMIT 1
             ) m ON true
             WHERE c.user_a = ? OR c.user_b = ?
             ORDER BY COALESCE(m.created_at, c.created_at) DESC
             LIMIT ? OFFSET ?',
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $perPage, $offset]
        );

        // Get total count for pagination
        $totalCount = $this->database->queryOne(
            'SELECT COUNT(*) as count FROM conversations WHERE user_a = ? OR user_b = ?',
            [$userId, $userId]
        )['count'] ?? 0;

        $pagination = $this->getPagination((int)$totalCount, $perPage, $page);

        $this->render('messages/inbox', [
            'conversations' => $conversations,
            'pagination' => $pagination
        ]);
    }

    public function thread(array $params): void
    {
        $conversationId = (int)$params['id'];
        $userId = $this->session->getUserId();

        // Get conversation and verify access
        $conversation = $this->database->queryOne(
            'SELECT c.*, u_a.handle as user_a_handle, u_b.handle as user_b_handle
             FROM conversations c
             JOIN users u_a ON c.user_a = u_a.id
             JOIN users u_b ON c.user_b = u_b.id
             WHERE c.id = ? AND (c.user_a = ? OR c.user_b = ?)',
            [$conversationId, $userId, $userId]
        );

        if (!$conversation) {
            throw new Exception('Conversation not found', 404);
        }

        // Determine other user
        $otherUserId = $conversation['user_a'] == $userId ? $conversation['user_b'] : $conversation['user_a'];
        $otherUserHandle = $conversation['user_a'] == $userId ? $conversation['user_b_handle'] : $conversation['user_a_handle'];

        // Mark messages as read
        $this->markMessagesAsRead($conversationId, $userId);

        // Get messages with pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $messages = $this->database->query(
            'SELECT m.*, u.handle as sender_handle
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE m.convo_id = ?
             ORDER BY m.created_at ASC
             LIMIT ? OFFSET ?',
            [$conversationId, $perPage, $offset]
        );

        // Get total message count
        $totalMessages = $this->database->queryOne(
            'SELECT COUNT(*) as count FROM messages WHERE convo_id = ?',
            [$conversationId]
        )['count'] ?? 0;

        $pagination = $this->getPagination((int)$totalMessages, $perPage, $page);

        $this->render('messages/thread', [
            'conversation' => $conversation,
            'other_user_id' => $otherUserId,
            'other_user_handle' => $otherUserHandle,
            'messages' => $messages,
            'pagination' => $pagination
        ]);
    }

    public function reply(array $params): void
    {
        $conversationId = (int)$params['id'];
        $userId = $this->session->getUserId();
        $data = $this->sanitizeArray($_POST);

        // Verify conversation access
        $conversation = $this->database->queryOne(
            'SELECT * FROM conversations WHERE id = ? AND (user_a = ? OR user_b = ?)',
            [$conversationId, $userId, $userId]
        );

        if (!$conversation) {
            throw new Exception('Conversation not found', 404);
        }

        // Validate message
        $errors = $this->validateInput([
            'body' => 'required|min:1|max:5000'
        ], $data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Message cannot be empty and must be less than 5000 characters.');
            $this->redirectBack('/message/' . $conversationId);
            return;
        }

        try {
            // Insert message
            $this->database->insert('messages', [
                'convo_id' => $conversationId,
                'sender_id' => $userId,
                'body' => $data['body'],
                'is_read_by_a' => $conversation['user_a'] == $userId,
                'is_read_by_b' => $conversation['user_b'] == $userId
            ]);

            // Update conversation timestamp
            $this->database->update('conversations', [
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $conversationId]);

            $this->setFlash('success', 'Message sent successfully.');
            $this->redirect('/message/' . $conversationId);

        } catch (Exception $e) {
            error_log('Message send failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to send message. Please try again.');
            $this->redirectBack('/message/' . $conversationId);
        }
    }

    public function startConversation(): void
    {
        $userId = $this->session->getUserId();
        $data = $this->sanitizeArray($_POST);

        // Validate input
        $errors = $this->validateInput([
            'other_user_id' => 'required|numeric',
            'message' => 'required|min:1|max:5000'
        ], $data);

        $otherUserId = (int)$data['other_user_id'];

        // Can't message yourself
        if ($otherUserId === $userId) {
            $errors['other_user_id'] = 'Cannot message yourself';
        }

        // Verify other user exists
        if (empty($errors['other_user_id'])) {
            $otherUser = $this->database->queryOne(
                'SELECT id FROM users WHERE id = ?',
                [$otherUserId]
            );
            if (!$otherUser) {
                $errors['other_user_id'] = 'User not found';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', 'Invalid conversation request.');
            $this->redirectBack('/');
            return;
        }

        try {
            $this->database->beginTransaction();

            // Check if conversation already exists
            $existingConversation = $this->database->queryOne(
                'SELECT id FROM conversations 
                 WHERE (user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?)',
                [$userId, $otherUserId, $otherUserId, $userId]
            );

            if ($existingConversation) {
                // Conversation exists, just add the message
                $conversationId = $existingConversation['id'];
            } else {
                // Create new conversation (ensure user_a < user_b for consistency)
                $userA = min($userId, $otherUserId);
                $userB = max($userId, $otherUserId);

                $conversationId = $this->database->insert('conversations', [
                    'user_a' => $userA,
                    'user_b' => $userB
                ]);
            }

            // Add the initial message
            $this->database->insert('messages', [
                'convo_id' => $conversationId,
                'sender_id' => $userId,
                'body' => $data['message'],
                'is_read_by_a' => $userId == ($conversation['user_a'] ?? $userA),
                'is_read_by_b' => $userId == ($conversation['user_b'] ?? $userB)
            ]);

            $this->database->commit();

            $this->setFlash('success', 'Message sent successfully.');
            $this->redirect('/message/' . $conversationId);

        } catch (Exception $e) {
            $this->database->rollback();
            error_log('Start conversation failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to start conversation. Please try again.');
            $this->redirectBack('/');
        }
    }

    private function markMessagesAsRead(int $conversationId, int $userId): void
    {
        try {
            // Determine which read flag to update based on user position in conversation
            $conversation = $this->database->queryOne(
                'SELECT user_a, user_b FROM conversations WHERE id = ?',
                [$conversationId]
            );

            if (!$conversation) {
                return;
            }

            if ($conversation['user_a'] == $userId) {
                // User A is reading, mark messages as read by A
                $this->database->execute(
                    'UPDATE messages SET is_read_by_a = true WHERE convo_id = ? AND sender_id != ?',
                    [$conversationId, $userId]
                );
            } else {
                // User B is reading, mark messages as read by B
                $this->database->execute(
                    'UPDATE messages SET is_read_by_b = true WHERE convo_id = ? AND sender_id != ?',
                    [$conversationId, $userId]
                );
            }
        } catch (Exception $e) {
            error_log('Mark messages as read failed: ' . $e->getMessage());
        }
    }

    public function deleteConversation(array $params): void
    {
        $conversationId = (int)$params['id'];
        $userId = $this->session->getUserId();

        // Verify access
        $conversation = $this->database->queryOne(
            'SELECT * FROM conversations WHERE id = ? AND (user_a = ? OR user_b = ?)',
            [$conversationId, $userId, $userId]
        );

        if (!$conversation) {
            throw new Exception('Conversation not found', 404);
        }

        try {
            // Delete conversation (cascade will handle messages)
            $this->database->delete('conversations', ['id' => $conversationId]);

            $this->setFlash('success', 'Conversation deleted successfully.');
            $this->redirect('/messages');

        } catch (Exception $e) {
            error_log('Delete conversation failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to delete conversation. Please try again.');
            $this->redirectBack('/messages');
        }
    }

    public function reportMessage(array $params): void
    {
        $messageId = (int)$params['messageId'];
        $userId = $this->session->getUserId();

        // Get message and verify access
        $message = $this->database->queryOne(
            'SELECT m.*, c.user_a, c.user_b 
             FROM messages m 
             JOIN conversations c ON m.convo_id = c.id 
             WHERE m.id = ? AND (c.user_a = ? OR c.user_b = ?)',
            [$messageId, $userId, $userId]
        );

        if (!$message) {
            throw new Exception('Message not found', 404);
        }

        // Can't report your own message
        if ($message['sender_id'] == $userId) {
            $this->setFlash('error', 'Cannot report your own message.');
            $this->redirectBack('/messages');
            return;
        }

        $data = $this->sanitizeArray($_POST);

        // Validate report
        $errors = $this->validateInput([
            'reason' => 'required',
            'description' => 'max:1000'
        ], $data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Please provide a valid reason for reporting.');
            $this->redirectBack('/message/' . $message['convo_id']);
            return;
        }

        try {
            // Create report
            $this->database->insert('reports', [
                'reporter_id' => $userId,
                'reported_user_id' => $message['sender_id'],
                'message_id' => $messageId,
                'reason' => $data['reason'],
                'description' => $data['description'] ?? '',
                'status' => 'pending'
            ]);

            $this->setFlash('success', 'Message reported successfully. Thank you for helping keep our community safe.');
            $this->redirect('/message/' . $message['convo_id']);

        } catch (Exception $e) {
            error_log('Report message failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to submit report. Please try again.');
            $this->redirectBack('/message/' . $message['convo_id']);
        }
    }
}