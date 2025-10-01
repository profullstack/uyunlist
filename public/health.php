<?php

declare(strict_types=1);

/**
 * Health Check Endpoint for Docker
 * 
 * This endpoint is used by Docker's health check to verify the application is running properly.
 */

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'memory_usage' => memory_get_usage(true),
    'checks' => []
];

// Check if required extensions are loaded
$requiredExtensions = ['pdo', 'pdo_pgsql', 'json', 'curl', 'gd'];
foreach ($requiredExtensions as $ext) {
    $health['checks']['extension_' . $ext] = extension_loaded($ext) ? 'ok' : 'fail';
}

// Check if vendor directory exists (Composer dependencies)
$health['checks']['composer_dependencies'] = file_exists(__DIR__ . '/../vendor/autoload.php') ? 'ok' : 'fail';

// Check if uploads directory is writable
$uploadsDir = __DIR__ . '/../uploads';
$health['checks']['uploads_writable'] = (is_dir($uploadsDir) && is_writable($uploadsDir)) ? 'ok' : 'fail';

// Check database connection if environment is configured
if (isset($_ENV['DATABASE_URL']) && !empty($_ENV['DATABASE_URL'])) {
    try {
        $parsedUrl = parse_url($_ENV['DATABASE_URL']);
        
        if ($parsedUrl !== false) {
            $host = $parsedUrl['host'] ?? '';
            $port = $parsedUrl['port'] ?? 5432;
            $database = ltrim($parsedUrl['path'] ?? '', '/');
            $username = $parsedUrl['user'] ?? '';
            $password = $parsedUrl['pass'] ?? '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode=require";
            
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5, // 5 second timeout for health check
            ]);
            
            // Simple query to test connection
            $pdo->query('SELECT 1');
            $health['checks']['database'] = 'ok';
        } else {
            $health['checks']['database'] = 'fail - invalid DATABASE_URL';
        }
    } catch (Exception $e) {
        $health['checks']['database'] = 'fail - ' . $e->getMessage();
    }
} else {
    $health['checks']['database'] = 'skip - no DATABASE_URL configured';
}

// Determine overall status
$overallStatus = 'ok';
foreach ($health['checks'] as $check => $status) {
    if (str_starts_with($status, 'fail')) {
        $overallStatus = 'fail';
        break;
    }
}

$health['status'] = $overallStatus;

// Set appropriate HTTP status code
if ($overallStatus === 'fail') {
    http_response_code(503); // Service Unavailable
} else {
    http_response_code(200); // OK
}

echo json_encode($health, JSON_PRETTY_PRINT);