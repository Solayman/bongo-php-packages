<?php
declare(strict_types=1);

namespace Bongo\Packages\Toast;

use System\Http\Session;

class Toast
{
    protected static array $inMemoryToasts = [];

    /**
     * Flash a success toast message.
     */
    public static function success(string $message, ?string $title = null, array $options = []): self
    {
        return static::add('success', $message, $title, $options);
    }

    /**
     * Flash an error toast message.
     */
    public static function error(string $message, ?string $title = null, array $options = []): self
    {
        return static::add('error', $message, $title, $options);
    }

    /**
     * Flash a warning toast message.
     */
    public static function warning(string $message, ?string $title = null, array $options = []): self
    {
        return static::add('warning', $message, $title, $options);
    }

    /**
     * Flash an informational toast message.
     */
    public static function info(string $message, ?string $title = null, array $options = []): self
    {
        return static::add('info', $message, $title, $options);
    }

    /**
     * Add a toast with arbitrary type.
     */
    public static function add(string $type, string $message, ?string $title = null, array $options = []): self
    {
        $sessionKey = config('toast.session_key', 'bongo_toasts');
        $toast = [
            'id' => 'toast_' . bin2hex(random_bytes(4)),
            'type' => $type,
            'message' => $message,
            'title' => $title,
            'duration' => $options['duration'] ?? config('toast.duration', 4000),
            'dismissible' => $options['dismissible'] ?? config('toast.dismissible', true),
            'progress_bar' => $options['progress_bar'] ?? config('toast.progress_bar', true),
        ];

        static::$inMemoryToasts[] = $toast;

        // Store in session if session is active or available
        $existing = Session::get($sessionKey, []);
        if (!is_array($existing)) {
            $existing = [];
        }
        $existing[] = $toast;
        Session::put($sessionKey, $existing);

        return new static();
    }

    /**
     * Retrieve all queued/flashed toasts and clear session storage.
     */
    public static function all(): array
    {
        $sessionKey = config('toast.session_key', 'bongo_toasts');
        $fromSession = Session::get($sessionKey, []);
        if (!is_array($fromSession)) {
            $fromSession = [];
        }

        $all = array_merge(static::$inMemoryToasts, $fromSession);
        static::clear();

        // Unique by id
        $unique = [];
        foreach ($all as $item) {
            if (isset($item['id'])) {
                $unique[$item['id']] = $item;
            }
        }

        return array_values($unique);
    }

    /**
     * Check if any toasts are currently queued.
     */
    public static function has(): bool
    {
        $sessionKey = config('toast.session_key', 'bongo_toasts');
        $fromSession = Session::get($sessionKey, []);
        return !empty(static::$inMemoryToasts) || (!empty($fromSession) && is_array($fromSession));
    }

    /**
     * Clear all toasts from memory and session.
     */
    public static function clear(): void
    {
        static::$inMemoryToasts = [];
        $sessionKey = config('toast.session_key', 'bongo_toasts');
        Session::forget($sessionKey);
    }

    /**
     * Render the toast container, CSS styles, JavaScript runtime, and initial queued toasts.
     */
    public static function render(?string $position = null): string
    {
        $pos = $position ?? config('toast.position', 'top-right');
        $toasts = static::all();
        $jsonToasts = json_encode($toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $posStyles = match ($pos) {
            'top-left' => 'top: 20px; left: 20px;',
            'top-center' => 'top: 20px; left: 50%; transform: translateX(-50%);',
            'bottom-right' => 'bottom: 20px; right: 20px;',
            'bottom-left' => 'bottom: 20px; left: 20px;',
            'bottom-center' => 'bottom: 20px; left: 50%; transform: translateX(-50%);',
            default => 'top: 20px; right: 20px;',
        };

        return <<<HTML
<div id="bongo-toast-container" style="position: fixed; z-index: 99999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; max-width: 380px; width: 100%; {$posStyles}"></div>
<style>
.bongo-toast {
    pointer-events: auto;
    position: relative;
    overflow: hidden;
    padding: 14px 16px;
    border-radius: 8px;
    color: #fff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.4;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.25), 0 8px 10px -6px rgba(0,0,0,0.2);
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}
.bongo-toast.show {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.bongo-toast.hide {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}
.bongo-toast-success { background: #059669; border-left: 5px solid #047857; }
.bongo-toast-error   { background: #dc2626; border-left: 5px solid #b91c1c; }
.bongo-toast-warning { background: #d97706; border-left: 5px solid #b45309; }
.bongo-toast-info    { background: #2563eb; border-left: 5px solid #1d4ed8; }

.bongo-toast-content { flex: 1; min-width: 0; }
.bongo-toast-title { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
.bongo-toast-message { font-size: 13px; opacity: 0.95; word-break: break-word; }
.bongo-toast-close {
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.7);
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    margin-left: 6px;
}
.bongo-toast-close:hover { color: #fff; }
.bongo-toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(255,255,255,0.45);
    width: 100%;
}
</style>
<script>
(function() {
    var container = document.getElementById('bongo-toast-container');
    window.bongoToast = {
        show: function(toast) {
            var el = document.createElement('div');
            el.className = 'bongo-toast bongo-toast-' + (toast.type || 'info');
            
            var iconSvg = {
                success: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
                error: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
                warning: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                info: '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
            }[toast.type || 'info'];

            var html = '<div style="flex-shrink:0;">' + iconSvg + '</div>';
            html += '<div class="bongo-toast-content">';
            if (toast.title) {
                var safeTitle = document.createElement('div');
                safeTitle.textContent = toast.title;
                html += '<div class="bongo-toast-title">' + safeTitle.innerHTML + '</div>';
            }
            var safeMsg = document.createElement('div');
            safeMsg.textContent = toast.message;
            html += '<div class="bongo-toast-message">' + safeMsg.innerHTML + '</div>';
            html += '</div>';

            if (toast.dismissible !== false) {
                html += '<button class="bongo-toast-close" aria-label="Close">&times;</button>';
            }
            if (toast.progress_bar !== false && toast.duration > 0) {
                html += '<div class="bongo-toast-progress"></div>';
            }
            el.innerHTML = html;
            container.appendChild(el);

            setTimeout(function() { el.classList.add('show'); }, 10);

            var closeBtn = el.querySelector('.bongo-toast-close');
            if (closeBtn) {
                closeBtn.onclick = function() { removeToast(el); };
            }

            if (toast.duration > 0) {
                var progress = el.querySelector('.bongo-toast-progress');
                if (progress) {
                    progress.style.transition = 'width ' + toast.duration + 'ms linear';
                    setTimeout(function() { progress.style.width = '0%'; }, 20);
                }
                setTimeout(function() { removeToast(el); }, toast.duration);
            }

            function removeToast(elem) {
                elem.classList.remove('show');
                elem.classList.add('hide');
                setTimeout(function() { if (elem.parentNode) elem.parentNode.removeChild(elem); }, 300);
            }
        },
        success: function(msg, title, opt) { this.show(Object.assign({ type: 'success', message: msg, title: title }, opt || {})); },
        error: function(msg, title, opt) { this.show(Object.assign({ type: 'error', message: msg, title: title }, opt || {})); },
        warning: function(msg, title, opt) { this.show(Object.assign({ type: 'warning', message: msg, title: title }, opt || {})); },
        info: function(msg, title, opt) { this.show(Object.assign({ type: 'info', message: msg, title: title }, opt || {})); }
    };

    var initial = {$jsonToasts};
    if (Array.isArray(initial)) {
        initial.forEach(function(t) { window.bongoToast.show(t); });
    }
})();
</script>
HTML;
    }
}
