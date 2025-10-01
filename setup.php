<?php

declare(strict_types=1);

/**
 * Onion Classifieds Setup Script
 * 
 * This script helps with initial setup and deployment.
 * Run this once after deploying to create necessary directories and check configuration.
 */

echo "🧅 Onion Classifieds Setup Script\n";
echo "==================================\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    echo "❌ Error: PHP 8.2 or higher is required. Current version: " . PHP_VERSION . "\n";
    exit(1);
}
echo "✅ PHP version: " . PHP_VERSION . "\n";

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_pgsql', 'json', 'curl', 'gd', 'imagick'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "❌ Error: Missing required PHP extensions: " . implode(', ', $missingExtensions) . "\n";
    exit(1);
}
echo "✅ All required PHP extensions are loaded\n";

// Check if .env file exists
if (!file_exists('.env')) {
    echo "⚠️  Warning: .env file not found. Please copy .env.example to .env and configure it.\n";
    
    if (file_exists('.env.example')) {
        echo "   You can run: cp .env.example .env\n";
    }
} else {
    echo "✅ .env file found\n";
}

// Create necessary directories
$directories = [
    'uploads',
    'uploads/avatars',
    'uploads/listings',
    'logs',
    'cache'
];

echo "\nCreating directories...\n";
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created directory: $dir\n";
        } else {
            echo "❌ Failed to create directory: $dir\n";
        }
    } else {
        echo "✅ Directory exists: $dir\n";
    }
}

// Set proper permissions
echo "\nSetting permissions...\n";
$writableDirectories = ['uploads', 'logs', 'cache'];

foreach ($writableDirectories as $dir) {
    if (is_dir($dir)) {
        if (chmod($dir, 0755)) {
            echo "✅ Set permissions for: $dir\n";
        } else {
            echo "⚠️  Warning: Could not set permissions for: $dir\n";
        }
    }
}

// Check Composer dependencies
if (!file_exists('vendor/autoload.php')) {
    echo "\n❌ Error: Composer dependencies not installed.\n";
    echo "   Please run: composer install\n";
    exit(1);
}
echo "✅ Composer dependencies installed\n";

// Test database connection if .env exists
if (file_exists('.env')) {
    echo "\nTesting database connection...\n";
    
    try {
        // Load environment variables
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        
        $databaseUrl = $_ENV['DATABASE_URL'] ?? '';
        
        if (empty($databaseUrl)) {
            echo "⚠️  Warning: DATABASE_URL not set in .env file\n";
        } else {
            $parsedUrl = parse_url($databaseUrl);
            
            if ($parsedUrl === false) {
                echo "❌ Error: Invalid DATABASE_URL format\n";
            } else {
                $host = $parsedUrl['host'] ?? '';
                $port = $parsedUrl['port'] ?? 5432;
                $database = ltrim($parsedUrl['path'] ?? '', '/');
                $username = $parsedUrl['user'] ?? '';
                $password = $parsedUrl['pass'] ?? '';

                $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode=require";
                
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                
                // Test query
                $result = $pdo->query('SELECT version()')->fetch();
                echo "✅ Database connection successful\n";
                echo "   PostgreSQL version: " . $result['version'] . "\n";
                
                // Check if tables exist
                $tables = $pdo->query("
                    SELECT table_name 
                    FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_type = 'BASE TABLE'
                ")->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($tables)) {
                    echo "⚠️  Warning: No tables found. Please run the database migration:\n";
                    echo "   Execute the SQL in database/migrations/001_initial_schema.sql\n";
                } else {
                    echo "✅ Database tables found: " . count($tables) . " tables\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    }
}

// Generate APP_SECRET if not set
if (file_exists('.env')) {
    $envContent = file_get_contents('.env');
    if (strpos($envContent, 'APP_SECRET=your-secret-key-here') !== false || 
        strpos($envContent, 'APP_SECRET=""') !== false ||
        strpos($envContent, 'APP_SECRET=') === false) {
        
        echo "\nGenerating APP_SECRET...\n";
        $secret = bin2hex(random_bytes(32));
        $envContent = preg_replace('/APP_SECRET=.*/', "APP_SECRET={$secret}", $envContent);
        
        if (file_put_contents('.env', $envContent)) {
            echo "✅ Generated new APP_SECRET\n";
        } else {
            echo "❌ Failed to update .env file with APP_SECRET\n";
        }
    } else {
        echo "✅ APP_SECRET is configured\n";
    }
}

// Security recommendations
echo "\n🔒 Security Recommendations:\n";
echo "- Ensure this site is only accessible via Tor\n";
echo "- Configure your web server to disable access logs or anonymize IPs\n";
echo "- Set up regular database backups\n";
echo "- Monitor for suspicious activity\n";
echo "- Keep PHP and dependencies updated\n";
echo "- Use strong, unique passwords for all accounts\n";

// Final status
echo "\n🎉 Setup completed!\n";
echo "\nNext steps:\n";
echo "1. Configure your .env file with proper values\n";
echo "2. Run the database migration (001_initial_schema.sql)\n";
echo "3. Set up your web server to point to the 'public' directory\n";
echo "4. Configure Tor hidden service\n";
echo "5. Test the application\n";

echo "\nFor production deployment:\n";
echo "- Set APP_DEBUG=false in .env\n";
echo "- Configure proper error logging\n";
echo "- Set up monitoring and backups\n";
echo "- Review security headers in public/.htaccess\n";

echo "\n🧅 Welcome to Onion Classifieds!\n";