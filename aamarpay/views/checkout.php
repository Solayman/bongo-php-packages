<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AamarPay Payment Checkout</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 480px; width: 100%; padding: 2rem; }
        h1 { font-size: 1.5rem; margin-top: 0; color: #111827; }
        .btn { display: inline-block; width: 100%; text-align: center; background: #ea580c; color: #fff; padding: 0.75rem; border-radius: 6px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; }
        .btn:hover { background: #c2410c; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Pay with AamarPay</h1>
        <?php if (!empty($sandbox)): ?>
            <p><span class="badge">SANDBOX TEST MODE</span></p>
        <?php endif; ?>
        <p>Complete your payment securely via Mobile Banking (bKash, Nagad, Rocket) or Visa/MasterCard.</p>
        <p><strong>Merchant ID:</strong> <?= htmlspecialchars($store_id ?? '') ?></p>
        <button type="button" class="btn" onclick="history.back()">Return to Checkout</button>
    </div>
</body>
</html>
