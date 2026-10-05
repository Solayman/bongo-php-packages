<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - AamarPay</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 480px; width: 100%; padding: 2rem; text-align: center; }
        .icon { font-size: 3rem; color: #f59e0b; }
        h1 { font-size: 1.5rem; margin-top: 1rem; color: #111827; }
        .btn { display: inline-block; background: #2563eb; color: #fff; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠</div>
        <h1>Payment Cancelled</h1>
        <p>You have cancelled the payment process.</p>
        <?php if (!empty($tran_id)): ?>
            <p><strong>Transaction ID:</strong> <?= htmlspecialchars($tran_id) ?></p>
        <?php endif; ?>
        <a href="/" class="btn">Return to Store</a>
    </div>
</body>
</html>
