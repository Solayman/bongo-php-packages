<?php

declare(strict_types=1);

namespace Bongo\Packages\SweetAlert;

use System\Http\Session;

class SweetAlert
{
    protected static array $inMemoryAlerts = [];

    /**
     * Trigger or flash an alert modal.
     */
    public static function alert(string $title, string $text = '', string $icon = 'info', array $options = []): self
    {
        return static::add(array_merge([
            'title' => $title,
            'text'  => $text,
            'icon'  => $icon,
            'is_confirm' => false,
        ], $options));
    }

    public static function success(string $title, string $text = '', array $options = []): self
    {
        return static::alert($title, $text, 'success', $options);
    }

    public static function error(string $title, string $text = '', array $options = []): self
    {
        return static::alert($title, $text, 'error', $options);
    }

    public static function warning(string $title, string $text = '', array $options = []): self
    {
        return static::alert($title, $text, 'warning', $options);
    }

    public static function info(string $title, string $text = '', array $options = []): self
    {
        return static::alert($title, $text, 'info', $options);
    }

    /**
     * Queue a confirmation dialog.
     */
    public static function confirm(string $title, string $text, array $options = []): self
    {
        return static::add(array_merge([
            'title' => $title,
            'text'  => $text,
            'icon'  => $options['icon'] ?? 'question',
            'is_confirm' => true,
            'confirm_button_text' => $options['confirm_button_text'] ?? config('sweet-alert.confirm_button_text', 'Yes, continue'),
            'cancel_button_text'  => $options['cancel_button_text'] ?? config('sweet-alert.cancel_button_text', 'Cancel'),
            'confirm_button_color' => $options['confirm_button_color'] ?? config('sweet-alert.confirm_button_color', '#2563eb'),
        ], $options));
    }

    /**
     * Queue a danger action confirmation dialog (e.g., delete).
     */
    public static function dangerConfirm(string $title, string $text, array $options = []): self
    {
        return static::confirm($title, $text, array_merge([
            'icon' => 'warning',
            'confirm_button_color' => config('sweet-alert.danger_button_color', '#dc2626'),
            'confirm_button_text'  => $options['confirm_button_text'] ?? 'Yes, delete it!',
        ], $options));
    }

    /**
     * Add alert to memory and session storage.
     */
    public static function add(array $alertData): self
    {
        $sessionKey = config('sweet-alert.session_key', 'bongo_sweet_alerts');
        $id = 'alert_' . bin2hex(random_bytes(4));
        $alert = array_merge([
            'id' => $id,
            'allow_outside_click' => config('sweet-alert.allow_outside_click', true),
            'allow_escape_key'    => config('sweet-alert.allow_escape_key', true),
            'confirm_button_text' => config('sweet-alert.confirm_button_text', 'OK'),
            'cancel_button_text'  => config('sweet-alert.cancel_button_text', 'Cancel'),
            'confirm_button_color' => config('sweet-alert.confirm_button_color', '#2563eb'),
        ], $alertData);

        static::$inMemoryAlerts[] = $alert;

        $existing = Session::get($sessionKey, []);
        if (!is_array($existing)) {
            $existing = [];
        }
        $existing[] = $alert;
        Session::put($sessionKey, $existing);

        return new static();
    }

    /**
     * Retrieve all queued alerts and clear session.
     */
    public static function all(): array
    {
        $sessionKey = config('sweet-alert.session_key', 'bongo_sweet_alerts');
        $fromSession = Session::get($sessionKey, []);
        if (!is_array($fromSession)) {
            $fromSession = [];
        }

        $all = array_merge(static::$inMemoryAlerts, $fromSession);
        static::clear();

        $unique = [];
        foreach ($all as $item) {
            if (isset($item['id'])) {
                $unique[$item['id']] = $item;
            }
        }

        return array_values($unique);
    }

    public static function has(): bool
    {
        $sessionKey = config('sweet-alert.session_key', 'bongo_sweet_alerts');
        $fromSession = Session::get($sessionKey, []);
        return !empty(static::$inMemoryAlerts) || (!empty($fromSession) && is_array($fromSession));
    }

    public static function clear(): void
    {
        static::$inMemoryAlerts = [];
        $sessionKey = config('sweet-alert.session_key', 'bongo_sweet_alerts');
        Session::forget($sessionKey);
    }

