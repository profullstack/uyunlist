<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Exception;

class TatumService
{
    private Config $config;
    private array $rateCache = [];
    private int $cacheLifetime = 120; // 2 minutes

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get exchange rate for a cryptocurrency pair
     * 
     * @param string $fromCurrency Base currency (e.g., 'USD')
     * @param string $toCurrency Target cryptocurrency (e.g., 'BTC')
     * @return float Exchange rate
     * @throws Exception
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $cacheKey = strtoupper($fromCurrency) . '_' . strtoupper($toCurrency);
        
        // Check cache first
        if (isset($this->rateCache[$cacheKey])) {
            $cached = $this->rateCache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheLifetime) {
                return $cached['rate'];
            }
        }

        // Fetch fresh rate: use Tatum if a real API key is configured, else
        // fall back to keyless CoinGecko so rates work out of the box.
        $rate = $this->fetchRate($fromCurrency, $toCurrency);
        
        // Cache the result
        $this->rateCache[$cacheKey] = [
            'rate' => $rate,
            'timestamp' => time()
        ];

        return $rate;
    }

    /**
     * Convert USD amount to cryptocurrency amount
     * 
     * @param float $usdAmount Amount in USD
     * @param string $cryptocurrency Target cryptocurrency
     * @return float Amount in cryptocurrency
     * @throws Exception
     */
    public function convertUsdToCrypto(float $usdAmount, string $cryptocurrency): float
    {
        $rate = $this->getExchangeRate('USD', $cryptocurrency);
        return $usdAmount / $rate;
    }

    /**
     * Convert cryptocurrency amount to USD
     * 
     * @param float $cryptoAmount Amount in cryptocurrency
     * @param string $cryptocurrency Source cryptocurrency
     * @return float Amount in USD
     * @throws Exception
     */
    public function convertCryptoToUsd(float $cryptoAmount, string $cryptocurrency): float
    {
        $rate = $this->getExchangeRate('USD', $cryptocurrency);
        return $cryptoAmount * $rate;
    }

    /**
     * Get current rates for all supported cryptocurrencies
     * 
     * @return array Associative array of currency => rate
     */
    public function getAllRates(): array
    {
        $supportedCurrencies = $this->config->getSupportedCurrencies();
        $rates = [];

        foreach ($supportedCurrencies as $currency) {
            try {
                $rates[$currency] = $this->getExchangeRate('USD', $currency);
            } catch (Exception $e) {
                error_log("Failed to get rate for {$currency}: " . $e->getMessage());
                $rates[$currency] = null;
            }
        }

        return $rates;
    }

    /**
     * Calculate payment amounts for all supported currencies
     * 
     * @param float $usdAmount Amount in USD
     * @return array Associative array of currency => amount
     */
    public function calculatePaymentAmounts(float $usdAmount): array
    {
        $supportedCurrencies = $this->config->getSupportedCurrencies();
        $amounts = [];

        foreach ($supportedCurrencies as $currency) {
            try {
                $amounts[$currency] = [
                    'amount' => $this->convertUsdToCrypto($usdAmount, $currency),
                    'rate' => $this->getExchangeRate('USD', $currency),
                    'usd_amount' => $usdAmount
                ];
            } catch (Exception $e) {
                error_log("Failed to calculate amount for {$currency}: " . $e->getMessage());
                $amounts[$currency] = [
                    'amount' => null,
                    'rate' => null,
                    'usd_amount' => $usdAmount,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $amounts;
    }

    /**
     * Pick a rate provider. Tatum needs a paid API key; when it isn't set (or is
     * still the placeholder), fall back to CoinGecko's keyless public endpoint so
     * the marketplace shows live rates without any configuration.
     */
    private function fetchRate(string $fromCurrency, string $toCurrency): float
    {
        $apiKey = (string)$this->config->get('TATUM_API_KEY');
        $hasTatum = $apiKey !== '' && $apiKey !== 'your-tatum-api-key';

        return $hasTatum
            ? $this->fetchRateFromTatum($fromCurrency, $toCurrency)
            : $this->fetchRateFromCoinGecko($fromCurrency, $toCurrency);
    }

    /**
     * Keyless rate from CoinGecko. Returns USD price of 1 unit of $toCurrency
     * (only USD base is supported here). Fetched server-side.
     */
    private function fetchRateFromCoinGecko(string $fromCurrency, string $toCurrency): float
    {
        if (strtoupper($fromCurrency) !== 'USD') {
            throw new Exception('CoinGecko fallback only supports a USD base');
        }

        $ids = [
            'BTC' => 'bitcoin', 'XMR' => 'monero', 'ETH' => 'ethereum',
            'SOL' => 'solana',  'DOGE' => 'dogecoin',
        ];
        $id = $ids[strtoupper($toCurrency)] ?? null;
        if ($id === null) {
            throw new Exception("Unsupported currency: {$toCurrency}");
        }

        $url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . $id . '&vs_currencies=usd';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'OnionClassifieds/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $error);
        }
        if ($httpCode !== 200) {
            throw new Exception("CoinGecko API error: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        $rate = (float)($data[$id]['usd'] ?? 0);
        if ($rate <= 0) {
            throw new Exception('Invalid exchange rate received from CoinGecko');
        }

        return $rate;
    }

    /**
     * Fetch exchange rate from Tatum API
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float
     * @throws Exception
     */
    private function fetchRateFromTatum(string $fromCurrency, string $toCurrency): float
    {
        $apiUrl = $this->config->get('TATUM_API_URL');
        $apiKey = $this->config->get('TATUM_API_KEY');

        if (empty($apiUrl) || empty($apiKey)) {
            throw new Exception('Tatum API configuration missing');
        }

        // Map currency codes to Tatum format
        $tatumCurrency = $this->mapCurrencyToTatum($toCurrency);
        
        // Build API endpoint
        $endpoint = rtrim($apiUrl, '/') . '/tatum/rate/' . $tatumCurrency;
        
        // Add base currency parameter if not USD
        if (strtoupper($fromCurrency) !== 'USD') {
            $endpoint .= '?basePair=' . strtoupper($fromCurrency);
        }

        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_USERAGENT => 'OnionClassifieds/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("Tatum API error: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Tatum API');
        }

        // Extract rate from response
        if (!isset($data['value'])) {
            throw new Exception('Invalid response format from Tatum API');
        }

        $rate = (float)$data['value'];
        
        if ($rate <= 0) {
            throw new Exception('Invalid exchange rate received');
        }

        return $rate;
    }

    /**
     * Map internal currency codes to Tatum API format
     * 
     * @param string $currency
     * @return string
     */
    private function mapCurrencyToTatum(string $currency): string
    {
        $mapping = [
            'BTC' => 'BTC',
            'XMR' => 'XMR',
            'ETH' => 'ETH',
            'SOL' => 'SOL',
            'DOGE' => 'DOGE'
        ];

        $upperCurrency = strtoupper($currency);
        
        if (!isset($mapping[$upperCurrency])) {
            throw new Exception("Unsupported currency: {$currency}");
        }

        return $mapping[$upperCurrency];
    }

    /**
     * Clear the rate cache
     */
    public function clearCache(): void
    {
        $this->rateCache = [];
    }

    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getCacheStats(): array
    {
        $stats = [
            'total_entries' => count($this->rateCache),
            'entries' => []
        ];

        foreach ($this->rateCache as $key => $data) {
            $age = time() - $data['timestamp'];
            $stats['entries'][$key] = [
                'rate' => $data['rate'],
                'age_seconds' => $age,
                'expires_in' => max(0, $this->cacheLifetime - $age)
            ];
        }

        return $stats;
    }

    /**
     * Test API connectivity
     * 
     * @return array Test results
     */
    public function testConnection(): array
    {
        $results = [
            'api_configured' => false,
            'connection_test' => false,
            'rate_fetch_test' => false,
            'error' => null
        ];

        try {
            // Check configuration
            $apiUrl = $this->config->get('TATUM_API_URL');
            $apiKey = $this->config->get('TATUM_API_KEY');
            
            if (empty($apiUrl) || empty($apiKey)) {
                $results['error'] = 'API URL or API key not configured';
                return $results;
            }
            
            $results['api_configured'] = true;

            // Test basic connectivity
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => rtrim($apiUrl, '/') . '/tatum/rate/BTC',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_HTTPHEADER => [
                    'x-api-key: ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_NOBODY => true // HEAD request
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 405) { // 405 = Method Not Allowed for HEAD
                $results['connection_test'] = true;
            }

            // Test actual rate fetching
            $rate = $this->getExchangeRate('USD', 'BTC');
            if ($rate > 0) {
                $results['rate_fetch_test'] = true;
            }

        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }
}