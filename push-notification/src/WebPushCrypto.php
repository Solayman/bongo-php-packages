<?php

declare(strict_types=1);

namespace Bongo\Packages\PushNotification;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Web Push cryptography with PHP's openssl extension only:
 * - RFC 8291 message encryption ("aes128gcm"): ECDH P-256 + HKDF-SHA256 + AES-128-GCM
 * - RFC 8292 VAPID: an ES256-signed JWT
 *
 * Keys are the raw, base64url forms used everywhere in Web Push: a P-256 public key is the
 * 65-byte uncompressed point (0x04 || X || Y), a private key the 32-byte scalar.
 */
class WebPushCrypto
{
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * Encrypt $plaintext for one subscription (keys from PushSubscription.toJSON()).
     * $asKeyPair and $salt are only given by tests (RFC 8291 test vectors).
     *
     * @param array{public: string, private: string}|null $asKeyPair raw keys
     */
    public static function encrypt(string $plaintext, string $uaPublic, string $authSecret, ?array $asKeyPair = null, ?string $salt = null): string
    {
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04") {
            throw new RuntimeException('Invalid subscription key p256dh.');
        }
        if (strlen($authSecret) !== 16) {
            throw new RuntimeException('Invalid subscription auth secret.');
        }
        if (strlen($plaintext) > 3993) {
            throw new RuntimeException('Web Push payloads are limited to about 4 KB.');
        }

        $asKeyPair ??= self::generateKeyPair();
        $salt ??= random_bytes(16);

        $ecdhSecret = openssl_pkey_derive(self::publicKey($uaPublic), self::privateKey($asKeyPair['private'], $asKeyPair['public']), 32);
        if ($ecdhSecret === false) {
            throw new RuntimeException('Key agreement failed.');
        }

        // RFC 8291 section 3.4
        $prkKey = hash_hmac('sha256', $ecdhSecret, $authSecret, true);
        $keyInfo = "WebPush: info\x00" . $uaPublic . $asKeyPair['public'];
        $ikm = hash_hmac('sha256', $keyInfo . "\x01", $prkKey, true);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        $tag = '';
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Payload encryption failed.');
        }

        // Header: salt (16) || record size (4) || key id length (1) || key id (the server's public key)
        return $salt . pack('N', 4096) . chr(65) . $asKeyPair['public'] . $cipher . $tag;
    }

    /** VAPID Authorization header value for an endpoint's origin */
    public static function vapidHeader(string $audience, string $subject, string $publicKey, string $privateKey, int $ttl = 43200): string
    {
        $segments = [
            self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256'])),
            self::base64UrlEncode((string) json_encode(['aud' => $audience, 'exp' => time() + min($ttl, 86400), 'sub' => $subject])),
        ];
        $der = '';
        if (!openssl_sign(implode('.', $segments), $der, self::privateKey($privateKey, $publicKey), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID signing failed (check WEBPUSH_PRIVATE_KEY / WEBPUSH_PUBLIC_KEY).');
        }
        $segments[] = self::base64UrlEncode(self::derToRaw($der));

        return 'vapid t=' . implode('.', $segments) . ', k=' . self::base64UrlEncode($publicKey);
    }

    /** @return array{public: string, private: string} a new raw P-256 key pair */
    public static function generateKeyPair(): array
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $key = @openssl_pkey_new($options);
        if ($key === false) {
            // PHP on Windows often has no openssl.cnf: use a minimal one
            $key = openssl_pkey_new($options + ['private_key_bits' => 2048, 'config' => self::minimalConfig()]);
        }
        if ($key === false) {
            throw new RuntimeException('Could not create a P-256 key: ' . (string) openssl_error_string());
        }
        $ec = openssl_pkey_get_details($key)['ec'];
        return [
            'public' => "\x04" . str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT),
            'private' => str_pad($ec['d'], 32, "\x00", STR_PAD_LEFT),
        ];
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url value.');
        }
        return $decoded;
    }

    private static function publicKey(string $point): OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::SPKI_PREFIX) . $point), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Invalid P-256 public key.');
        }
        return $key;
    }

    private static function privateKey(string $scalar, string $point): OpenSSLAsymmetricKey
    {
        if (strlen($scalar) !== 32 || strlen($point) !== 65) {
            throw new RuntimeException('Invalid P-256 key pair.');
        }
        // SEC1 ECPrivateKey: version 1, the scalar, the curve, the public point
        $der = hex2bin('30770201010420') . $scalar . hex2bin('a00a06082a8648ce3d030107a144034200') . $point;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Invalid P-256 private key.');
        }
        return $key;
    }

    /** DER ECDSA signature (SEQUENCE of two INTEGERs) to the raw 64-byte r || s that JWT needs */
    private static function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7f : 0);
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $value = ltrim(substr($der, $offset + 2, $length), "\x00");
            $parts[] = str_pad($value, 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }
        return $parts[0] . $parts[1];
    }

    private static function minimalConfig(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bongo-openssl.cnf';
        if (!is_file($path)) {
            file_put_contents($path, "[req]\ndistinguished_name = dn\n[dn]\n");
        }
        return $path;
    }
}
