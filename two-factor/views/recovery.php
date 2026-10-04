<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('locale') ? locale() : 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(__('two-factor::Recovery Codes'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .two-factor-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); max-width: 480px; width: 100%; padding: 32px; text-align: center; }
        .two-factor-title { font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #0f172a; }
        .two-factor-desc { color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.5; }
        .recovery-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; margin-bottom: 24px; }
        .recovery-code { font-family: monospace; font-size: 15px; font-weight: 600; color: #334155; padding: 8px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; user-select: all; }
        .two-factor-btn { display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: background-color 0.2s; }
        .two-factor-btn:hover { background-color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="two-factor-card">
        <h1 class="two-factor-title"><?= htmlspecialchars(__('two-factor::Recovery Codes'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="two-factor-desc"><?= htmlspecialchars(__('two-factor::Store these recovery codes in a safe place. Each code can only be used once.'), ENT_QUOTES, 'UTF-8') ?></p>

        <div class="recovery-grid">
            <?php foreach (($recoveryCodes ?? []) as $code): ?>
                <div class="recovery-code"><?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
        </div>

        <a href="/" class="two-factor-btn">Continue to App</a>
    </div>
</body>
</html>
