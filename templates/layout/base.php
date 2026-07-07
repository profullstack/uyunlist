<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title) . ' - ' : '' ?>Onion Classifieds</title>
    <style>
        /* Minimal CSS for Tor Browser compatibility - no external resources */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        header {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }
        
        nav {
            margin-top: 10px;
        }
        
        nav a {
            color: #333;
            text-decoration: none;
            margin-right: 20px;
            padding: 5px 10px;
            border: 1px solid transparent;
        }
        
        nav a:hover {
            border-color: #333;
        }
        
        .user-info {
            float: right;
            margin-top: -30px;
        }
        
        .flash-messages {
            margin-bottom: 20px;
        }
        
        .flash {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        
        .flash.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .flash.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .flash.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .flash.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
        }
        
        textarea {
            height: 100px;
            resize: vertical;
        }
        
        button,
        input[type="submit"] {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
        }
        
        button:hover,
        input[type="submit"]:hover {
            background-color: #555;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        .error {
            color: #721c24;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .listing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .listing-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background: white;
        }
        
        .listing-card h3 {
            margin-bottom: 10px;
        }
        
        .listing-card .price {
            font-weight: bold;
            color: #28a745;
        }
        
        .listing-card .location {
            color: #666;
            font-size: 12px;
        }
        
        footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="clearfix">
            <a href="/" class="logo">🧅 Onion Classifieds</a>
            
            <div class="user-info">
                <?php if ($current_user): ?>
                    Welcome, <?= htmlspecialchars($current_user['handle']) ?>
                    <a href="/profile">Profile</a>
                    <form method="post" action="/logout" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <button type="submit" style="background: none; border: none; color: #333; text-decoration: underline; cursor: pointer;">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="/login">Login</a>
                    <a href="/register">Register</a>
                <?php endif; ?>
            </div>
            
            <nav>
                <a href="/">Home</a>
                <a href="/search">Search</a>
                <?php if ($current_user): ?>
                    <a href="/create-listing">Post Listing</a>
                    <a href="/my-listings">My Listings</a>
                    <a href="/messages">Messages</a>
                    <a href="/members">Members</a>
                <?php endif; ?>
                <?php if ($current_user && $current_user['is_admin']): ?>
                    <a href="/admin">Admin</a>
                <?php endif; ?>
            </nav>
        </header>

        <?php if (!empty($flash_messages)): ?>
            <div class="flash-messages">
                <?php foreach ($flash_messages as $type => $message): ?>
                    <div class="flash <?= htmlspecialchars($type) ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <main>
            <?php if (isset($content)) echo $content; ?>
        </main>

        <footer>
            <p>Onion Classifieds - Privacy-First Marketplace</p>
            <p>Accessible only via Tor for your privacy and security</p>
        </footer>
    </div>
</body>
</html>