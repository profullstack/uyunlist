<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\ListingController;
use App\Controllers\MessageController;
use App\Controllers\PaymentController;
use App\Controllers\AdminController;
use App\Controllers\MembersController;
use App\Controllers\ImageController;
use App\Controllers\CanaryController;
use Exception;

class Application
{
    private Config $config;
    private Database $database;
    private Session $session;
    private Router $router;

    public function __construct(Config $config, Database $database, Session $session, Router $router)
    {
        $this->config = $config;
        $this->database = $database;
        $this->session = $session;
        $this->router = $router;
        
        $this->setupMiddleware();
        $this->setupRoutes();
    }

    private function setupMiddleware(): void
    {
        // Authentication middleware
        $this->router->addMiddleware('auth', function (array $params = []) {
            if (!$this->session->isLoggedIn()) {
                $this->router->redirect('/login');
                return false;
            }
            return true;
        });

        // Guest middleware (redirect if already logged in)
        $this->router->addMiddleware('guest', function (array $params = []) {
            if ($this->session->isLoggedIn()) {
                $this->router->redirect('/');
                return false;
            }
            return true;
        });

        // Admin middleware
        $this->router->addMiddleware('admin', function (array $params = []) {
            if (!$this->session->isLoggedIn()) {
                $this->router->redirect('/login');
                return false;
            }
            
            $userId = $this->session->getUserId();
            $user = $this->database->queryOne('SELECT is_admin FROM users WHERE id = ?', [$userId]);
            
            if (!$user || !$user['is_admin']) {
                http_response_code(403);
                include __DIR__ . '/../../templates/errors/403.php';
                exit;
            }
            
            return true;
        });

        // CSRF middleware
        $this->router->addMiddleware('csrf', function (array $params = []) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $token = $_POST['csrf_token'] ?? '';
                if (!$this->session->validateCsrfToken($token)) {
                    http_response_code(403);
                    include __DIR__ . '/../../templates/errors/csrf.php';
                    exit;
                }
            }
            return true;
        });
    }

    private function setupRoutes(): void
    {
        // Home and browsing
        $this->router->get('/', [HomeController::class, 'index']);
        $this->router->get('/category/{id}', [HomeController::class, 'categoryById']); // legacy numeric → 301 to slug
        $this->router->get('/search', [HomeController::class, 'search']);
        
        // Warrant Canary
        $this->router->get('/canary', [CanaryController::class, 'show']);

        // Authentication
        $this->router->get('/register', [AuthController::class, 'showRegister'], ['guest']);
        $this->router->post('/register', [AuthController::class, 'register'], ['guest', 'csrf']);
        $this->router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
        $this->router->post('/login', [AuthController::class, 'login'], ['guest', 'csrf']);
        $this->router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

        // User profile
        $this->router->get('/profile', [AuthController::class, 'showProfile'], ['auth']);
        $this->router->get('/members', [MembersController::class, 'index'], ['auth']);
        $this->router->post('/profile', [AuthController::class, 'updateProfile'], ['auth', 'csrf']);

        // Listings
        $this->router->get('/listing/{id}', [ListingController::class, 'show']);
        $this->router->get('/create-listing', [ListingController::class, 'showCreate'], ['auth']);
        $this->router->post('/create-listing', [ListingController::class, 'create'], ['auth', 'csrf']);
        $this->router->get('/edit-listing/{id}', [ListingController::class, 'showEdit'], ['auth']);
        $this->router->post('/edit-listing/{id}', [ListingController::class, 'update'], ['auth', 'csrf']);
        $this->router->post('/delete-listing/{id}', [ListingController::class, 'delete'], ['auth', 'csrf']);
        $this->router->get('/my-listings', [ListingController::class, 'myListings'], ['auth']);

        // Images
        $this->router->get('/image/{id}', [ImageController::class, 'serve']);
        $this->router->get('/avatar/{user_id}', [ImageController::class, 'serveAvatar']);
        $this->router->get('/thumbnail/{id}', [ImageController::class, 'thumbnail']);

        // Messaging
        $this->router->get('/messages', [MessageController::class, 'inbox'], ['auth']);
        $this->router->get('/message/{id}', [MessageController::class, 'thread'], ['auth']);
        $this->router->post('/message/{id}', [MessageController::class, 'reply'], ['auth', 'csrf']);
        $this->router->post('/start-conversation', [MessageController::class, 'startConversation'], ['auth', 'csrf']);

        // Payments
        $this->router->get('/pay/{invoiceId}', [PaymentController::class, 'showPayment'], ['auth']);
        $this->router->get('/pay/{invoiceId}/status', [PaymentController::class, 'paymentStatus'], ['auth']);
        $this->router->get('/pay-to-publish/{listingId}', [PaymentController::class, 'payToPublish'], ['auth']);
        $this->router->get('/bump-listing/{listingId}', [PaymentController::class, 'bumpListing'], ['auth']);
        $this->router->post('/create-invoice', [PaymentController::class, 'createInvoice'], ['auth', 'csrf']);
        $this->router->get('/invoice-status/{invoiceId}', [PaymentController::class, 'invoiceStatus'], ['auth']);
        $this->router->post('/webhook/cryptapi', [PaymentController::class, 'webhook']);
        $this->router->get('/cron/poll-payments', [PaymentController::class, 'pollPayments']);

        // Comments
        $this->router->post('/add-comment', [CommentController::class, 'addComment'], ['auth', 'csrf']);
        $this->router->post('/edit-comment/{id}', [CommentController::class, 'editComment'], ['auth', 'csrf']);
        $this->router->post('/delete-comment/{id}', [CommentController::class, 'deleteComment'], ['auth', 'csrf']);
        $this->router->post('/report-comment/{id}', [CommentController::class, 'reportComment'], ['auth', 'csrf']);

        // Admin
        $this->router->get('/admin', [AdminController::class, 'dashboard'], ['admin']);
        $this->router->get('/admin/users', [AdminController::class, 'users'], ['admin']);
        $this->router->get('/admin/listings', [AdminController::class, 'listings'], ['admin']);
        $this->router->get('/admin/reports', [AdminController::class, 'reports'], ['admin']);
        $this->router->get('/admin/invoices', [AdminController::class, 'invoices'], ['admin']);
        $this->router->post('/admin/moderate', [AdminController::class, 'moderate'], ['admin', 'csrf']);

        // Category browse by slug: /<category> and /<category>/<subcategory>
        // (e.g. /jobs, /jobs/dealer). Registered LAST on purpose — these are
        // greedy one/two-segment patterns and the router is first-match-wins,
        // so every specific route above (/listing/{id}, /login, /pay/... etc.)
        // takes precedence. A slug that doesn't resolve 404s in the handler.
        $this->router->get('/{category}/{subcategory}', [HomeController::class, 'subcategory']);
        $this->router->get('/{slug}', [HomeController::class, 'category']);
    }

    public function run(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = $_SERVER['REQUEST_URI'];

            // Handle route dispatch
            $route = $this->router->dispatch($method, $uri);
            
            // Execute middleware
            if (!$this->router->executeMiddleware($route['middleware'], $route['params'])) {
                return; // Middleware handled the response
            }

            // Execute controller
            $this->executeController($route['handler'], $route['params']);

        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    private function executeController(callable|string|array $handler, array $params): void
    {
        if (is_string($handler)) {
            // Handle "ControllerClass::method" format
            if (strpos($handler, '::') !== false) {
                [$class, $method] = explode('::', $handler, 2);
                $controller = new $class($this->config, $this->database, $this->session, $this->router);
                $handler = [$controller, $method];
            } else {
                throw new Exception("Invalid handler format: {$handler}");
            }
        } elseif (is_array($handler) && count($handler) === 2) {
            // Handle [ControllerClass::class, 'method'] format
            [$class, $method] = $handler;
            $controller = new $class($this->config, $this->database, $this->session, $this->router);
            $handler = [$controller, $method];
        }

        if (!is_callable($handler)) {
            throw new Exception('Handler is not callable');
        }

        call_user_func($handler, $params);
    }

    private function handleError(Exception $e): void
    {
        $code = $e->getCode() ?: 500;
        
        // Log error
        error_log(sprintf(
            'Application error [%d]: %s in %s:%d',
            $code,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        // Set HTTP response code
        http_response_code($code);

        // Show appropriate error page
        $errorTemplate = match ($code) {
            404 => __DIR__ . '/../../templates/errors/404.php',
            403 => __DIR__ . '/../../templates/errors/403.php',
            default => __DIR__ . '/../../templates/errors/500.php'
        };

        if (file_exists($errorTemplate)) {
            include $errorTemplate;
        } else {
            echo "Error {$code}: " . ($this->config->get('APP_DEBUG') ? $e->getMessage() : 'An error occurred');
        }
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }
}