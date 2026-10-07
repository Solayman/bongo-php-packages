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

        // The endpoint comes from the browser: only https on a public address, never an internal URL
        static::assertPushEndpoint($endpoint);

        $keys = is_array($subscription) ? (array) ($subscription['keys'] ?? []) : [];
        if (empty($keys['p256dh']) || empty($keys['auth'])) {
            throw new RuntimeException('Web Push subscription keys (p256dh, auth) are required to encrypt the payload.');
        }

        // RFC 8291: push services only deliver encrypted payloads
        $body = WebPushCrypto::encrypt(
            $bodyJson,
            WebPushCrypto::base64UrlDecode((string) $keys['p256dh']),
            WebPushCrypto::base64UrlDecode((string) $keys['auth'])
        );

        $parsed = parse_url($endpoint);
        $audience = 'https://' . ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');

        $headers = [
            'TTL: ' . max(0, $ttl),
            'Urgency: ' . (in_array($urgency, ['very-low', 'low', 'normal', 'high'], true) ? $urgency : 'normal'),
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($body),
        ];
        foreach ($this->generateVapidAuthorization($audience) as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        return $this->requestHttp($endpoint, 'POST', $headers, $body);
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
                'message_id'   => 'projects/fcm/messages/' . bin2hex(random_bytes(8)),
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

        if (!preg_match('/^[a-z0-9-]{1,64}$/', $projectId)) {
            throw new RuntimeException('Invalid FCM project_id.');
        }
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $bodyJson = json_encode($payload, JSON_THROW_ON_ERROR);

        $headers = [
            'Authorization: Bearer ' . $this->fcmAccessToken(),
            'Content-Type: application/json',
            'Content-Length: ' . strlen($bodyJson),
        ];

        return $this->requestHttp($url, 'POST', $headers, $bodyJson);
    }

    /**
     * VAPID Authorization header (RFC 8292): an ES256 JWT signed with WEBPUSH_PRIVATE_KEY.
     * Create the key pair once with Push::generateVapidKeys().
     */
    public function generateVapidAuthorization(string $audience): array
    {
        $subject = (string) ($this->config['webpush']['subject'] ?? 'mailto:admin@example.com');
        $publicKey = (string) ($this->config['webpush']['public_key'] ?? '');
        $privateKey = (string) ($this->config['webpush']['private_key'] ?? '');
        if ($publicKey === '' || $privateKey === '') {
            throw new RuntimeException('Set WEBPUSH_PUBLIC_KEY and WEBPUSH_PRIVATE_KEY (create them with Push::generateVapidKeys()).');
        }

        return [
            'Authorization' => WebPushCrypto::vapidHeader(
                $audience,
                $subject,
                WebPushCrypto::base64UrlDecode($publicKey),
                WebPushCrypto::base64UrlDecode($privateKey)
            ),
        ];
    }

    /** A new VAPID key pair (base64url) for WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY */
    public static function generateVapidKeys(): array
    {
        $pair = WebPushCrypto::generateKeyPair();
        return [
            'public_key' => WebPushCrypto::base64UrlEncode($pair['public']),
            'private_key' => WebPushCrypto::base64UrlEncode($pair['private']),
        ];
    }

    /** Push endpoints must be https URLs on public addresses (the browser supplies them) */
    public static function assertPushEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $host === '') {
            throw new RuntimeException('Web Push endpoints must be https URLs.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_filter([gethostbyname($host)], fn($ip) => $ip !== $host);
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Web Push endpoint points to a private or reserved address.');
            }
        }
    }

    /**
     * OAuth2 access token for FCM v1 from the service account (RS256 JWT, cached until it expires).
     */
    protected function fcmAccessToken(): string
    {
        $email = (string) ($this->config['fcm']['client_email'] ?? '');
        $privateKey = str_replace('\n', "\n", (string) ($this->config['fcm']['private_key'] ?? ''));
        if ($email === '' || $privateKey === '') {
            throw new RuntimeException('Set FCM_CLIENT_EMAIL and FCM_PRIVATE_KEY (from the Firebase service account JSON).');
        }

        $cacheKey = 'push:fcm_token:' . sha1($email);
        if (function_exists('cache') || class_exists(\System\Cache\Cache::class)) {
            try {
                $cached = \System\Cache\Cache::get($cacheKey);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // no cache
            }
        }

        $now = time();
        $segments = [
            WebPushCrypto::base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            WebPushCrypto::base64UrlEncode((string) json_encode([
                'iss' => $email,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];
        $signature = '';
        if (!openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM_PRIVATE_KEY is not a valid RSA private key.');
        }
        $assertion = implode('.', $segments) . '.' . WebPushCrypto::base64UrlEncode($signature);

        $body = http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]);
        $response = $this->requestHttp('https://oauth2.googleapis.com/token', 'POST', ['Content-Type: application/x-www-form-urlencoded'], $body);
        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('FCM authentication failed: ' . (string) ($response['error_description'] ?? $response['error'] ?? 'no access token'));
        }

        try {
            \System\Cache\Cache::put($cacheKey, $token, max(60, (int) ($response['expires_in'] ?? 3600) - 300));
        } catch (\Throwable $e) {
            // no cache
        }
        return $token;
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
