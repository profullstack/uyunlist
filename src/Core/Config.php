<?php

declare(strict_types=1);

namespace App\Core;

class Config
{
    private array $config = [];

    public function __construct()
    {
        $this->loadEnvironmentVariables();
    }

    private function loadEnvironmentVariables(): void
    {
        // Database
        $this->config['DATABASE_URL'] = $_ENV['DATABASE_URL'] ?? '';
        
        // Application
        $this->config['APP_SECRET'] = $_ENV['APP_SECRET'] ?? '';
        $this->config['APP_BASE_URL'] = $_ENV['APP_BASE_URL'] ?? '';
        $this->config['APP_DEBUG'] = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        // CryptAPI Payout Addresses
        $this->config['PAYOUT_BTC'] = $_ENV['PAYOUT_BTC'] ?? '';
        $this->config['PAYOUT_XMR'] = $_ENV['PAYOUT_XMR'] ?? '';
        $this->config['PAYOUT_ETH'] = $_ENV['PAYOUT_ETH'] ?? '';
        $this->config['PAYOUT_SOL'] = $_ENV['PAYOUT_SOL'] ?? '';
        $this->config['PAYOUT_DOGE'] = $_ENV['PAYOUT_DOGE'] ?? '';
        
        // Payment Confirmations
        $this->config['PAY_CONF_DEFAULT'] = (int)($_ENV['PAY_CONF_DEFAULT'] ?? 1);
        $this->config['PAY_CONF_BTC'] = (int)($_ENV['PAY_CONF_BTC'] ?? 1);
        $this->config['PAY_CONF_XMR'] = (int)($_ENV['PAY_CONF_XMR'] ?? 10);
        $this->config['PAY_CONF_ETH'] = (int)($_ENV['PAY_CONF_ETH'] ?? 12);
        $this->config['PAY_CONF_SOL'] = (int)($_ENV['PAY_CONF_SOL'] ?? 1);
        $this->config['PAY_CONF_DOGE'] = (int)($_ENV['PAY_CONF_DOGE'] ?? 6);
        
        // Tatum API
        $this->config['TATUM_API_URL'] = $_ENV['TATUM_API_URL'] ?? 'https://api.tatum.io/v3';
        $this->config['TATUM_API_KEY'] = $_ENV['TATUM_API_KEY'] ?? '';
        
        // Upload Configuration
        $this->config['UPLOAD_MAX_SIZE'] = (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880); // 5MB
        $this->config['UPLOAD_MAX_FILES'] = (int)($_ENV['UPLOAD_MAX_FILES'] ?? 5);
        $this->config['UPLOAD_ALLOWED_TYPES'] = explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'image/jpeg,image/png,image/webp');
        
        // Pricing
        $this->config['LISTING_PRICE_CENTS'] = (int)($_ENV['LISTING_PRICE_CENTS'] ?? 100);
        $this->config['BUMP_PRICE_CENTS'] = (int)($_ENV['BUMP_PRICE_CENTS'] ?? 50);
        
        // Session
        $this->config['SESSION_LIFETIME'] = (int)($_ENV['SESSION_LIFETIME'] ?? 86400);
        $this->config['CSRF_TOKEN_LIFETIME'] = (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600);
        
        // Rate Limiting
        $this->config['RATE_LIMIT_ENABLED'] = filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->config['RATE_LIMIT_REQUESTS'] = (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $this->config['RATE_LIMIT_WINDOW'] = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 3600);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    public function all(): array
    {
        return $this->config;
    }

    public function getPayoutAddress(string $currency): string
    {
        $key = 'PAYOUT_' . strtoupper($currency);
        return $this->get($key, '');
    }

    public function getConfirmationsRequired(string $currency): int
    {
        $key = 'PAY_CONF_' . strtoupper($currency);
        return $this->get($key, $this->get('PAY_CONF_DEFAULT', 1));
    }

    public function getSupportedCurrencies(): array
    {
        return ['BTC', 'XMR', 'ETH', 'SOL', 'DOGE'];
    }
}