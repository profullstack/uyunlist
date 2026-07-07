<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;

// Load environment variables
// In Docker the environment is provided by compose; a .env file is optional.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Initialize configuration
$config = new Config();

// Set error reporting based on debug mode
if ($config->get('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Content Security Policy - strict for Tor Browser compatibility
header("Content-Security-Policy: default-src 'self'; script-src 'none'; object-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'none'; connect-src 'none'; media-src 'none'; frame-src 'self'; frame-ancestors 'self';");

try {
    // Initialize database connection
    $database = new Database($config);
    
    // Initialize session management
    $session = new Session($config, $database);
    
    // Initialize router
    $router = new Router();
    
    // Initialize application
    $app = new Application($config, $database, $session, $router);
    
    // Run the application
    $app->run();
    
} catch (\Throwable $e) {
    // Log error (implement proper logging)
    error_log('Application error: ' . $e->getMessage());
    
    // Show generic error page
    http_response_code(500);
    include __DIR__ . '/../templates/errors/500.php';
}