    /**
     * Render the modal DOM container, styles, and client JS engine.
     */
    public static function render(): string
    {
        $alerts = static::all();
        $jsonAlerts = json_encode($alerts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<div id="bongo-swal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.65); backdrop-filter:blur(4px); z-index:999999; align-items:center; justify-content:center; padding:16px;">
    <div id="bongo-swal-modal" role="dialog" aria-modal="true" style="background:#fff; border-radius:14px; max-width:440px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); padding:28px 24px; text-align:center; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; transform:scale(0.92); opacity:0; transition:all 0.25s cubic-bezier(0.16,1,0.3,1);">
        <div id="bongo-swal-icon" style="margin:0 auto 16px auto; width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center;"></div>
        <h3 id="bongo-swal-title" style="margin:0 0 10px 0; font-size:20px; font-weight:700; color:#0f172a;"></h3>
        <p id="bongo-swal-text" style="margin:0 0 24px 0; font-size:14px; color:#475569; line-height:1.5; word-break:break-word;"></p>
        <div id="bongo-swal-actions" style="display:flex; justify-content:center; gap:12px;">
            <button id="bongo-swal-cancel" style="display:none; padding:10px 20px; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer; transition:background 0.2s;"></button>
            <button id="bongo-swal-confirm" style="padding:10px 24px; border:none; background:#2563eb; color:#fff; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer; transition:opacity 0.2s;"></button>
        </div>
    </div>
</div>
<script>
(function() {
    var backdrop = document.getElementById('bongo-swal-backdrop');
    var modal = document.getElementById('bongo-swal-modal');
    var iconEl = document.getElementById('bongo-swal-icon');
    var titleEl = document.getElementById('bongo-swal-title');
    var textEl = document.getElementById('bongo-swal-text');
    var cancelBtn = document.getElementById('bongo-swal-cancel');
    var confirmBtn = document.getElementById('bongo-swal-confirm');

    var currentResolve = null;
    var currentAlert = null;

    var icons = {
        success: { bg: '#dcfce7', color: '#16a34a', svg: '<svg width="28" height="28" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' },
        error:   { bg: '#fee2e2', color: '#dc2626', svg: '<svg width="28" height="28" fill="none" stroke="#dc2626" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' },
        warning: { bg: '#fef3c7', color: '#d97706', svg: '<svg width="28" height="28" fill="none" stroke="#d97706" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>' },
        info:    { bg: '#dbeafe', color: '#2563eb', svg: '<svg width="28" height="28" fill="none" stroke="#2563eb" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' },
        question:{ bg: '#f1f5f9', color: '#475569', svg: '<svg width="28" height="28" fill="none" stroke="#475569" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' }
    };

    function closeDialog(result) {
        modal.style.transform = 'scale(0.92)';
        modal.style.opacity = '0';
        setTimeout(function() {
            backdrop.style.display = 'none';
            if (currentResolve) {
                currentResolve(result);
                currentResolve = null;
            }
        }, 200);
    }

    confirmBtn.onclick = function() { closeDialog({ isConfirmed: true, isDismissed: false }); };
    cancelBtn.onclick = function() { closeDialog({ isConfirmed: false, isDismissed: true }); };

    backdrop.onclick = function(e) {
        if (e.target === backdrop && currentAlert && currentAlert.allow_outside_click !== false) {
            closeDialog({ isConfirmed: false, isDismissed: true });
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && backdrop.style.display === 'flex' && currentAlert && currentAlert.allow_escape_key !== false) {
            closeDialog({ isConfirmed: false, isDismissed: true });
        }
    });

    window.BongoAlert = {
        fire: function(opt) {
            if (typeof opt === 'string') {
                opt = { title: opt, text: arguments[1] || '', icon: arguments[2] || 'info' };
            }
            return new Promise(function(resolve) {
                currentResolve = resolve;
                currentAlert = opt;

                titleEl.textContent = opt.title || '';
                textEl.textContent = opt.text || '';

                var iconDef = icons[opt.icon || 'info'] || icons.info;
                iconEl.style.background = iconDef.bg;
                iconEl.innerHTML = iconDef.svg;

                confirmBtn.textContent = opt.confirm_button_text || opt.confirmButtonText || 'OK';
                confirmBtn.style.background = opt.confirm_button_color || opt.confirmButtonColor || '#2563eb';

                if (opt.is_confirm || opt.showCancelButton) {
                    cancelBtn.style.display = 'inline-block';
                    cancelBtn.textContent = opt.cancel_button_text || opt.cancelButtonText || 'Cancel';
                } else {
                    cancelBtn.style.display = 'none';
                }

                backdrop.style.display = 'flex';
                setTimeout(function() {
                    modal.style.transform = 'scale(1)';
                    modal.style.opacity = '1';
                    confirmBtn.focus();
                }, 10);
            });
        },
        confirm: function(title, text, opt) {
            return this.fire(Object.assign({
                title: title,
                text: text,
                is_confirm: true,
                showCancelButton: true,
                icon: 'question'
            }, opt || {}));
        },
        danger: function(title, text, opt) {
            return this.fire(Object.assign({
                title: title,
                text: text,
                is_confirm: true,
                showCancelButton: true,
                icon: 'warning',
                confirm_button_color: '#dc2626',
                confirm_button_text: 'Yes, delete it!'
            }, opt || {}));
        }
    };

    var queue = {$jsonAlerts};
    if (Array.isArray(queue) && queue.length > 0) {
        var runNext = function(idx) {
            if (idx >= queue.length) return;
            window.BongoAlert.fire(queue[idx]).then(function() {
                runNext(idx + 1);
            });
        };
        runNext(0);
    }
})();
</script>
HTML;
    }
}
