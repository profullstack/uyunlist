<?php

declare(strict_types=1);

namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        // Get categories for navigation
        $categories = $this->database->query(
            'SELECT * FROM categories WHERE is_active = true ORDER BY sort_order, name'
        );

        // Get recent published listings
        $recentListings = $this->database->query(
            'SELECT l.*, c.name as category_name, u.handle as user_handle 
             FROM listings l 
             JOIN categories c ON l.category_id = c.id 
             JOIN users u ON l.user_id = u.id 
             WHERE l.is_published = true 
             ORDER BY l.created_at DESC 
             LIMIT 20'
        );

        // Get featured listings if any
        $featuredListings = $this->database->query(
            'SELECT l.*, c.name as category_name, u.handle as user_handle 
             FROM listings l 
             JOIN categories c ON l.category_id = c.id 
             JOIN users u ON l.user_id = u.id 
             WHERE l.is_published = true AND l.is_featured = true 
             ORDER BY l.created_at DESC 
             LIMIT 5'
        );

        // Get site statistics
        $stats = [
            'total_listings' => $this->database->queryOne('SELECT COUNT(*) as count FROM listings WHERE is_published = true')['count'] ?? 0,
            'total_users' => $this->database->queryOne('SELECT COUNT(*) as count FROM users')['count'] ?? 0,
            'total_categories' => count($categories)
        ];

        $this->render('home/index', [
            'categories' => $categories,
            'recent_listings' => $recentListings,
            'featured_listings' => $featuredListings,
            'stats' => $stats
        ]);
    }

    public function category(array $params): void
    {
        $categoryId = (int)$params['id'];
        
        // Get category info
        $category = $this->database->queryOne(
            'SELECT * FROM categories WHERE id = ? AND is_active = true',
            [$categoryId]
        );

        if (!$category) {
            throw new \Exception('Category not found', 404);
        }

        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Get total count
        $totalCount = $this->database->queryOne(
            'SELECT COUNT(*) as count FROM listings WHERE category_id = ? AND is_published = true',
            [$categoryId]
        )['count'] ?? 0;

        // Get listings for this category
        $listings = $this->database->query(
            'SELECT l.*, u.handle as user_handle 
             FROM listings l 
             JOIN users u ON l.user_id = u.id 
             WHERE l.category_id = ? AND l.is_published = true 
             ORDER BY l.created_at DESC 
             LIMIT ? OFFSET ?',
            [$categoryId, $perPage, $offset]
        );

        // Get pagination info
        $pagination = $this->getPagination((int)$totalCount, $perPage, $page);

        $this->render('home/category', [
            'category' => $category,
            'listings' => $listings,
            'pagination' => $pagination
        ]);
    }

    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        $categoryId = !empty($_GET['category']) ? (int)$_GET['category'] : null;
        $location = trim($_GET['location'] ?? '');
        $minPrice = !empty($_GET['min_price']) ? (int)$_GET['min_price'] : null;
        $maxPrice = !empty($_GET['max_price']) ? (int)$_GET['max_price'] : null;

        // Get categories for filter dropdown
        $categories = $this->database->query(
            'SELECT * FROM categories WHERE is_active = true ORDER BY name'
        );

        $listings = [];
        $totalCount = 0;
        $pagination = null;

        if (!empty($query) || $categoryId || !empty($location) || $minPrice || $maxPrice) {
            // Build search query
            $searchSql = 'SELECT l.*, c.name as category_name, u.handle as user_handle 
                         FROM listings l 
                         JOIN categories c ON l.category_id = c.id 
                         JOIN users u ON l.user_id = u.id 
                         WHERE l.is_published = true';
            
            $countSql = 'SELECT COUNT(*) as count 
                        FROM listings l 
                        WHERE l.is_published = true';

            $params = [];
            $conditions = [];

            // Text search
            if (!empty($query)) {
                $conditions[] = "(l.title ILIKE ? OR l.body ILIKE ?)";
                $searchPattern = '%' . $query . '%';
                $params[] = $searchPattern;
                $params[] = $searchPattern;
            }

            // Category filter
            if ($categoryId) {
                $conditions[] = "l.category_id = ?";
                $params[] = $categoryId;
            }

            // Location filter
            if (!empty($location)) {
                $conditions[] = "l.location ILIKE ?";
                $params[] = '%' . $location . '%';
            }

            // Price filters
            if ($minPrice !== null) {
                $conditions[] = "l.price_sats >= ?";
                $params[] = $minPrice * 100000000; // Convert to satoshis
            }

            if ($maxPrice !== null) {
                $conditions[] = "l.price_sats <= ?";
                $params[] = $maxPrice * 100000000; // Convert to satoshis
            }

            if (!empty($conditions)) {
                $whereClause = ' AND ' . implode(' AND ', $conditions);
                $searchSql .= $whereClause;
                $countSql .= $whereClause;
            }

            // Get total count
            $totalCount = $this->database->queryOne($countSql, $params)['count'] ?? 0;

            // Pagination
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;

            // Add ordering and pagination
            $searchSql .= ' ORDER BY l.created_at DESC LIMIT ? OFFSET ?';
            $params[] = $perPage;
            $params[] = $offset;

            // Execute search
            $listings = $this->database->query($searchSql, $params);
            $pagination = $this->getPagination((int)$totalCount, $perPage, $page);
        }

        $this->render('home/search', [
            'query' => $query,
            'category_id' => $categoryId,
            'location' => $location,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'categories' => $categories,
            'listings' => $listings,
            'total_count' => $totalCount,
            'pagination' => $pagination
        ]);
    }
}