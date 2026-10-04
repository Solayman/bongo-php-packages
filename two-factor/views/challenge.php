<!DOCTYPE html>
<html lang="<?= htmlspecialchars(function_exists('locale') ? locale() : 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(__('two-factor::Two-Factor Authentication'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .two-factor-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); max-width: 400px; width: 100%; padding: 32px; text-align: center; }
        .two-factor-icon { width: 52px; height: 52px; background-color: #eff6ff; color: #2563eb; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 24px; }
        .two-factor-title { font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #0f172a; }
        .two-factor-desc { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .two-factor-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 20px; text-align: center; letter-spacing: 4px; box-sizing: border-box; margin-bottom: 16px; }
        .two-factor-btn { width: 100%; background-color: #2563eb; color: #ffffff; border: none; padding: 12px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background-color 0.2s; }
        .two-factor-btn:hover { background-color: #1d4ed8; }
        .two-factor-toggle { display: inline-block; margin-top: 16px; font-size: 13px; color: #2563eb; text-decoration: none; cursor: pointer; }
        .two-factor-toggle:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="two-factor-card">
        <div class="two-factor-icon">🔒</div>
        <h1 class="two-factor-title"><?= htmlspecialchars(__('two-factor::Two-Factor Authentication'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="two-factor-desc"><?= htmlspecialchars(__('two-factor::Enter 6-digit code'), ENT_QUOTES, 'UTF-8') ?></p>

        <form action="/2fa/verify" method="POST" id="totp-form">
            <?= csrf_field() ?>
            <input type="text" name="code" class="two-factor-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            <button type="submit" class="two-factor-btn"><?= htmlspecialchars(__('two-factor::Verify'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>

        <form action="/2fa/verify" method="POST" id="recovery-form" style="display: none;">
            <?= csrf_field() ?>
            <input type="text" name="recovery_code" class="two-factor-input" style="font-size: 16px; letter-spacing: 2px;" placeholder="ABCD-1234" required>
            <button type="submit" class="two-factor-btn"><?= htmlspecialchars(__('two-factor::Verify'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>

        <a class="two-factor-toggle" id="toggle-link" onclick="toggleMode()"><?= htmlspecialchars(__('two-factor::Use a recovery code'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>

    <script>
        function toggleMode() {
            var totp = document.getElementById('totp-form');
            var rec = document.getElementById('recovery-form');
            var link = document.getElementById('toggle-link');
            if (totp.style.display === 'none') {
                totp.style.display = 'block';
                rec.style.display = 'none';
                link.innerText = '<?= addslashes(__('two-factor::Use a recovery code')) ?>';
            } else {
                totp.style.display = 'none';
                rec.style.display = 'block';
                link.innerText = '<?= addslashes(__('two-factor::Use an authentication code')) ?>';
            }
        }
    </script>
</body>
</html>
