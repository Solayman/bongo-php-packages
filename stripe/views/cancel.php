<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('locale') ? locale() : 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(__('stripe::Payment Cancelled'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .stripe-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); max-width: 480px; width: 100%; padding: 32px; text-align: center; }
        .stripe-icon { width: 56px; height: 56px; background-color: #fef2f2; color: #dc2626; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 28px; }
        .stripe-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #0f172a; }
        .stripe-desc { color: #64748b; margin-bottom: 24px; font-size: 15px; }
        .stripe-btn { display: inline-block; background-color: #64748b; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: background-color 0.2s; }
        .stripe-btn:hover { background-color: #475569; }
    </style>
</head>
<body>
    <div class="stripe-card stripe-cancel-card">
        <div class="stripe-icon">✕</div>
        <h1 class="stripe-title"><?= htmlspecialchars(__('stripe::Payment Cancelled'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="stripe-desc"><?= htmlspecialchars(__('stripe::Your payment process was cancelled.'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="/" class="stripe-btn"><?= htmlspecialchars(__('stripe::Return to Store'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</body>
</html>
