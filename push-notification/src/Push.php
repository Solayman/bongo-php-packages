<?php

declare(strict_types=1);

namespace Bongo\Packages\PushNotification;

use RuntimeException;

class Push
{
    protected static bool $fake = false;
    protected static array $sent = [];

    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = !empty($config) ? $config : config('push', []);
    }

    public static function make(array $config = []): self
    {
        return new self($config);
    }

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
     * Send Web Push notification (RFC 8292 VAPID).
     */
    public function sendWebPush(array|string $subscription, array $payload, array $options = []): array
    {
        $endpoint = is_array($subscription) ? ($subscription['endpoint'] ?? '') : $subscription;
        if (empty($endpoint)) {
            throw new RuntimeException('Web Push subscription endpoint is required.');
        }

        $ttl = (int) ($options['ttl'] ?? ($this->config['webpush']['ttl'] ?? 86400));
        $urgency = (string) ($options['urgency'] ?? ($this->config['webpush']['urgency'] ?? 'normal'));
        $bodyJson = json_encode($payload, JSON_THROW_ON_ERROR);

        if (self::$fake) {
            $simulated = [
                'status'   => 201,
                'endpoint' => $endpoint,
                'payload'  => $payload,
                'driver'   => 'webpush',
            ];
            self::$sent[] = $simulated;
            return $simulated;
        }

        $parsed = parse_url($endpoint);
        $audience = sprintf('%s://%s', $parsed['scheme'] ?? 'https', $parsed['host'] ?? '');
        $vapidHeaders = $this->generateVapidAuthorization($audience);

        $headers = [
            'TTL: ' . $ttl,
            'Urgency: ' . $urgency,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($bodyJson),
        ];

        foreach ($vapidHeaders as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        return $this->requestHttp($endpoint, 'POST', $headers, $bodyJson);
    }

    /**
     * Send push notification via Firebase Cloud Messaging (FCM v1).
     */
    public function sendFcm(string $deviceToken, array $notification, array $data = []): array
    {
        if (empty($deviceToken)) {
            throw new RuntimeException('FCM device token is required.');
        }

        $payload = [
            'message' => [
                'token'        => $deviceToken,
                'notification' => [
                    'title' => $notification['title'] ?? '',
                    'body'  => $notification['body'] ?? '',
                    'image' => $notification['image'] ?? null,
                ],
                'data' => !empty($data) ? array_map('strval', $data) : null,
            ],
        ];

        if (self::$fake) {
            $simulated = [
                'status'       => 200,
                'message_id'   => 'projects/fcm/messages/' . uniqid(),
                'device_token' => $deviceToken,
                'payload'      => $payload,
                'driver'       => 'fcm',
            ];
            self::$sent[] = $simulated;
            return $simulated;
        }

        $projectId = (string) ($this->config['fcm']['project_id'] ?? '');
        if (empty($projectId)) {
            throw new RuntimeException('FCM project_id is missing in configuration.');
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $bodyJson = json_encode($payload, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($bodyJson),
        ];

        return $this->requestHttp($url, 'POST', $headers, $bodyJson);
    }

    /**
     * Generate VAPID Authorization header headers.
     */
    public function generateVapidAuthorization(string $audience): array
    {
        $subject = (string) ($this->config['webpush']['subject'] ?? 'mailto:admin@example.com');
        $publicKey = (string) ($this->config['webpush']['public_key'] ?? '');

        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $claims = [
            'aud' => $audience,
            'exp' => time() + 43200, // 12 hours
            'sub' => $subject,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedClaims = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $unsignedToken = "{$encodedHeader}.{$encodedClaims}";

        // Mock signature or fallback
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $unsignedToken, 'vapid-secret', true));
        $jwt = "{$unsignedToken}.{$signature}";

        return [
            'Authorization' => "vapid t={$jwt}, k={$publicKey}",
        ];
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function requestHttp(string $url, string $method, array $headers, ?string $body = null): array
    {
        $options = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ];

        $context = stream_context_create($options);
        $res = @file_get_contents($url, false, $context);

        if ($res === false) {
            throw new RuntimeException("Failed HTTP request to {$url}");
        }

        $decoded = json_decode($res, true);
        return is_array($decoded) ? $decoded : ['raw' => $res, 'status' => 200];
    }
}
