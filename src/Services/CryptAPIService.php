<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Exception;

class CryptAPIService
{
    private Config $config;
    private Database $database;

    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
    }

    /**
     * Create a payment invoice using CryptAPI
     * 
     * @param string $currency Cryptocurrency (BTC, XMR, ETH, SOL, DOGE)
     * @param float $amount Amount in cryptocurrency
     * @param string $purpose Purpose of payment (e.g., 'post_listing:123')
     * @param int $userId User ID making the payment
     * @param float $fiatAmount Original amount in fiat currency
     * @param float $cryptoRate Exchange rate used
     * @return array Invoice data
     * @throws Exception
     */
    public function createInvoice(
        string $currency,
        float $amount,
        string $purpose,
        int $userId,
        float $fiatAmount,
        float $cryptoRate
    ): array {
        $currency = strtoupper($currency);
        
        // Validate currency
        if (!in_array($currency, $this->config->getSupportedCurrencies())) {
            throw new Exception("Unsupported currency: {$currency}");
        }

        // Get payout address for this currency (falls back to the operator's
        // imported wallet when PAYOUT_* env is unset/placeholder).
        $payoutAddress = $this->resolvePayoutAddress($currency);
        if ($payoutAddress === '') {
            throw new Exception("No {$currency} payout address configured. Set your {$currency} wallet on your admin profile (or PAYOUT_{$currency}).");
        }

        // Get confirmations required
        $confirmations = $this->config->getConfirmationsRequired($currency);

        // Build callback URL
        $baseUrl = $this->config->get('APP_BASE_URL');
        $callbackUrl = rtrim($baseUrl, '/') . '/webhook/cryptapi';

        // Create CryptAPI request
        $apiResponse = $this->callCryptAPI($currency, [
            'callback' => $callbackUrl,
            'address' => $payoutAddress,
            'confirmations' => $confirmations,
            'post' => 1,
            'json' => 1
        ]);

        if (!$apiResponse['success']) {
            throw new Exception('CryptAPI error: ' . $apiResponse['error']);
        }

        // Store invoice in database
        $invoiceId = $this->database->insert('invoices', [
            'user_id' => $userId,
            'purpose' => $purpose,
            'status' => 'new',
            'fiat_currency' => 'USD',
            'fiat_amount' => $fiatAmount,
            'currency' => $currency,
            'crypto_rate' => $cryptoRate,
            'crypto_amount' => $amount,
            'address_in' => $apiResponse['data']['address_in'],
            'confirmations_required' => $confirmations,
            'confirmations_received' => 0,
            'is_pending_notified' => false,
            'expires_at' => date('Y-m-d H:i:s', time() + 86400) // 24 hours
        ]);

        return [
            'invoice_id' => $invoiceId,
            'address_in' => $apiResponse['data']['address_in'],
            'amount' => $amount,
            'currency' => $currency,
            'qr_code' => $this->generateQRCodeUrl($currency, $apiResponse['data']['address_in'], $amount),
            'expires_at' => time() + 86400,
            'confirmations_required' => $confirmations
        ];
    }

    /**
     * Process webhook from CryptAPI
     * 
     * @param array $webhookData Raw webhook data
     * @return array Processing result
     */
    public function processWebhook(array $webhookData): array
    {
        try {
            // Validate webhook data
            $requiredFields = ['address_in', 'address_out', 'txid_in', 'txid_out', 'confirmations', 'value'];
            foreach ($requiredFields as $field) {
                if (!isset($webhookData[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            $addressIn = $webhookData['address_in'];
            $confirmations = (int)$webhookData['confirmations'];
            $value = (float)$webhookData['value'];

            // Find invoice by address
            $invoice = $this->database->queryOne(
                'SELECT * FROM invoices WHERE address_in = ? AND status IN (?, ?)',
                [$addressIn, 'new', 'processing']
            );

            if (!$invoice) {
                throw new Exception("Invoice not found for address: {$addressIn}");
            }

            // Update invoice with webhook data
            $this->database->beginTransaction();

            // Store raw webhook data
            $this->database->update('invoices', [
                'webhook_raw' => json_encode($webhookData),
                'confirmations_received' => $confirmations
            ], ['id' => $invoice['id']]);

            $result = ['invoice_id' => $invoice['id'], 'action' => 'none'];

            // Check if this is a pending notification
            if ($confirmations > 0 && !$invoice['is_pending_notified']) {
                $this->database->update('invoices', [
                    'status' => 'processing',
                    'is_pending_notified' => true
                ], ['id' => $invoice['id']]);
                
                $result['action'] = 'pending';
            }

            // Check if payment is confirmed
            if ($confirmations >= $invoice['confirmations_required']) {
                // Verify amount (allow small discrepancies due to fees)
                $expectedAmount = (float)$invoice['crypto_amount'];
                $tolerance = $expectedAmount * 0.01; // 1% tolerance
                
                if ($value >= ($expectedAmount - $tolerance)) {
                    // Payment confirmed - settle invoice
                    $this->database->update('invoices', [
                        'status' => 'settled',
                        'settled_at' => date('Y-m-d H:i:s')
                    ], ['id' => $invoice['id']]);

                    // Process the payment purpose
                    $this->processPurpose($invoice['purpose'], $invoice['user_id']);
                    
                    $result['action'] = 'settled';
                } else {
                    // Insufficient amount
                    $this->database->update('invoices', [
                        'status' => 'failed'
                    ], ['id' => $invoice['id']]);
                    
                    $result['action'] = 'insufficient_amount';
                    $result['error'] = "Insufficient amount: received {$value}, expected {$expectedAmount}";
                }
            }

            $this->database->commit();
            return $result;

        } catch (Exception $e) {
            $this->database->rollback();
            error_log('Webhook processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get invoice status and details
     * 
     * @param int $invoiceId
     * @return array|null
     */
    public function getInvoice(int $invoiceId): ?array
    {
        return $this->database->queryOne('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
    }

    /**
     * Get invoices for a user
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserInvoices(int $userId, int $limit = 50): array
    {
        return $this->database->query(
            'SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    /**
     * Check for expired invoices and mark them as expired
     * 
     * @return int Number of expired invoices
     */
    public function expireOldInvoices(): int
    {
        return $this->database->execute(
            "UPDATE invoices SET status = 'expired' WHERE status IN ('new', 'processing') AND expires_at < NOW()"
        );
    }

    /**
     * Call CryptAPI endpoint
     * 
     * @param string $currency
     * @param array $params
     * @return array
     * @throws Exception
     */
    /**
     * The address to forward payments to: the configured PAYOUT_<coin> if it's
     * a real value, otherwise the site operator's imported wallet (the first
     * admin who has that coin's address set on their profile).
     */
    private function resolvePayoutAddress(string $currency): string
    {
        $addr = (string)$this->config->getPayoutAddress($currency);
        if ($addr !== '' && !str_starts_with($addr, 'your-')) {
            return $addr;
        }

        $col = 'wallet_' . strtolower($currency);
        if (!in_array($col, ['wallet_btc', 'wallet_xmr', 'wallet_eth', 'wallet_sol', 'wallet_doge'], true)) {
            return '';
        }
        $row = $this->database->queryOne(
            "SELECT {$col} AS addr FROM users WHERE is_admin = true AND {$col} <> '' ORDER BY id ASC LIMIT 1"
        );
        return (string)($row['addr'] ?? '');
    }

    private function callCryptAPI(string $currency, array $params): array
    {
        $currency = strtolower($currency);
        $url = "https://api.cryptapi.io/{$currency}/create/?" . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
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
            throw new Exception("CryptAPI HTTP error: {$httpCode}");
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from CryptAPI');
        }

        if (!isset($data['status'])) {
            throw new Exception('Invalid response format from CryptAPI');
        }

        if ($data['status'] !== 'success') {
            $errorMsg = $data['error'] ?? 'Unknown error';
            return ['success' => false, 'error' => $errorMsg];
        }

        return ['success' => true, 'data' => $data];
    }

    /**
     * Generate QR code URL for payment
     * 
     * @param string $currency
     * @param string $address
     * @param float $amount
     * @return string
     */
    private function generateQRCodeUrl(string $currency, string $address, float $amount): string
    {
        $currency = strtolower($currency);
        
        // Build payment URI
        $paymentUri = match ($currency) {
            'btc' => "bitcoin:{$address}?amount={$amount}",
            'eth' => "ethereum:{$address}?value=" . ($amount * 1e18), // Convert to wei
            'doge' => "dogecoin:{$address}?amount={$amount}",
            default => "{$currency}:{$address}?amount={$amount}"
        };

        // Use CryptAPI's QR code service
        return "https://api.cryptapi.io/{$currency}/qrcode/?address={$address}&value={$amount}&size=300";
    }

    /**
     * Process payment purpose (e.g., publish listing)
     * 
     * @param string $purpose
     * @param int $userId
     * @throws Exception
     */
    private function processPurpose(string $purpose, int $userId): void
    {
        $parts = explode(':', $purpose, 2);
        
        if (count($parts) !== 2) {
            throw new Exception("Invalid purpose format: {$purpose}");
        }

        [$action, $targetId] = $parts;
        $targetId = (int)$targetId;

        switch ($action) {
            case 'post_listing':
                // Publish the listing
                $updated = $this->database->update('listings', [
                    'is_published' => true,
                    'published_at' => date('Y-m-d H:i:s')
                ], [
                    'id' => $targetId,
                    'user_id' => $userId,
                    'is_published' => false
                ]);

                if ($updated === 0) {
                    throw new Exception("Listing not found or already published: {$targetId}");
                }
                break;

            case 'bump_listing':
                // Bump the listing (update timestamp)
                $updated = $this->database->update('listings', [
                    'updated_at' => date('Y-m-d H:i:s')
                ], [
                    'id' => $targetId,
                    'user_id' => $userId,
                    'is_published' => true
                ]);

                if ($updated === 0) {
                    throw new Exception("Listing not found or not published: {$targetId}");
                }
                break;

            default:
                throw new Exception("Unknown purpose action: {$action}");
        }
    }

    /**
     * Test CryptAPI connectivity
     * 
     * @return array Test results
     */
    public function testConnection(): array
    {
        $results = [
            'api_reachable' => false,
            'currencies_supported' => [],
            'payout_addresses_configured' => [],
            'error' => null
        ];

        try {
            // Test basic API connectivity
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.cryptapi.io/btc/info/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $results['api_reachable'] = true;
            }

            // Check supported currencies and payout addresses
            foreach ($this->config->getSupportedCurrencies() as $currency) {
                $payoutAddress = $this->config->getPayoutAddress($currency);
                
                $results['currencies_supported'][$currency] = !empty($payoutAddress);
                $results['payout_addresses_configured'][$currency] = !empty($payoutAddress);
            }

        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }
}