<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - AamarPay</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 480px; width: 100%; padding: 2rem; text-align: center; }
        .icon { font-size: 3rem; color: #dc2626; }
        h1 { font-size: 1.5rem; margin-top: 1rem; color: #111827; }
        .details { text-align: left; background: #fef2f2; color: #991b1b; padding: 1rem; border-radius: 6px; margin: 1.5rem 0; font-size: 0.9rem; }
        .btn { display: inline-block; background: #4b5563; color: #fff; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✗</div>
        <h1>Payment Failed</h1>
        <p>Your payment attempt could not be completed.</p>
        <div class="details">
            <p><strong>Transaction ID:</strong> <?= htmlspecialchars($tran_id ?? '') ?></p>
            <p><strong>Reason:</strong> <?= htmlspecialchars($reason ?? 'Declined') ?></p>
        </div>
        <a href="/" class="btn">Try Again</a>
    </div>
</body>
</html>
