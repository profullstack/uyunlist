<?php

declare(strict_types=1);

namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        // Get categories (top-level + their subcategories) for navigation
        $categories = $this->categoryTree();

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
            'total_categories' => count($categories) // top-level categories
        ];

        $this->render('home/index', [
            'categories' => $categories,
            'recent_listings' => $recentListings,
            'featured_listings' => $featuredListings,
            'stats' => $stats
        ]);
    }

    /**
     * Top-level category page at /<slug> (e.g. /jobs). Lists everything in the
     * category AND its subcategories, and shows the subcategory links.
     */
    public function category(array $params): void
    {
        $slug = (string)($params['slug'] ?? '');

        $category = $this->database->queryOne(
            'SELECT * FROM categories WHERE slug = ? AND parent_id IS NULL AND is_active = true',
            [$slug]
        );

        if (!$category) {
            throw new \Exception('Category not found', 404);
        }

        // Subcategories, with ready-made slug paths for the template.
        $subcategories = $this->database->query(
            'SELECT * FROM categories WHERE parent_id = ? AND is_active = true ORDER BY sort_order, name',
            [$category['id']]
        );
        foreach ($subcategories as &$sub) {
            $sub['path'] = '/' . $category['slug'] . '/' . $sub['slug'];
        }
        unset($sub);

        // Listings in this category OR any of its subcategories.
        $scope = 'l.category_id = ? OR l.category_id IN (SELECT id FROM categories WHERE parent_id = ?)';
        $this->renderListingBrowse(
            $category,
            null,
            $subcategories,
            "AND ({$scope})",
            [$category['id'], $category['id']]
        );
    }

    /**
     * Subcategory page at /<category>/<subcategory> (e.g. /jobs/dealer). Lists
     * only the subcategory's own listings.
     */
    public function subcategory(array $params): void
    {
        $catSlug = (string)($params['category'] ?? '');
        $subSlug = (string)($params['subcategory'] ?? '');

        $parent = $this->database->queryOne(
            'SELECT * FROM categories WHERE slug = ? AND parent_id IS NULL AND is_active = true',
            [$catSlug]
        );
        if (!$parent) {
            throw new \Exception('Category not found', 404);
        }

        $category = $this->database->queryOne(
            'SELECT * FROM categories WHERE slug = ? AND parent_id = ? AND is_active = true',
            [$subSlug, $parent['id']]
        );
        if (!$category) {
            throw new \Exception('Category not found', 404);
        }

        $this->renderListingBrowse(
            $category,
            $parent,
            [],
            'AND l.category_id = ?',
            [$category['id']]
        );
    }

    /**
     * Back-compat for old numeric /category/{id} links — 301 to the canonical
     * slug URL so existing listing back-links keep working.
     */
    public function categoryById(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $category = $this->database->queryOne(
            'SELECT * FROM categories WHERE id = ? AND is_active = true',
            [$id]
        );
        if (!$category) {
            throw new \Exception('Category not found', 404);
        }

        if ($category['parent_id'] !== null) {
            $parent = $this->database->queryOne('SELECT slug FROM categories WHERE id = ?', [$category['parent_id']]);
            $this->redirect('/' . $parent['slug'] . '/' . $category['slug'], 301);
        }
        $this->redirect('/' . $category['slug'], 301);
    }

    /** Shared paginated listing render for a (sub)category page. */
    private function renderListingBrowse(array $category, ?array $parent, array $subcategories, string $whereExtra, array $args): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $totalCount = $this->database->queryOne(
            "SELECT COUNT(*) as count FROM listings l WHERE l.is_published = true {$whereExtra}",
            $args
        )['count'] ?? 0;

        $listings = $this->database->query(
            "SELECT l.*, u.handle as user_handle
             FROM listings l
             JOIN users u ON l.user_id = u.id
             WHERE l.is_published = true {$whereExtra}
             ORDER BY l.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($args, [$perPage, $offset])
        );

        $this->render('home/category', [
            'category' => $category,
            'parent' => $parent,
            'subcategories' => $subcategories,
            'listings' => $listings,
            'pagination' => $this->getPagination((int)$totalCount, $perPage, $page)
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

            // Price filters (USD)
            if ($minPrice !== null) {
                $conditions[] = "l.price_usd_cents >= ?";
                $params[] = (int)round($minPrice * 100);
            }

            if ($maxPrice !== null) {
                $conditions[] = "l.price_usd_cents <= ?";
                $params[] = (int)round($maxPrice * 100);
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