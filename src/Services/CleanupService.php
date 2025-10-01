<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Exception;

class CleanupService
{
    private Config $config;
    private Database $database;

    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
    }

    /**
     * Clean up old listings (90 days)
     * 
     * @return array Cleanup results
     */
    public function cleanupOldListings(): array
    {
        $results = [
            'listings_deleted' => 0,
            'images_deleted' => 0,
            'files_removed' => 0,
            'errors' => []
        ];

        try {
            $this->database->beginTransaction();

            // Get listings older than 90 days
            $oldListings = $this->database->query(
                'SELECT l.id, li.path 
                 FROM listings l 
                 LEFT JOIN listing_images li ON l.id = li.listing_id 
                 WHERE l.created_at < NOW() - INTERVAL \'90 days\''
            );

            if (empty($oldListings)) {
                $this->database->commit();
                return $results;
            }

            // Group by listing ID and collect image paths
            $listingsToDelete = [];
            $imagePaths = [];

            foreach ($oldListings as $row) {
                $listingsToDelete[$row['id']] = true;
                if (!empty($row['path'])) {
                    $imagePaths[] = $row['path'];
                }
            }

            $listingIds = array_keys($listingsToDelete);

            // Delete listings (cascade will handle images, messages, etc.)
            $deletedCount = $this->database->execute(
                'DELETE FROM listings WHERE id = ANY(?)',
                [$listingIds]
            );

            $results['listings_deleted'] = $deletedCount;
            $results['images_deleted'] = count($imagePaths);

            // Delete image files from filesystem
            foreach ($imagePaths as $imagePath) {
                $fullPath = __DIR__ . '/../../' . $imagePath;
                if (file_exists($fullPath)) {
                    if (unlink($fullPath)) {
                        $results['files_removed']++;
                    } else {
                        $results['errors'][] = "Failed to delete file: {$imagePath}";
                    }
                }
            }

            $this->database->commit();

            // Log cleanup activity
            error_log(sprintf(
                'Cleanup completed: %d listings deleted, %d images deleted, %d files removed',
                $results['listings_deleted'],
                $results['images_deleted'],
                $results['files_removed']
            ));

        } catch (Exception $e) {
            $this->database->rollback();
            $results['errors'][] = $e->getMessage();
            error_log('Cleanup failed: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Clean up expired sessions
     * 
     * @return int Number of sessions cleaned up
     */
    public function cleanupExpiredSessions(): int
    {
        try {
            $sessionLifetime = $this->config->get('SESSION_LIFETIME', 86400);
            return $this->database->execute(
                'DELETE FROM sessions WHERE last_seen_at < NOW() - INTERVAL ? SECOND',
                [$sessionLifetime]
            );
        } catch (Exception $e) {
            error_log('Session cleanup failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up expired invoices
     * 
     * @return int Number of invoices cleaned up
     */
    public function cleanupExpiredInvoices(): int
    {
        try {
            return $this->database->execute(
                "UPDATE invoices SET status = 'expired' WHERE status IN ('new', 'processing') AND expires_at < NOW()"
            );
        } catch (Exception $e) {
            error_log('Invoice cleanup failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up old conversation messages (keep only last 1000 per conversation)
     * 
     * @return int Number of messages cleaned up
     */
    public function cleanupOldMessages(): int
    {
        try {
            // Delete old messages, keeping only the latest 1000 per conversation
            return $this->database->execute(
                'DELETE FROM messages 
                 WHERE id NOT IN (
                     SELECT id FROM (
                         SELECT id, ROW_NUMBER() OVER (PARTITION BY convo_id ORDER BY created_at DESC) as rn
                         FROM messages
                     ) ranked 
                     WHERE rn <= 1000
                 )'
            );
        } catch (Exception $e) {
            error_log('Message cleanup failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up orphaned image files
     * 
     * @return array Cleanup results
     */
    public function cleanupOrphanedImages(): array
    {
        $results = [
            'files_scanned' => 0,
            'orphaned_files' => 0,
            'files_removed' => 0,
            'errors' => []
        ];

        try {
            $uploadDirs = [
                __DIR__ . '/../../uploads/listings/',
                __DIR__ . '/../../uploads/avatars/',
                __DIR__ . '/../../uploads/thumbnails/'
            ];

            foreach ($uploadDirs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }

                $files = glob($dir . '*');
                $results['files_scanned'] += count($files);

                foreach ($files as $file) {
                    if (!is_file($file)) {
                        continue;
                    }

                    $filename = basename($file);
                    $relativePath = str_replace(__DIR__ . '/../../', '', $file);

                    // Check if file is referenced in database
                    $isReferenced = false;

                    // Check listing images
                    if (str_contains($dir, 'listings')) {
                        $referenced = $this->database->queryOne(
                            'SELECT id FROM listing_images WHERE path = ?',
                            [$relativePath]
                        );
                        $isReferenced = $referenced !== null;
                    }

                    // Check avatars
                    if (str_contains($dir, 'avatars')) {
                        $referenced = $this->database->queryOne(
                            'SELECT id FROM users WHERE avatar_path = ?',
                            [$relativePath]
                        );
                        $isReferenced = $referenced !== null;
                    }

                    // Check thumbnails (always safe to delete, they regenerate)
                    if (str_contains($dir, 'thumbnails')) {
                        $isReferenced = false;
                    }

                    if (!$isReferenced) {
                        $results['orphaned_files']++;
                        
                        if (unlink($file)) {
                            $results['files_removed']++;
                        } else {
                            $results['errors'][] = "Failed to delete orphaned file: {$relativePath}";
                        }
                    }
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            error_log('Orphaned image cleanup failed: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Run all cleanup tasks
     * 
     * @return array Combined results
     */
    public function runAllCleanup(): array
    {
        $results = [
            'timestamp' => date('c'),
            'listings' => $this->cleanupOldListings(),
            'sessions' => $this->cleanupExpiredSessions(),
            'invoices' => $this->cleanupExpiredInvoices(),
            'messages' => $this->cleanupOldMessages(),
            'orphaned_images' => $this->cleanupOrphanedImages()
        ];

        // Log summary
        error_log('Full cleanup completed: ' . json_encode([
            'listings_deleted' => $results['listings']['listings_deleted'],
            'sessions_cleaned' => $results['sessions'],
            'invoices_expired' => $results['invoices'],
            'messages_cleaned' => $results['messages'],
            'orphaned_files_removed' => $results['orphaned_images']['files_removed']
        ]));

        return $results;
    }

    /**
     * Get cleanup statistics without performing cleanup
     * 
     * @return array Statistics
     */
    public function getCleanupStats(): array
    {
        try {
            return [
                'old_listings_count' => $this->database->queryOne(
                    'SELECT COUNT(*) as count FROM listings WHERE created_at < NOW() - INTERVAL \'90 days\''
                )['count'] ?? 0,
                'expired_sessions_count' => $this->database->queryOne(
                    'SELECT COUNT(*) as count FROM sessions WHERE last_seen_at < NOW() - INTERVAL ? SECOND',
                    [$this->config->get('SESSION_LIFETIME', 86400)]
                )['count'] ?? 0,
                'expired_invoices_count' => $this->database->queryOne(
                    "SELECT COUNT(*) as count FROM invoices WHERE status IN ('new', 'processing') AND expires_at < NOW()"
                )['count'] ?? 0,
                'total_conversations' => $this->database->queryOne(
                    'SELECT COUNT(*) as count FROM conversations'
                )['count'] ?? 0,
                'total_messages' => $this->database->queryOne(
                    'SELECT COUNT(*) as count FROM messages'
                )['count'] ?? 0
            ];
        } catch (Exception $e) {
            error_log('Get cleanup stats failed: ' . $e->getMessage());
            return [];
        }
    }
}