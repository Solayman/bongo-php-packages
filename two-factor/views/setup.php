<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('locale') ? locale() : 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(__('two-factor::Enable Two-Factor Authentication'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .two-factor-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); max-width: 440px; width: 100%; padding: 32px; text-align: center; }
        .two-factor-title { font-size: 20px; font-weight: 700; margin-bottom: 12px; color: #0f172a; }
        .two-factor-desc { color: #64748b; font-size: 14px; line-height: 1.5; margin-bottom: 20px; }
        .two-factor-qr { background: #ffffff; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-block; margin-bottom: 16px; }
        .two-factor-secret-box { background: #f1f5f9; padding: 10px 14px; border-radius: 6px; font-family: monospace; font-size: 15px; font-weight: 600; color: #334155; letter-spacing: 2px; margin-bottom: 24px; user-select: all; }
        .two-factor-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 18px; text-align: center; letter-spacing: 4px; box-sizing: border-box; margin-bottom: 16px; }
        .two-factor-btn { width: 100%; background-color: #2563eb; color: #ffffff; border: none; padding: 12px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .two-factor-btn:hover { background-color: #1d4ed8; }
        .two-factor-alert { padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; text-align: left; }
        .two-factor-error { background-color: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="two-factor-card">
        <h1 class="two-factor-title"><?= htmlspecialchars(__('two-factor::Enable Two-Factor Authentication'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="two-factor-desc"><?= htmlspecialchars(__('two-factor::Scan this QR code with your authenticator app (Google Authenticator, Authy, or 1Password).'), ENT_QUOTES, 'UTF-8') ?></p>
        
        <?php if (!empty($qrCodeSvg)): ?>
            <div class="two-factor-qr">
                <?= $qrCodeSvg ?>
            </div>
        <?php endif; ?>

        <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;"><?= htmlspecialchars(__('two-factor::Or enter this code manually'), ENT_QUOTES, 'UTF-8') ?>:</div>
        <div class="two-factor-secret-box"><?= htmlspecialchars((string) ($secret ?? ''), ENT_QUOTES, 'UTF-8') ?></div>

        <form action="/2fa/confirm" method="POST">
            <?= csrf_field() ?>
            <input type="text" name="code" class="two-factor-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            <button type="submit" class="two-factor-btn"><?= htmlspecialchars(__('two-factor::Confirm and Activate'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    </div>
</body>
</html>
