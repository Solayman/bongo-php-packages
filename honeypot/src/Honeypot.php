<?php

declare(strict_types=1);

namespace Bongo\Packages\Honeypot;

use Bongo\Packages\Honeypot\Exceptions\SpamDetectedException;
use Stringable;

class Honeypot implements Stringable
{
    protected static bool $faked = false;

    protected string $decoyField = 'my_name';
    protected string $timeField = '_hp_time';
    protected int $minSeconds = 2;
    protected int $maxSeconds = 86400;
    protected bool $enabled = true;

    public function __construct()
    {
        if (function_exists('config')) {
            $this->decoyField = (string) config('honeypot.decoy_field', 'my_name');
            $this->timeField = (string) config('honeypot.time_field', '_hp_time');
            $this->minSeconds = (int) config('honeypot.min_seconds', 2);
            $this->maxSeconds = (int) config('honeypot.max_seconds', 86400);
            $this->enabled = (bool) config('honeypot.enabled', true);
        }
    }

    public static function instance(): static
    {
        return new static();
    }

    public static function fake(): void
    {
        static::$faked = true;
    }

    public static function unfake(): void
    {
        static::$faked = false;
    }

    public static function isFaked(): bool
    {
        return static::$faked;
    }

    public function decoyField(): string
    {
        return $this->decoyField;
    }

    public function timeField(): string
    {
        return $this->timeField;
    }

    public function minSeconds(): int
    {
        return $this->minSeconds;
    }

    public function setMinSeconds(int $seconds): static
    {
        $this->minSeconds = max(0, $seconds);
        return $this;
    }

    /**
     * Generate the signed timestamp token for the current time.
     */
    public function generateToken(?int $time = null): string
    {
        $t = $time ?? time();
        $secret = $this->secretKey();
        $hmac = hash_hmac('sha256', (string) $t, $secret);
        return base64_encode("{$t}:{$hmac}");
    }

    /**
     * Render invisible honeypot HTML input fields.
     */
    public function render(): string
    {
        if (!$this->enabled) {
            return '';
        }

        $token = $this->generateToken();
        $decoy = htmlspecialchars($this->decoyField, ENT_QUOTES, 'UTF-8');
        $timeF = htmlspecialchars($this->timeField, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="display:none !important; visibility:hidden !important; position:absolute !important; left:-9999px !important; opacity:0 !important;" aria-hidden="true">
    <label for="{$decoy}">Do not fill this field</label>
    <input type="text" name="{$decoy}" id="{$decoy}" value="" tabindex="-1" autocomplete="off">
    <input type="hidden" name="{$timeF}" value="{$token}">
</div>
HTML;
    }

    /**
     * Validate incoming request or array of submitted form data.
     */
    public function validate(?array $data = null): HoneypotResult
    {
        if (static::$faked || !$this->enabled) {
            return new HoneypotResult(true, null, 10);
        }

        $input = $data ?? static::resolveInput();

        // 1. Check if decoy honeypot field was filled
        if (isset($input[$this->decoyField]) && trim((string) $input[$this->decoyField]) !== '') {
            return new HoneypotResult(false, 'decoy_filled', 0);
        }

        // 2. Check signed timestamp field
        $token = $input[$this->timeField] ?? null;
        if (!is_string($token) || trim($token) === '') {
            return new HoneypotResult(false, 'invalid_signature', 0);
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return new HoneypotResult(false, 'invalid_signature', 0);
        }

        [$renderedTime, $providedHmac] = explode(':', $decoded, 2);
        if (!is_numeric($renderedTime)) {
            return new HoneypotResult(false, 'invalid_signature', 0);
        }

        $renderedTime = (int) $renderedTime;
        $expectedHmac = hash_hmac('sha256', (string) $renderedTime, $this->secretKey());

        if (!hash_equals($expectedHmac, $providedHmac)) {
            return new HoneypotResult(false, 'invalid_signature', 0);
        }

        $now = time();
        $elapsed = $now - $renderedTime;

        // 3. Check if submitted unrealistically fast (bots)
        if ($this->minSeconds > 0 && $elapsed < $this->minSeconds) {
            return new HoneypotResult(false, 'too_fast', max(0, $elapsed));
        }

        // 4. Check if token expired
        if ($this->maxSeconds > 0 && $elapsed > $this->maxSeconds) {
            return new HoneypotResult(false, 'expired', $elapsed);
        }

        return new HoneypotResult(true, null, max(0, $elapsed));
    }

    /**
     * Static helper for check.
     */
    public static function check(?array $data = null): HoneypotResult
    {
        return static::instance()->validate($data);
    }

    /**
     * Static helper returning bool.
     */
    public static function isValid(?array $data = null): bool
    {
        return static::check($data)->isValid();
    }

    /**
     * Guard that throws SpamDetectedException if invalid.
     */
    public static function guard(?array $data = null): void
    {
        $res = static::check($data);
        if ($res->isSpam()) {
            throw new SpamDetectedException($res->message(), $res->reason() ?? 'spam');
        }
    }

    public function __toString(): string
    {
        return $this->render();
    }

    protected function secretKey(): string
    {
        if (function_exists('config') && config('app.key')) {
            return (string) config('app.key');
        }

        if (isset($_ENV['APP_KEY']) && $_ENV['APP_KEY'] !== '') {
            return (string) $_ENV['APP_KEY'];
        }

        return 'bongo_default_honeypot_secret_key';
    }

    protected static function resolveInput(): array
    {
        if (function_exists('request') && request()) {
            return request()->all();
        }

        return $_POST;
    }
}
