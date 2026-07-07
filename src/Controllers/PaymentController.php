<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TatumService;
use App\Services\CryptAPIService;
use Exception;

class PaymentController extends BaseController
{
    /**
     * Cron endpoint: poll CoinPay for open invoices and settle paid ones. A Tor
     * hidden service can't receive webhooks, so a cron on the box curls this
     * (e.g. every minute). Protected by ?token=APP_SECRET.
     */
    public function pollPayments(): void
    {
        $token = (string)($_GET['token'] ?? '');
        if (!hash_equals((string)$this->config->get('APP_SECRET'), $token)) {
            $this->json(['success' => false, 'error' => 'forbidden'], 403);
            return;
        }
        $result = (new CryptAPIService($this->config, $this->database))->pollCoinpayInvoices();
        $this->json(['success' => true] + $result);
    }

    /**
     * Cron endpoint: recompute cached crypto prices for active listings so
     * displayed amounts track the market (run hourly). Protected by
     * ?token=APP_SECRET.
     */
    public function refreshPrices(): void
    {
        $token = (string)($_GET['token'] ?? '');
        if (!hash_equals((string)$this->config->get('APP_SECRET'), $token)) {
            $this->json(['success' => false, 'error' => 'forbidden'], 403);
            return;
        }
        $result = (new \App\Services\PricingService($this->config, $this->database))->refreshActive();
        $this->json(['success' => true] + $result);
    }

    /**
     * Standalone, no-JS status panel loaded in an iframe on the pay page. It
     * meta-refreshes every 5s and polls CoinPay on each load, so payment is
     * detected within seconds. Rendered without the site layout (lightweight).
     */
    public function paymentStatus(array $params): void
    {
        $invoiceId = (int)$params['invoiceId'];
        $userId = $this->session->getUserId();

        $svc = new CryptAPIService($this->config, $this->database);
        $invoice = $svc->getInvoice($invoiceId);
        if (!$invoice || $invoice['user_id'] !== $userId) {
            throw new Exception('Invoice not found', 404);
        }

        // Opportunistically settle via CoinPay, then reload the fresh row.
        $status = $svc->checkAndSettleInvoice($invoiceId);
        $invoice = $svc->getInvoice($invoiceId);

        $listingId = 0;
        $parts = explode(':', (string)$invoice['purpose']);
        if (count($parts) === 2 && $parts[0] === 'post_listing') {
            $listingId = (int)$parts[1];
        }

        // Render the standalone fragment directly (no base layout).
        include __DIR__ . '/../../templates/payments/status.php';
    }

    /**
     * Server-rendered QR of the payment URI, as SVG (no JS, no external image —
     * served by the onion itself). Owner-only.
     */
    public function paymentQr(array $params): void
    {
        $invoiceId = (int)$params['invoiceId'];
        $userId = $this->session->getUserId();

        $invoice = (new CryptAPIService($this->config, $this->database))->getInvoice($invoiceId);
        if (!$invoice || $invoice['user_id'] !== $userId) {
            throw new Exception('Invoice not found', 404);
        }

        $cur = strtolower((string)$invoice['currency']);
        $amt = rtrim(rtrim(number_format((float)$invoice['crypto_amount'], 18, '.', ''), '0'), '.');
        $scheme = ['btc' => 'bitcoin', 'eth' => 'ethereum', 'doge' => 'dogecoin', 'xmr' => 'monero', 'sol' => 'solana'][$cur] ?? $cur;
        $amtParam = $cur === 'xmr' ? 'tx_amount' : ($cur === 'eth' ? 'value' : 'amount');
        $uri = "{$scheme}:{$invoice['address_in']}?{$amtParam}={$amt}";

        $options = new \chillerlan\QRCode\QROptions([
            'outputType'  => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
            'eccLevel'    => \chillerlan\QRCode\Common\EccLevel::M,
            'addQuietzone' => true,
            'svgViewBoxSize' => 0,
        ]);

        header('Content-Type: image/svg+xml');
        header('Cache-Control: private, max-age=300');
        echo (new \chillerlan\QRCode\QRCode($options))->render($uri);
    }

