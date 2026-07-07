<?php

declare(strict_types=1);

namespace App\Core;

use Exception;

class Session
{
    private Config $config;
    private Database $database;
    private ?array $currentSession = null;

    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
        $this->configureSession();
    }

    private function configureSession(): void
    {
        // Configure session for security. Tor .onion services are served over
        // HTTP (Tor provides the transport encryption), so a Secure cookie
        // would never be sent and would silently break sessions/login. Only
        // require Secure when the site is actually served over HTTPS.
        $secure = str_starts_with((string)$this->config->get('APP_BASE_URL'), 'https://');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_lifetime', (string)$this->config->get('SESSION_LIFETIME', 86400));
        
        // Use custom session name
        session_name('ONION_SESS');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login(int $userId): bool
    {
        if (!$this->database) {
            throw new Exception('Database connection required for session management');
        }

        try {
            // Generate new session ID for security
            session_regenerate_id(true);
            
            // Create session record in database
            $sessionId = session_id();
            $csrfToken = bin2hex(random_bytes(32));
            
            $this->database->execute(
                'INSERT INTO sessions (id, user_id, csrf_token, created_at, last_seen_at) 
                 VALUES (?, ?, ?, NOW(), NOW()) 
                 ON CONFLICT (id) DO UPDATE SET 
                 user_id = EXCLUDED.user_id, 
                 csrf_token = EXCLUDED.csrf_token, 
                 last_seen_at = NOW()',
                [$sessionId, $userId, $csrfToken]
            );
            
            // Store in PHP session
            $_SESSION['user_id'] = $userId;
            $_SESSION['csrf_token'] = $csrfToken;
            $_SESSION['login_time'] = time();
            
            $this->currentSession = [
                'id' => $sessionId,
                'user_id' => $userId,
                'csrf_token' => $csrfToken
            ];
            
            return true;
        } catch (Exception $e) {
            error_log('Session login failed: ' . $e->getMessage());
            return false;
        }
    }

    public function logout(): bool
    {
        try {
            $sessionId = session_id();
            
            // Remove from database
            if ($this->database && $sessionId) {
                $this->database->delete('sessions', ['id' => $sessionId]);
            }
            
            // Clear PHP session
            $_SESSION = [];
            session_destroy();
            
            // Clear session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            $this->currentSession = null;
            return true;
        } catch (Exception $e) {
            error_log('Session logout failed: ' . $e->getMessage());
            return false;
        }
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && $this->validateSession();
    }

    public function getUserId(): ?int
    {
        return $this->isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }

    public function getCsrfToken(): string
    {
        // CSRF protection must work for anonymous visitors too — the register
        // and login forms are submitted by guests. Lazily mint a token bound to
        // the PHP session (anonymous or authenticated) and reuse it. login()
        // rotates this on privilege change.
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(string $token): bool
    {
        // Validate against the per-session token regardless of login state, so
        // guest POSTs (register/login) aren't rejected outright. Reject empties
        // so a session with no issued token can't be satisfied by a blank field.
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if ($sessionToken === '' || $token === '') {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    public function regenerateCsrfToken(): string
    {
        if (!$this->isLoggedIn()) {
            throw new Exception('No active session to regenerate CSRF token');
        }
        
        $newToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $newToken;
        
        // Update in database
        if ($this->database) {
            $this->database->update(
                'sessions',
                ['csrf_token' => $newToken, 'last_seen_at' => 'NOW()'],
                ['id' => session_id()]
            );
        }
        
        return $newToken;
    }

    private function validateSession(): bool
    {
        if (!$this->database) {
            return true; // Skip database validation if no database connection
        }
        
        try {
            $sessionId = session_id();
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$sessionId || !$userId) {
                return false;
            }
            
            // Check session in database
            $session = $this->database->queryOne(
                'SELECT s.*, u.id as user_exists 
                 FROM sessions s 
                 LEFT JOIN users u ON s.user_id = u.id 
                 WHERE s.id = ? AND s.user_id = ?',
                [$sessionId, $userId]
            );
            
            if (!$session || !$session['user_exists']) {
                return false;
            }
            
            // Check session expiry
            $lastSeen = strtotime($session['last_seen_at']);
            $sessionLifetime = $this->config->get('SESSION_LIFETIME', 86400);
            
            if (time() - $lastSeen > $sessionLifetime) {
                $this->logout();
                return false;
            }
            
            // Update last seen time
            $this->database->update(
                'sessions',
                ['last_seen_at' => 'NOW()'],
                ['id' => $sessionId]
            );
            
            $this->currentSession = $session;
            return true;
            
        } catch (Exception $e) {
            error_log('Session validation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function cleanupExpiredSessions(): int
    {
        if (!$this->database) {
            return 0;
        }
        
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

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }
}