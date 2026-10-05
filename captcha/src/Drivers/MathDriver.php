<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha\Drivers;

class MathDriver implements CaptchaDriverInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function inputName(): string
    {
        return $this->config['session_key'] ?? 'bongo_captcha_math';
    }

    public function script(): string
    {
        return '';
    }

    /**
     * Generate a new math problem and store its answer in session.
     */
    public function generate(): array
    {
        $this->ensureSession();

        $min = (int) ($this->config['min'] ?? 1);
        $max = (int) ($this->config['max'] ?? 20);
        $operators = $this->config['operators'] ?? ['+', '-'];

        $op = $operators[array_rand($operators)];
        $num1 = random_int($min, $max);
        $num2 = random_int($min, $max);

        // Ensure positive subtraction result
        if ($op === '-' && $num1 < $num2) {
            [$num1, $num2] = [$num2, $num1];
        }

        switch ($op) {
            case '+':
                $answer = $num1 + $num2;
                break;
            case '-':
                $answer = $num1 - $num2;
                break;
            case '*':
                $answer = $num1 * $num2;
                break;
            default:
                $op = '+';
                $answer = $num1 + $num2;
        }

        $question = "$num1 $op $num2 = ?";
        $sessionKey = $this->inputName();

        $_SESSION[$sessionKey] = [
            'answer'     => (string) $answer,
            'created_at' => time(),
        ];

        return [
            'question' => $question,
            'answer'   => $answer,
        ];
    }

    public function render(array $attributes = []): string
    {
        $problem = $this->generate();
        $inputName = $this->inputName();
        $id = htmlspecialchars($attributes['id'] ?? $inputName, ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($attributes['class'] ?? 'bongo-captcha-input', ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars($attributes['placeholder'] ?? 'Your answer', ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($attributes['label'] ?? 'Security Question:', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="bongo-captcha-math-wrapper" style="margin-bottom: 1rem;">
    <label for="{$id}" style="display: block; font-weight: 600; margin-bottom: 0.35rem;">
        {$label} <span class="bongo-captcha-math-question" style="display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.06); border-radius: 4px; font-family: monospace;">{$problem['question']}</span>
    </label>
    <input type="number" name="{$inputName}" id="{$id}" class="{$class}" placeholder="{$placeholder}" required autocomplete="off" style="padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 4px; width: 120px;" />
</div>
HTML;
    }

    public function verify(?string $response = null, ?string $clientIp = null): bool
    {
        $this->ensureSession();

        $sessionKey = $this->inputName();
        $stored = $_SESSION[$sessionKey] ?? null;

        // Clear immediately to prevent replay attack
        unset($_SESSION[$sessionKey]);

        if (!is_array($stored) || !isset($stored['answer'], $stored['created_at'])) {
            return false;
        }

        // Expire after 10 minutes
        if (time() - $stored['created_at'] > 600) {
            return false;
        }

        $userResponse = trim((string) ($response ?? ($_POST[$sessionKey] ?? '')));
        if ($userResponse === '') {
            return false;
        }

        return hash_equals((string) $stored['answer'], $userResponse);
    }

    protected function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }
}
