<?php
/**
 * Warrant Canary Page
 * 
 * A warrant canary is a method by which a service provider can inform users
 * that they have NOT been served with secret government subpoenas or warrants.
 * If this page is removed or not updated regularly, it may indicate that
 * such legal processes have been served.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warrant Canary - <?= htmlspecialchars($this->config->get('APP_NAME', 'Marketplace')) ?></title>
    <style>
        body {
            font-family: monospace;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #1a1a1a;
            color: #00ff00;
            line-height: 1.6;
        }
        .container {
            border: 2px solid #00ff00;
            padding: 30px;
            background-color: #000;
        }
        h1 {
            color: #00ff00;
            text-align: center;
            border-bottom: 2px solid #00ff00;
            padding-bottom: 10px;
        }
        .warning {
            background-color: #1a1a00;
            border: 1px solid #ffff00;
            color: #ffff00;
            padding: 15px;
            margin: 20px 0;
        }
        .statement {
            margin: 10px 0;
            padding: 10px;
            border-left: 3px solid #00ff00;
            padding-left: 15px;
        }
        .date-info {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #0a0a0a;
            border: 1px solid #00ff00;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #00ff00;
            text-align: center;
            font-size: 0.9em;
        }
        a {
            color: #00ff00;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .status-ok {
            color: #00ff00;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐦 WARRANT CANARY 🐦</h1>
        
        <div class="date-info">
            <p><strong>Last Updated:</strong> <?= htmlspecialchars($last_updated) ?></p>
            <p><strong>Next Scheduled Update:</strong> <?= htmlspecialchars($next_update_date) ?></p>
        </div>

        <div class="warning">
            <strong>⚠️ IMPORTANT:</strong> This page serves as a warrant canary. If this page is not updated 
            by the scheduled date above, or if any of the statements below are removed or modified, 
            it may indicate that legal processes have been served that prevent us from maintaining 
            this canary.
        </div>

        <h2>Current Status: <span class="status-ok">✓ ACTIVE</span></h2>

        <p>As of <strong><?= htmlspecialchars($current_date) ?></strong>, we declare the following:</p>

        <?php foreach ($statements as $statement): ?>
            <div class="statement">
                ✓ <?= htmlspecialchars($statement) ?>
            </div>
        <?php endforeach; ?>

        <div class="warning" style="margin-top: 30px;">
            <h3>What is a Warrant Canary?</h3>
            <p>
                A warrant canary is a posted document stating that an organization has not received 
                any secret subpoenas during a specific period of time. If the canary is not updated 
                or is removed, users may infer that the service provider has been served with such 
                a subpoena. The intention is to allow the provider to warn users of the existence 
                of a subpoena passively, without disclosing to others that the government has sought 
                or obtained access to information or records under a secret subpoena.
            </p>
        </div>

        <div class="footer">
            <p>This warrant canary is updated quarterly.</p>
            <p>For questions or concerns, please verify this page's authenticity through multiple channels.</p>
            <p><a href="/">← Return to Home</a></p>
        </div>
    </div>
</body>
</html>