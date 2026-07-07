<?php
$title = 'Pay to Publish Listing';
ob_start();
?>

<div style="max-width: 800px; margin: 0 auto;">
    <h1>Pay to Publish Your Listing</h1>
    
    <!-- Listing Preview -->
    <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
        <h3>Listing Preview</h3>
        <div style="margin-top: 15px;">
            <h4><?= htmlspecialchars($listing['title']) ?></h4>
            <p style="color: #666; margin: 10px 0;">
                <?= htmlspecialchars(substr($listing['body'], 0, 200)) ?><?= strlen($listing['body']) > 200 ? '...' : '' ?>
            </p>
            <?php if ((int)($listing['price_usd_cents'] ?? 0) > 0): ?>
                <p><strong>Price:</strong> <?= htmlspecialchars(\App\Core\Price::label($listing)) ?></p>
            <?php endif; ?>
            <?php if (!empty($listing['location'])): ?>
                <p><strong>Location:</strong> <?= htmlspecialchars($listing['location']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Options -->
    <div style="margin-bottom: 30px;">
        <h2>Choose Payment Method</h2>
        <p>Publishing fee: <strong>$<?= number_format($price_usd, 2) ?> USD</strong></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php foreach ($supported_currencies as $currency): ?>
                <?php if (isset($payment_amounts[$currency]) && !isset($payment_amounts[$currency]['error'])): ?>
                    <div style="border: 1px solid #ddd; border-radius: 5px; padding: 20px; background: white; text-align: center;">
                        <h3 style="margin-bottom: 15px; color: #333;">
                            <?= strtoupper($currency) ?>
                        </h3>
                        
                        <div style="font-size: 18px; font-weight: bold; color: #28a745; margin-bottom: 10px;">
                            <?= number_format($payment_amounts[$currency]['amount'], 8) ?>
                        </div>
                        
                        <div style="font-size: 12px; color: #666; margin-bottom: 15px;">
                            Rate: $<?= number_format($payment_amounts[$currency]['rate'], 2) ?> USD
                        </div>
                        
                        <form method="post" action="/create-invoice">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="purpose" value="post_listing:<?= $listing['id'] ?>">
                            <input type="hidden" name="currency" value="<?= $currency ?>">
                            <input type="hidden" name="amount_usd" value="<?= $price_usd ?>">
                            
                            <button type="submit" style="width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 16px;">
                                Pay with <?= strtoupper($currency) ?>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="border: 1px solid #ddd; border-radius: 5px; padding: 20px; background: #f8f9fa; text-align: center; opacity: 0.6;">
                        <h3 style="margin-bottom: 15px; color: #666;">
                            <?= strtoupper($currency) ?>
                        </h3>
                        <div style="color: #dc3545; font-size: 14px;">
                            Rate unavailable
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment Information -->
    <div style="margin-bottom: 30px; padding: 20px; background: #e7f3ff; border-left: 4px solid #007bff; border-radius: 5px;">
        <h3>💰 Why Payment is Required</h3>
        <ul>
            <li><strong>Spam Prevention:</strong> Small fee prevents automated spam listings</li>
            <li><strong>Quality Control:</strong> Ensures serious sellers and quality listings</li>
            <li><strong>Platform Maintenance:</strong> Helps cover server and development costs</li>
            <li><strong>Instant Publishing:</strong> Your listing goes live immediately after payment</li>
        </ul>
    </div>

    <!-- Security Notice -->
    <div style="margin-bottom: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px;">
        <h3>🔒 Payment Security</h3>
        <ul>
            <li>Payments are processed directly on the blockchain</li>
            <li>We never store your private keys or wallet information</li>
            <li>All transactions are irreversible - double-check amounts</li>
            <li>Payment addresses are unique and expire after 24 hours</li>
            <li>Your listing will be published automatically upon confirmation</li>
        </ul>
    </div>

    <!-- Supported Networks -->
    <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
        <h3>📡 Supported Networks</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 15px;">
            <div><strong>Bitcoin (BTC):</strong> Main network</div>
            <div><strong>Monero (XMR):</strong> Main network</div>
            <div><strong>Ethereum (ETH):</strong> Main network</div>
            <div><strong>Solana (SOL):</strong> Main network</div>
            <div><strong>Dogecoin (DOGE):</strong> Main network</div>
        </div>
        <p style="margin-top: 15px; font-size: 14px; color: #666;">
            <strong>Important:</strong> Only send from the main networks. Do not use test networks or layer 2 solutions.
        </p>
    </div>

    <!-- Cancel Option -->
    <div style="text-align: center; margin-top: 40px;">
        <a href="/my-listings" style="color: #6c757d; text-decoration: none;">
            ← Cancel and return to my listings
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>