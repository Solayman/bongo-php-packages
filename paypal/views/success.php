<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful - PayPal</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .paypal-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); max-width: 480px; width: 100%; padding: 32px; text-align: center; }
        .paypal-icon { width: 56px; height: 56px; background-color: #ecfdf5; color: #059669; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 28px; }
        .paypal-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #0f172a; }
        .paypal-desc { color: #64748b; margin-bottom: 24px; font-size: 15px; }
        .paypal-details { background: #f1f5f9; border-radius: 8px; padding: 16px; text-align: left; margin-bottom: 24px; font-size: 14px; }
        .paypal-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .paypal-row:last-child { margin-bottom: 0; }
        .paypal-label { color: #64748b; }
        .paypal-value { font-weight: 600; color: #1e293b; word-break: break-all; }
        .paypal-btn { display: inline-block; background-color: #0070ba; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: background-color 0.2s; }
        .paypal-btn:hover { background-color: #005ea6; }
    </style>
</head>
<body>
    <div class="paypal-card">
        <div class="paypal-icon">✓</div>
        <h1 class="paypal-title">Payment Successful</h1>
        <p class="paypal-desc">Thank you! Your payment has been confirmed by PayPal.</p>
        
        <div class="paypal-details">
            <?php if (!empty($order_id)): ?>
                <div class="paypal-row">
                    <span class="paypal-label">Order ID:</span>
                    <span class="paypal-value"><?= htmlspecialchars((string) $order_id, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($capture_id)): ?>
                <div class="paypal-row">
                    <span class="paypal-label">Capture ID:</span>
                    <span class="paypal-value"><?= htmlspecialchars((string) $capture_id, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($amount)): ?>
                <div class="paypal-row">
                    <span class="paypal-label">Amount Paid:</span>
                    <span class="paypal-value"><?= htmlspecialchars(strtoupper((string) ($currency ?? 'USD')) . ' ' . number_format((float) $amount, 2), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($status)): ?>
                <div class="paypal-row">
                    <span class="paypal-label">Status:</span>
                    <span class="paypal-value"><?= htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
        </div>

        <a href="/" class="paypal-btn">Return to Store</a>
    </div>
</body>
</html>
