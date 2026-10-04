<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Cancelled - PayPal</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .paypal-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); max-width: 480px; width: 100%; padding: 32px; text-align: center; }
        .paypal-icon { width: 56px; height: 56px; background-color: #fef2f2; color: #dc2626; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 28px; }
        .paypal-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #0f172a; }
        .paypal-desc { color: #64748b; margin-bottom: 24px; font-size: 15px; }
        .paypal-btn { display: inline-block; background-color: #475569; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: background-color 0.2s; }
        .paypal-btn:hover { background-color: #334155; }
    </style>
</head>
<body>
    <div class="paypal-card">
        <div class="paypal-icon">✕</div>
        <h1 class="paypal-title">Payment Cancelled</h1>
        <p class="paypal-desc"><?= !empty($error) ? htmlspecialchars($error, ENT_QUOTES, 'UTF-8') : 'The PayPal payment was cancelled or not completed. No money was charged.' ?></p>

        <a href="/" class="paypal-btn">Return to Store</a>
    </div>
</body>
</html>