    public function showPayment(array $params): void
    {
        $invoiceId = (int)$params['invoiceId'];
        $userId = $this->session->getUserId();

        // Get invoice
        $cryptoService = new CryptAPIService($this->config, $this->database);
        $invoice = $cryptoService->getInvoice($invoiceId);

        if (!$invoice || $invoice['user_id'] !== $userId) {
            throw new Exception('Invoice not found', 404);
        }

        // Check if invoice is expired
        if (strtotime($invoice['expires_at']) < time()) {
            $this->setFlash('error', 'This payment has expired. Please create a new listing.');
            $this->redirect('/my-listings');
            return;
        }

        // If already settled, redirect to success
        if ($invoice['status'] === 'settled') {
            $this->setFlash('success', 'Payment completed successfully!');
            
            // Extract listing ID from purpose
            $parts = explode(':', $invoice['purpose']);
            if (count($parts) === 2 && $parts[0] === 'post_listing') {
                $this->redirect('/listing/' . $parts[1]);
                return;
            }
            
            $this->redirect('/my-listings');
            return;
        }

        $this->render('payments/show', [
            'invoice' => $invoice
        ]);
    }

    public function createInvoice(): void
    {
        $data = $this->sanitizeArray($_POST);
        $userId = $this->session->getUserId();

        // Validate input
        $errors = $this->validateInput([
            'purpose' => 'required',
            'currency' => 'required',
            'amount_usd' => 'required|numeric'
        ], $data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Please choose a valid payment option and amount.');
            $this->redirectBack('/my-listings');
            return;
        }

        try {
            $currency = strtoupper($data['currency']);
            $amountUsd = (float)$data['amount_usd'];
            $purpose = $data['purpose'];

            // Validate currency
            if (!in_array($currency, $this->config->getSupportedCurrencies())) {
                throw new Exception('Unsupported currency');
            }

            // Get exchange rate and calculate crypto amount
            $tatumService = new TatumService($this->config);
            $cryptoAmount = $tatumService->convertUsdToCrypto($amountUsd, $currency);
            $exchangeRate = $tatumService->getExchangeRate('USD', $currency);

            // Create invoice
            $cryptoService = new CryptAPIService($this->config, $this->database);
            $invoice = $cryptoService->createInvoice(
                $currency,
                $cryptoAmount,
                $purpose,
                $userId,
                $amountUsd,
                $exchangeRate
            );

            // No-JS: redirect straight to the payment page.
            $this->redirect('/pay/' . $invoice['invoice_id']);

        } catch (Exception $e) {
            error_log('Invoice creation failed: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to create payment invoice. Please try again.');
            $this->redirectBack('/my-listings');
        }
    }

    public function webhook(): void
    {
        // Get raw POST data
        $rawData = file_get_contents('php://input');
        $webhookData = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo 'Invalid JSON';
            return;
        }

