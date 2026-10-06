<?php

namespace Bongo\Packages\Webhooks;

class Webhook
{
    protected static bool $faking = false;
    protected static array $dispatched = [];

    public static function fake(): void
    {
        static::$faking = true;
        static::$dispatched = [];
    }

    public static function isFaking(): bool
    {
        return static::$faking;
    }

    public static function getDispatched(): array
    {
        return static::$dispatched;
    }

    public static function sign(string|array $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $payloadString = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : $payload;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payloadString}", $secret);
        return "t={$timestamp},v1={$signature}";
    }

    public static function verify(string|array $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $payloadString = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : $payload;

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            $kv = explode('=', trim($pair), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        if (!isset($parts['t']) || !isset($parts['v1'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];
        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false; // Signature expired
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payloadString}", $secret);
        return hash_equals($expected, $parts['v1']);
    }

    public static function dispatch(string $url, string $event, array $payload, ?string $secret = null, array $headers = []): array
    {
        $secret = $secret ?? (string) config('webhooks.default_secret', '');
        $timestamp = time();
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = $secret !== '' ? static::sign($jsonPayload, $secret, $timestamp) : '';

        $call = [
            'id'         => uniqid('wh_', true),
            'url'        => $url,
            'event'      => $event,
            'payload'    => $payload,
            'headers'    => array_merge([
                'Content-Type'      => 'application/json',
                'User-Agent'        => 'Bongo-Webhooks/1.0',
                'X-Bongo-Event'     => $event,
                'X-Bongo-Signature' => $signature,
                'X-Bongo-Timestamp' => (string) $timestamp,
            ], $headers),
            'timestamp'  => $timestamp,
        ];

        if (static::$faking) {
            static::$dispatched[] = $call;
            return [
                'success'     => true,
                'status_code' => 200,
                'body'        => '{"fake": true}',
                'error'       => null,
                'call'        => $call,
            ];
        }

        return static::sendHttp($call, $jsonPayload);
    }

    protected static function sendHttp(array $call, string $jsonPayload): array
    {
        $headers = [];
        foreach ($call['headers'] as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $ch = curl_init($call['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) config('webhooks.timeout', 10),
            CURLOPT_SSL_VERIFYPEER => (bool) config('webhooks.verify_ssl', true),
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch) ?: null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $success = ($status >= 200 && $status < 300) && $error === null;

        return [
            'success'     => $success,
            'status_code' => $status,
            'body'        => $body !== false ? $body : '',
            'error'       => $error,
            'call'        => $call,
        ];
    }
}
