<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TatumService;
use App\Services\CryptAPIService;
use Exception;

class PaymentController extends BaseController
{
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
            $this->json(['success' => false, 'errors' => $errors], 400);
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

            $this->json([
                'success' => true,
                'invoice' => $invoice,
                'redirect_url' => '/pay/' . $invoice['invoice_id']
            ]);

        } catch (Exception $e) {
            error_log('Invoice creation failed: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => 'Failed to create payment invoice. Please try again.'
            ], 500);
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