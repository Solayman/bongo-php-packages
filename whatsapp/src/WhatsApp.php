<?php

declare(strict_types=1);

namespace Bongo\Packages\WhatsApp;

use RuntimeException;

class WhatsApp
{
    protected string $token;
    protected string $phoneNumberId;
    protected string $apiVersion;
    protected int $timeout;

    /** @var bool */
    protected static bool $fake = false;
    /** @var array */
    protected static array $sent = [];

    public function __construct(?string $token = null, ?string $phoneNumberId = null)
    {
        $this->token = $token ?? (string) config('whatsapp.token', '');
        $this->phoneNumberId = $phoneNumberId ?? (string) config('whatsapp.phone_number_id', '');
        $this->apiVersion = (string) config('whatsapp.api_version', 'v20.0');
        $this->timeout = (int) config('whatsapp.timeout', 15);
    }

    public static function make(?string $token = null, ?string $phoneNumberId = null): self
    {
        return new self($token, $phoneNumberId);
    }

    /**
     * Enable fake mode for tests.
     */
    public static function fake(): void
    {
        self::$fake = true;
        self::$sent = [];
    }

    public static function isFake(): bool
    {
        return self::$fake;
    }

    public static function getSent(): array
    {
        return self::$sent;
    }

    public static function clearSent(): void
    {
        self::$sent = [];
        self::$fake = false;
    }

    /**
     * Sanitize phone number to Meta's expected format (digits only, e.g. 8801712345678).
     */
    public function formatPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?? '';
    }

    /**
     * Send standard text message.
     */
    public function sendText(string $to, string $message, bool $previewUrl = false): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhone($to),
            'type'              => 'text',
            'text'              => [
                'preview_url' => $previewUrl,
                'body'        => $message,
            ],
        ];

        return $this->request('messages', $payload);
    }

    /**
     * Send pre-approved WhatsApp template message.
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language = 'en_US',
        array $components = []
    ): array {
        $templateData = [
            'name'     => $templateName,
            'language' => [
                'code' => $language,
            ],
        ];

        if (!empty($components)) {
            $templateData['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhone($to),
            'type'              => 'template',
            'template'          => $templateData,
        ];

        return $this->request('messages', $payload);
    }

    /**
     * Helper to send an authentication OTP template message.
     */
    public function sendOtp(
        string $to,
        string $code,
        string $templateName = 'auth_otp',
        string $language = 'en_US'
    ): array {
        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $code,
                    ],
                ],
            ],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $code,
                    ],
                ],
            ],
        ];

        return $this->sendTemplate($to, $templateName, $language, $components);
    }

    /**
     * Send media message (image, document, audio, video, sticker).
     */
    public function sendMedia(
        string $to,
        string $type,
        string $linkOrId,
        ?string $caption = null,
        ?string $filename = null
    ): array {
        $mediaObject = [];
        if (filter_var($linkOrId, FILTER_VALIDATE_URL)) {
            $mediaObject['link'] = $linkOrId;
        } else {
            $mediaObject['id'] = $linkOrId;
        }

        if ($caption !== null && in_array($type, ['image', 'video', 'document'], true)) {
            $mediaObject['caption'] = $caption;
        }

        if ($filename !== null && $type === 'document') {
            $mediaObject['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhone($to),
            'type'              => $type,
            $type               => $mediaObject,
        ];

        return $this->request('messages', $payload);
    }

    /**
     * Verify incoming webhook challenge during Meta webhook subscription setup.
     */
    public function verifyWebhook(array $queryParams, ?string $verifyToken = null): ?string
    {
        $token = $verifyToken ?? (string) config('whatsapp.verify_token', '');
        $mode = $queryParams['hub_mode'] ?? $queryParams['hub.mode'] ?? null;
        $receivedToken = $queryParams['hub_verify_token'] ?? $queryParams['hub.verify_token'] ?? null;
        $challenge = $queryParams['hub_challenge'] ?? $queryParams['hub.challenge'] ?? null;

        if ($mode === 'subscribe' && $receivedToken === $token && $challenge !== null) {
            return (string) $challenge;
        }

        return null;
    }

    /**
     * Verify Meta webhook payload signature (X-Hub-Signature-256).
     */
    public function verifySignature(string $payload, string $signatureHeader, ?string $appSecret = null): bool
    {
        $secret = $appSecret ?? (string) config('whatsapp.app_secret', '');
        if (empty($secret) || empty($signatureHeader)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Execute Meta Graph API HTTP request.
     */
    protected function request(string $endpoint, array $payload): array
    {
        if (self::$fake) {
            $simulated = [
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    ['input' => $payload['to'] ?? '', 'wa_id' => $payload['to'] ?? ''],
                ],
                'messages' => [
                    ['id' => 'wamid.' . uniqid()],
                ],
            ];
            self::$sent[] = [
                'endpoint' => $endpoint,
                'payload'  => $payload,
                'response' => $simulated,
            ];
            return $simulated;
        }

        if (empty($this->token) || empty($this->phoneNumberId)) {
            throw new RuntimeException('WhatsApp token or phone_number_id is missing.');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/%s',
            $this->apiVersion,
            $this->phoneNumberId,
            $endpoint
        );

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json),
                ],
                'content' => $json,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($options);
        $raw = @file_get_contents($url, false, $context);

        if ($raw === false) {
            throw new RuntimeException('Failed to connect to WhatsApp Cloud API.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from WhatsApp Cloud API: ' . $raw);
        }

        if (isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? 'Unknown WhatsApp API error';
            throw new RuntimeException('WhatsApp API Error: ' . $msg);
        }

        return $decoded;
    }
}
