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
            'id'         => 'wh_' . bin2hex(random_bytes(12)),
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
        $pin = null;
        $refused = static::refusal($call['url'], $pin);
        if ($refused !== null) {
            return ['success' => false, 'status_code' => 0, 'body' => '', 'error' => $refused, 'call' => $call];
        }

        $headers = [];
        foreach ($call['headers'] as $k => $v) {
            // A CR/LF in a header name or value would let the caller inject extra headers
            $name = preg_replace('/[^A-Za-z0-9-]/', '', (string) $k);
            $headers[] = $name . ': ' . str_replace(["\r", "\n", "\0"], '', (string) $v);
        }

        $ch = curl_init($call['url']);
        $options = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) config('webhooks.timeout', 10),
            CURLOPT_SSL_VERIFYPEER => (bool) config('webhooks.verify_ssl', true),
            CURLOPT_FOLLOWLOCATION => false,
        ];
        // http(s) only: never file://, gopher://, dict://... (they could read local files or reach internal services)
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
        } elseif (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        // Connect to the address that was checked (a second DNS answer cannot point somewhere else)
        if ($pin !== null) {
            $options[CURLOPT_RESOLVE] = [$pin];
        }
        curl_setopt_array($ch, $options);

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

    /**
     * Why a URL may not be called, or null when it may.
     * Only http(s). With webhooks.block_private_networks (default on in production) the host must
     * not resolve to a private, loopback, link-local or reserved address (127.0.0.1, 10.x,
     * 169.254.169.254 cloud metadata...), so user-entered webhook URLs cannot reach internal services.
     */
    public static function refusal(string $url, ?string &$pin = null): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return 'Webhook URL must be http(s)://host/...';
        }

        $block = function_exists('config')
            ? (bool) config('webhooks.block_private_networks', env('APP_ENV', 'production') === 'production')
            : true;
        if (!$block) {
            return null;
        }

        $bare = trim($host, '[]');
        $ips = filter_var($bare, FILTER_VALIDATE_IP) ? [$bare] : static::resolve($bare);
        if ($ips === []) {
            return "Webhook host [$host] could not be resolved";
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return "Webhook host [$host] points to a private or reserved address";
            }
        }
        if (!filter_var($bare, FILTER_VALIDATE_IP)) {
            $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
            $pin = $bare . ':' . $port . ':' . (str_contains($ips[0], ':') ? '[' . $ips[0] . ']' : $ips[0]);
        }
        return null;
    }

    /** @return string[] IPv4 and IPv6 addresses of $host */
    protected static function resolve(string $host): array
    {
        $ips = [];
        foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
            if (!empty($record['ip'])) {
                $ips[] = $record['ip'];
            } elseif (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        if ($ips === []) {
            $ip = gethostbyname($host);
            if ($ip !== $host) {
                $ips[] = $ip;
            }
        }
        return array_values(array_unique($ips));
    }
}
