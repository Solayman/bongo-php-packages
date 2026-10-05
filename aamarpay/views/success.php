<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - AamarPay</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 480px; width: 100%; padding: 2rem; text-align: center; }
        .icon { font-size: 3rem; color: #16a34a; }
        h1 { font-size: 1.5rem; margin-top: 1rem; color: #111827; }
        .details { text-align: left; background: #f9fafb; padding: 1rem; border-radius: 6px; margin: 1.5rem 0; font-size: 0.9rem; }
        .btn { display: inline-block; background: #16a34a; color: #fff; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>Payment Successful!</h1>
        <p>Thank you. Your transaction has been processed.</p>
        <div class="details">
            <p><strong>Transaction ID:</strong> <?= htmlspecialchars($tran_id ?? '') ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($status ?? 'SUCCESS') ?></p>
            <?php if (!empty($amount)): ?>
                <p><strong>Amount Paid:</strong> <?= htmlspecialchars((string) $amount) ?> <?= htmlspecialchars($currency ?? 'BDT') ?></p>
            <?php endif; ?>
        </div>
        <a href="/" class="btn">Return to Home</a>
    </div>
</body>
</html>
