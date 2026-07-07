<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Exception;

/**
 * Converts USD listing prices to crypto server-side and keeps them fresh.
 *
 * The crypto amount for a listing is computed at save time and cached on the
 * row; a cron (/cron/refresh-prices, hourly) recomputes it for ACTIVE
 * (published) listings so displayed amounts track the market.
 */
class PricingService
{
    private const COINS = ['BTC', 'XMR', 'ETH', 'SOL', 'DOGE'];

    private Config $config;
    private Database $database;
    private TatumService $rates;

    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
        $this->rates = new TatumService($config);
    }

    public static function supportedCoins(): array
    {
        return self::COINS;
    }

    public static function isCoin(string $c): bool
    {
        return in_array(strtoupper($c), self::COINS, true);
    }

    /** Convert a USD amount to crypto for $coin (0.0 if it can't be priced). */
    public function convert(float $usd, string $coin): float
    {
        if ($usd <= 0 || !self::isCoin($coin)) {
            return 0.0;
        }
        try {
            return $this->rates->convertUsdToCrypto($usd, strtoupper($coin));
        } catch (Exception $e) {
            error_log('PricingService convert failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Recompute the cached crypto amount for every published, priced listing.
     * Returns ['updated' => n]. Rates are fetched once per coin (cached).
     */
    public function refreshActive(): array
    {
        $rows = $this->database->query(
            "SELECT id, price_usd_cents, price_currency
               FROM listings
              WHERE is_published = true AND price_usd_cents > 0 AND price_currency <> ''"
        );

        $updated = 0;
        foreach ($rows as $r) {
            $crypto = $this->convert(((int)$r['price_usd_cents']) / 100, (string)$r['price_currency']);
            if ($crypto <= 0) {
                continue;
            }
            $this->database->execute(
                'UPDATE listings SET price_crypto = ?, price_rate_updated_at = NOW() WHERE id = ?',
                [$crypto, (int)$r['id']]
            );
            $updated++;
        }
        return ['updated' => $updated];
    }
}
