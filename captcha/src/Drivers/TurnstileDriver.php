<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha\Drivers;

class TurnstileDriver implements CaptchaDriverInterface
{
    protected array $config;
    protected ?string $verifyUrl;

    public function __construct(array $config = [], ?string $verifyUrl = null)
    {
        $this->config = $config;
        $this->verifyUrl = $verifyUrl ?? 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    public function render(array $attributes = []): string
    {
        $siteKey = htmlspecialchars($this->config['site_key'] ?? '', ENT_QUOTES, 'UTF-8');
        $theme = htmlspecialchars($attributes['theme'] ?? $this->config['theme'] ?? 'auto', ENT_QUOTES, 'UTF-8');
        $size = htmlspecialchars($attributes['size'] ?? $this->config['size'] ?? 'normal', ENT_QUOTES, 'UTF-8');
        
        $class = 'cf-turnstile ' . ($attributes['class'] ?? '');
        $class = htmlspecialchars(trim($class), ENT_QUOTES, 'UTF-8');

        return "<div class=\"{$class}\" data-sitekey=\"{$siteKey}\" data-theme=\"{$theme}\" data-size=\"{$size}\"></div>";
    }

    public function script(): string
    {
        return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }

    public function inputName(): string
    {
        return 'cf-turnstile-response';
    }

    public function verify(?string $response = null, ?string $clientIp = null): bool
    {
        $response = $response ?? ($_POST[$this->inputName()] ?? '');
        if (empty($response)) {
            return false;
        }

        $secret = $this->config['secret_key'] ?? '';
        if (empty($secret)) {
            return false;
        }

        $postData = [
            'secret'   => $secret,
            'response' => $response,
        ];
        if (!empty($clientIp)) {
            $postData['remoteip'] = $clientIp;
        }

        $result = $this->post($this->verifyUrl, $postData);
        if ($result === null) {
            return false;
        }

        return !empty($result['success']);
    }

    protected function post(string $url, array $data): ?array
    {
        $payload = http_build_query($data);
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Content-Length: " . strlen($payload) . "\r\n" .
                             "User-Agent: Bongo-Captcha/1.0\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
