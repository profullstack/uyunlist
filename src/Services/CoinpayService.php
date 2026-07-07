<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Exception;

/**
 * Server-side integration with the CoinPay gateway (coinpayportal.com).
 *
 * All calls happen server-to-server from the onion host; the customer never
 * talks to CoinPay and never sees it — uyunlist renders the returned deposit
 * address on its own page. Because a Tor hidden service can't receive webhook
 * callbacks, confirmation is done by POLLING getStatus() (see PaymentController
 * ::pollPayments), not by an inbound webhook.
 *
 * Enabled only when COINPAY_API_KEY is set; otherwise the caller falls back to
 * paying the operator's address directly.
 */
class CoinpayService
{
    /** CoinPay supports these of our coins (no XMR). */
    private const SUPPORTED = ['BTC', 'ETH', 'SOL', 'DOGE', 'BCH'];

    private Config $config;
    private Database $database;

    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
    }

    public function isEnabled(): bool
    {
        $k = (string)$this->config->get('COINPAY_API_KEY');
        return $k !== '' && $k !== 'your-coinpay-api-key';
    }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), self::SUPPORTED, true);
    }

    private function apiUrl(): string
    {
        return rtrim((string)($this->config->get('COINPAY_API_URL') ?: 'https://coinpayportal.com'), '/');
    }

    /**
     * Create a crypto payment. The deposit address CoinPay returns forwards to
     * $merchantWallet. Returns [payment_id, address, crypto_amount, status].
     */
    public function createPayment(float $amountUsd, string $currency, string $merchantWallet, string $description): array
    {
        $body = [
            'amount_usd'             => $amountUsd,
            'currency'               => strtolower($currency),
            'payment_method'         => 'crypto',
            'merchant_wallet_address' => $merchantWallet,
            'description'            => $description,
        ];
        $businessId = (string)$this->config->get('COINPAY_BUSINESS_ID');
        if ($businessId !== '') {
            $body['business_id'] = $businessId;
        }

        $resp = $this->request('POST', '/api/payments/create', $body);
        if (empty($resp['success']) || empty($resp['payment'])) {
            throw new Exception('CoinPay create failed: ' . ($resp['error'] ?? 'unknown response'));
        }
        $p = $resp['payment'];
        $address = (string)($p['payment_address'] ?? $p['pay_address'] ?? '');
        if ($address === '') {
            throw new Exception('CoinPay returned no payment address');
        }

        return [
            'payment_id'    => (string)($p['id'] ?? ''),
            'address'       => $address,
            'crypto_amount' => (float)($p['crypto_amount'] ?? $p['amount_crypto'] ?? $p['pay_amount'] ?? 0),
            'status'        => (string)($p['status'] ?? 'new'),
        ];
    }

    /** Poll a payment's status (e.g. pending, confirmed, forwarded, expired). */
    public function getStatus(string $paymentId): string
    {
        $resp = $this->request('GET', '/api/payments/' . rawurlencode($paymentId), null);
        $p = $resp['payment'] ?? $resp;
        return strtolower((string)($p['status'] ?? 'unknown'));
    }

    /** CoinPay statuses that mean the merchant has (or will have) the funds. */
    public function isPaidStatus(string $status): bool
    {
        return in_array(strtolower($status), ['confirmed', 'forwarded', 'completed', 'settled', 'paid'], true);
    }

    private function request(string $method, string $path, ?array $body): array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $this->apiUrl() . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . (string)$this->config->get('COINPAY_API_KEY'),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new Exception('CoinPay cURL error: ' . $err);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new Exception("CoinPay invalid JSON (HTTP {$code})");
        }
        if ($code >= 400) {
            throw new Exception("CoinPay HTTP {$code}: " . ($data['error'] ?? ''));
        }
        return $data;
    }
}
