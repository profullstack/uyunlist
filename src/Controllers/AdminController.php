<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Admin panel. All routes are gated by the `admin` middleware, so every action
 * here can assume the current user is an administrator.
 */
class AdminController extends BaseController
{
    public function dashboard(): void
    {
        $one = fn(string $sql): int => (int)($this->database->queryOne($sql)['count'] ?? 0);

        $stats = [
            'users'            => $one('SELECT COUNT(*) AS count FROM users'),
            'admins'           => $one('SELECT COUNT(*) AS count FROM users WHERE is_admin = true'),
            'listings'         => $one('SELECT COUNT(*) AS count FROM listings'),
            'listings_live'    => $one('SELECT COUNT(*) AS count FROM listings WHERE is_published = true'),
            'reports_pending'  => $one("SELECT COUNT(*) AS count FROM reports WHERE status = 'pending'"),
            'reports_total'    => $one('SELECT COUNT(*) AS count FROM reports'),
        ];

        $recentListings = $this->database->query(
            'SELECT l.id, l.title, l.is_published, l.created_at, u.handle AS user_handle
               FROM listings l JOIN users u ON u.id = l.user_id
              ORDER BY l.created_at DESC LIMIT 10'
        );

        $recentReports = $this->database->query(
            "SELECT r.id, r.reason, r.status, r.created_at, u.handle AS reporter
               FROM reports r LEFT JOIN users u ON u.id = r.reporter_id
              ORDER BY (r.status = 'pending') DESC, r.created_at DESC LIMIT 10"
        );

        $this->render('admin/dashboard', [
            'title'           => 'Admin',
            'stats'           => $stats,
            'recent_listings' => $recentListings,
            'recent_reports'  => $recentReports,
        ]);
    }

    public function users(): void
    {
        $users = $this->database->query(
            'SELECT u.id, u.handle, u.is_admin, u.created_at,
                    (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id) AS listing_count
               FROM users u ORDER BY u.created_at DESC'
        );

        $this->render('admin/users', [
            'title' => 'Admin · Users',
            'users' => $users,
        ]);
    }

    public function listings(): void
    {
        $listings = $this->database->query(
            'SELECT l.id, l.title, l.is_published, l.is_featured, l.created_at,
                    u.handle AS user_handle, c.name AS category_name
               FROM listings l
               JOIN users u ON u.id = l.user_id
               LEFT JOIN categories c ON c.id = l.category_id
              ORDER BY l.created_at DESC LIMIT 200'
        );

        $this->render('admin/listings', [
            'title'    => 'Admin · Listings',
            'listings' => $listings,
        ]);
    }

    public function reports(): void
    {
        $reports = $this->database->query(
            "SELECT r.*, rep.handle AS reporter_handle, tgt.handle AS reported_handle,
                    l.title AS listing_title
               FROM reports r
               LEFT JOIN users rep ON rep.id = r.reporter_id
               LEFT JOIN users tgt ON tgt.id = r.reported_user_id
               LEFT JOIN listings l ON l.id = r.listing_id
              ORDER BY (r.status = 'pending') DESC, r.created_at DESC LIMIT 200"
        );

        $this->render('admin/reports', [
            'title'   => 'Admin · Reports',
            'reports' => $reports,
        ]);
    }

    /**
     * Single POST endpoint for all moderation actions. Expects `action` plus an
     * `id`, and redirects back to the page it came from with a flash message.
     */
    public function moderate(): void
    {
        $action = (string)($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);
        $back   = (string)($_POST['redirect'] ?? '/admin');

        if ($id <= 0) {
            $this->setFlash('error', 'Missing target id.');
            $this->redirect($back);
            return;
        }

        $adminId = $this->session->getUserId();

        switch ($action) {
            case 'listing_publish':
                $this->database->update('listings', ['is_published' => true], ['id' => $id]);
                $this->setFlash('success', 'Listing published.');
                break;
            case 'listing_unpublish':
                $this->database->update('listings', ['is_published' => false], ['id' => $id]);
                $this->setFlash('success', 'Listing unpublished.');
                break;
            case 'listing_feature':
                $this->database->update('listings', ['is_featured' => true], ['id' => $id]);
                $this->setFlash('success', 'Listing featured.');
                break;
            case 'listing_unfeature':
                $this->database->update('listings', ['is_featured' => false], ['id' => $id]);
                $this->setFlash('success', 'Listing unfeatured.');
                break;
            case 'listing_delete':
                $this->database->delete('listings', ['id' => $id]);
                $this->setFlash('success', 'Listing deleted.');
                break;
            case 'user_promote':
                $this->database->update('users', ['is_admin' => true], ['id' => $id]);
                $this->setFlash('success', 'User promoted to admin.');
                break;
            case 'user_demote':
                if ($id === $adminId) {
                    $this->setFlash('error', "You can't remove your own admin access.");
                    break;
                }
                $this->database->update('users', ['is_admin' => false], ['id' => $id]);
                $this->setFlash('success', 'Admin access removed.');
                break;
            case 'report_resolve':
            case 'report_dismiss':
                $status = $action === 'report_resolve' ? 'resolved' : 'dismissed';
                // NOW() must be raw SQL, not a bound param (which would bind the
                // literal string "NOW()").
                $this->database->execute(
                    'UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
                    [$status, $adminId, $id]
                );
                $this->setFlash('success', "Report {$status}.");
                break;
            default:
                $this->setFlash('error', 'Unknown moderation action.');
        }

        $this->redirect($back);
    }
}
