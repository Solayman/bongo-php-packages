<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('locale') ? locale() : 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(__('stripe::Payment Successful'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .stripe-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); max-width: 480px; width: 100%; padding: 32px; text-align: center; }
        .stripe-icon { width: 56px; height: 56px; background-color: #ecfdf5; color: #059669; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 28px; }
        .stripe-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #0f172a; }
        .stripe-desc { color: #64748b; margin-bottom: 24px; font-size: 15px; }
        .stripe-details { background: #f1f5f9; border-radius: 8px; padding: 16px; text-align: left; margin-bottom: 24px; font-size: 14px; }
        .stripe-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .stripe-row:last-child { margin-bottom: 0; }
        .stripe-label { color: #64748b; }
        .stripe-value { font-weight: 600; color: #1e293b; word-break: break-all; }
        .stripe-btn { display: inline-block; background-color: #635bff; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: background-color 0.2s; }
        .stripe-btn:hover { background-color: #4f46e5; }
    </style>
</head>
<body>
    <div class="stripe-card stripe-success-card">
        <div class="stripe-icon">✓</div>
        <h1 class="stripe-title"><?= htmlspecialchars(__('stripe::Payment Successful'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="stripe-desc"><?= htmlspecialchars(__('stripe::Thank you for your payment.'), ENT_QUOTES, 'UTF-8') ?></p>
        
        <div class="stripe-details">
            <?php if (!empty($session_id)): ?>
                <div class="stripe-row">
                    <span class="stripe-label">Session ID:</span>
                    <span class="stripe-value"><?= htmlspecialchars((string) $session_id, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($payment_intent_id)): ?>
                <div class="stripe-row">
                    <span class="stripe-label"><?= htmlspecialchars(__('stripe::Transaction ID'), ENT_QUOTES, 'UTF-8') ?>:</span>
                    <span class="stripe-value"><?= htmlspecialchars((string) $payment_intent_id, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($amount)): ?>
                <div class="stripe-row">
                    <span class="stripe-label"><?= htmlspecialchars(__('stripe::Amount Paid'), ENT_QUOTES, 'UTF-8') ?>:</span>
                    <span class="stripe-value"><?= htmlspecialchars(strtoupper((string) ($currency ?? 'USD')) . ' ' . number_format((float) $amount, 2), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
        </div>

        <a href="/" class="stripe-btn"><?= htmlspecialchars(__('stripe::Return to Store'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</body>
</html>