        try {
            $cryptoService = new CryptAPIService($this->config, $this->database);
            $result = $cryptoService->processWebhook($webhookData);

            // Log webhook processing
            error_log('Webhook processed: ' . json_encode([
                'invoice_id' => $result['invoice_id'],
                'action' => $result['action'],
                'timestamp' => date('c')
            ]));

            echo 'OK';

        } catch (Exception $e) {
            error_log('Webhook processing failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function payToPublish(array $params): void
    {
        $listingId = (int)$params['listingId'];
        $userId = $this->session->getUserId();

        // Get listing
        $listing = $this->database->queryOne(
            'SELECT * FROM listings WHERE id = ? AND user_id = ? AND is_published = false',
            [$listingId, $userId]
        );

        if (!$listing) {
            throw new Exception('Listing not found or already published', 404);
        }

        // Check if there's already a pending invoice
        $existingInvoice = $this->database->queryOne(
            'SELECT * FROM invoices WHERE purpose = ? AND user_id = ? AND status IN (?, ?) AND expires_at > NOW()',
            ["post_listing:{$listingId}", $userId, 'new', 'processing']
        );

        if ($existingInvoice) {
            $this->redirect('/pay/' . $existingInvoice['id']);
            return;
        }

        // Get exchange rates for all supported currencies
        $tatumService = new TatumService($this->config);
        $listingPriceUsd = $this->config->get('LISTING_PRICE_CENTS', 100) / 100;
        $paymentAmounts = $tatumService->calculatePaymentAmounts($listingPriceUsd);

        $this->render('payments/pay-to-publish', [
            'listing' => $listing,
            'price_usd' => $listingPriceUsd,
            'payment_amounts' => $paymentAmounts,
            'supported_currencies' => $this->config->getSupportedCurrencies()
        ]);
    }

    public function bumpListing(array $params): void
    {
        $listingId = (int)$params['listingId'];
        $userId = $this->session->getUserId();

        // Get listing
        $listing = $this->database->queryOne(
            'SELECT * FROM listings WHERE id = ? AND user_id = ? AND is_published = true',
            [$listingId, $userId]
        );

        if (!$listing) {
            throw new Exception('Listing not found or not published', 404);
        }

        // Check if there's already a pending invoice
        $existingInvoice = $this->database->queryOne(
            'SELECT * FROM invoices WHERE purpose = ? AND user_id = ? AND status IN (?, ?) AND expires_at > NOW()',
            ["bump_listing:{$listingId}", $userId, 'new', 'processing']
        );

        if ($existingInvoice) {
            $this->redirect('/pay/' . $existingInvoice['id']);
            return;
        }

        // Get exchange rates for all supported currencies
        $tatumService = new TatumService($this->config);
        $bumpPriceUsd = $this->config->get('BUMP_PRICE_CENTS', 50) / 100;
        $paymentAmounts = $tatumService->calculatePaymentAmounts($bumpPriceUsd);

        $this->render('payments/bump-listing', [
            'listing' => $listing,
            'price_usd' => $bumpPriceUsd,
            'payment_amounts' => $paymentAmounts,
            'supported_currencies' => $this->config->getSupportedCurrencies()
        ]);
    }

    public function invoiceStatus(array $params): void
    {
        $invoiceId = (int)$params['invoiceId'];
        $userId = $this->session->getUserId();

        $cryptoService = new CryptAPIService($this->config, $this->database);
        $invoice = $cryptoService->getInvoice($invoiceId);

        if (!$invoice || $invoice['user_id'] !== $userId) {
            $this->json(['error' => 'Invoice not found'], 404);
            return;
        }

        $this->json([
            'status' => $invoice['status'],
            'confirmations_received' => (int)$invoice['confirmations_received'],
            'confirmations_required' => (int)$invoice['confirmations_required'],
            'expires_at' => strtotime($invoice['expires_at']),
            'settled_at' => $invoice['settled_at'] ? strtotime($invoice['settled_at']) : null
        ]);
    }

    public function testServices(): void
    {
        // Only allow admin access
        if (!$this->session->isLoggedIn()) {
            throw new Exception('Access denied', 403);
        }

        $user = $this->getCurrentUser();
        if (!$user || !$user['is_admin']) {
            throw new Exception('Admin access required', 403);
        }

        // Test Tatum service
        $tatumService = new TatumService($this->config);
        $tatumTest = $tatumService->testConnection();

        // Test CryptAPI service
        $cryptoService = new CryptAPIService($this->config, $this->database);
        $cryptoTest = $cryptoService->testConnection();

        // Get some sample rates
        $sampleRates = [];
        try {
            $sampleRates = $tatumService->getAllRates();
        } catch (Exception $e) {
            $sampleRates['error'] = $e->getMessage();
        }

        $this->render('admin/test-services', [
            'tatum_test' => $tatumTest,
            'crypto_test' => $cryptoTest,
            'sample_rates' => $sampleRates,
            'tatum_cache' => $tatumService->getCacheStats()
        ]);
    }
}