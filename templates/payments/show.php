<?php
$title = 'Payment Required';
ob_start();
?>

<div style="max-width: 600px; margin: 0 auto;">
    <h1>Payment Required</h1>
    
    <div style="margin-bottom: 30px; padding: 20px; background-color: #e7f3ff; border-left: 4px solid #007bff; border-radius: 5px;">
        <h3>Invoice Details</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
            <div>
                <strong>Amount (USD):</strong><br>
                $<?= number_format($invoice['fiat_amount'], 2) ?>
            </div>
            <div>
                <strong>Currency:</strong><br>
                <?= strtoupper($invoice['currency']) ?>
            </div>
            <div>
                <strong>Crypto Amount:</strong><br>
                <?= number_format($invoice['crypto_amount'], 8) ?> <?= strtoupper($invoice['currency']) ?>
            </div>
            <div>
                <strong>Status:</strong><br>
                <span style="padding: 2px 8px; border-radius: 3px; font-size: 12px; <?= $invoice['status'] === 'settled' ? 'background: #d4edda; color: #155724;' : ($invoice['status'] === 'processing' ? 'background: #fff3cd; color: #856404;' : 'background: #f8d7da; color: #721c24;') ?>">
                    <?= ucfirst($invoice['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($invoice['status'] === 'settled'): ?>
        <!-- Payment Completed -->
        <div style="text-align: center; padding: 40px; background: #d4edda; border-radius: 5px; margin-bottom: 30px;">
            <h2 style="color: #155724; margin-bottom: 15px;">✅ Payment Completed!</h2>
            <p style="color: #155724;">Your payment has been confirmed and processed successfully.</p>
            
            <?php 
            $parts = explode(':', $invoice['purpose']);
            if (count($parts) === 2 && $parts[0] === 'post_listing'): 
            ?>
                <a href="/listing/<?= $parts[1] ?>" style="display: inline-block; margin-top: 15px; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">
                    View Your Published Listing
                </a>
            <?php endif; ?>
        </div>
        
    <?php elseif ($invoice['status'] === 'expired'): ?>
        <!-- Payment Expired -->
        <div style="text-align: center; padding: 40px; background: #f8d7da; border-radius: 5px; margin-bottom: 30px;">
            <h2 style="color: #721c24; margin-bottom: 15px;">⏰ Payment Expired</h2>
            <p style="color: #721c24;">This payment has expired. Please create a new listing to try again.</p>
            
            <a href="/create-listing" style="display: inline-block; margin-top: 15px; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 3px;">
                Create New Listing
            </a>
        </div>
        
    <?php else: ?>
        <!-- Payment Pending -->
        <div style="text-align: center; margin-bottom: 30px;">
            <h2>Send Payment</h2>
            <p>Send exactly <strong><?= number_format($invoice['crypto_amount'], 8) ?> <?= strtoupper($invoice['currency']) ?></strong> to the address below:</p>
        </div>

        <!-- Payment Address -->
        <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px; text-align: center;">
            <h3>Payment Address</h3>
            <div style="margin: 20px 0; padding: 15px; background: white; border: 2px dashed #007bff; border-radius: 5px; font-family: monospace; word-break: break-all; font-size: 14px;">
                <?= htmlspecialchars($invoice['address_in']) ?>
            </div>
            
            <?php
            // Self-contained wallet URI (no external image — an onion page must
            // not load off-site resources).
            $cur = strtolower($invoice['currency']);
            $amt = rtrim(rtrim(number_format((float)$invoice['crypto_amount'], 18, '.', ''), '0'), '.');
            $scheme = ['btc' => 'bitcoin', 'eth' => 'ethereum', 'doge' => 'dogecoin', 'xmr' => 'monero', 'sol' => 'solana'][$cur] ?? $cur;
            $amtParam = $cur === 'xmr' ? 'tx_amount' : ($cur === 'eth' ? 'value' : 'amount');
            $uri = "{$scheme}:{$invoice['address_in']}?{$amtParam}={$amt}";
            ?>
            <div style="margin-top: 15px; font-size: 14px; color: #666;">
                <p><a href="<?= htmlspecialchars($uri) ?>">Open in your <?= strtoupper($invoice['currency']) ?> wallet</a>, or copy the address above.</p>
            </div>
        </div>

        <!-- Payment Instructions -->
        <div style="margin-bottom: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px;">
            <h3>Payment Instructions</h3>
            <ol>
                <li>Send exactly <strong><?= number_format($invoice['crypto_amount'], 8) ?> <?= strtoupper($invoice['currency']) ?></strong></li>
                <li>To address: <code><?= htmlspecialchars($invoice['address_in']) ?></code></li>
                <li>Wait for <?= $invoice['confirmations_required'] ?> network confirmation<?= $invoice['confirmations_required'] > 1 ? 's' : '' ?></li>
                <li>Your listing will be published automatically</li>
            </ol>
            
            <div style="margin-top: 15px; font-size: 14px;">
                <strong>⏰ Payment expires:</strong> <?= date('M j, Y \a\t g:i A', strtotime($invoice['expires_at'])) ?>
            </div>
        </div>

        <!-- Live status panel: a no-JS iframe that meta-refreshes every 5s and
             polls for the payment. When it lands, it links you to your listing. -->
        <div style="text-align: center; margin-bottom: 30px;">
            <iframe src="/pay/<?= (int)$invoice['id'] ?>/status" title="Payment status"
                    style="width: 100%; max-width: 420px; height: 96px; border: 1px solid #ddd; border-radius: 5px; background: #fff;"></iframe>
            <div style="margin-top: 12px; font-size: 14px; color: #666;">
                <p>This panel checks for your payment automatically every 5 seconds.</p>
                <p><a href="/pay/<?= (int)$invoice['id'] ?>">Reload this page</a></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Support Information -->
    <div style="margin-top: 40px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
        <h3>Need Help?</h3>
        <ul>
            <li>Make sure you send the exact amount shown above</li>
            <li>Double-check the payment address</li>
            <li>Network confirmations can take 10-60 minutes depending on the cryptocurrency</li>
            <li>If you sent payment but it's not showing, please wait for network confirmations</li>
            <li>Contact support if payment doesn't process within 2 hours</li>
        </ul>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>