<?php

declare(strict_types=1);

namespace Bongo\Packages\Nagad;

use InvalidArgumentException;
use RuntimeException;
use System\Database\DB;
use System\Events\Event;
use Throwable;

/**
 * Nagad Payment Gateway (PGW v-0.2) integration.
 * Zero external dependencies — pure PHP with native OpenSSL and cURL / streams.
 */
class Nagad
{
    protected string $merchantId;
    protected string $merchantPrivateKey;
    protected string $nagadPublicKey;
    protected bool $sandbox;
    protected string $currency;
    protected string $apiVersion;
    protected string $callbackUrl;

    /** @var callable|null Test mock transport: fn(string $url, string $method, array $data, array $headers): array */
    protected static $transportMock = null;

    public function __construct(?array $config = null)
    {
        $config ??= (array) (function_exists('config') ? config('nagad', []) : []);

        $this->merchantId = (string) ($config['merchant_id'] ?? env('NAGAD_MERCHANT_ID', ''));
        $this->merchantPrivateKey = (string) ($config['merchant_private_key'] ?? env('NAGAD_MERCHANT_PRIVATE_KEY', ''));
        $this->nagadPublicKey = (string) ($config['nagad_public_key'] ?? env('NAGAD_PUBLIC_KEY', ''));
        $this->sandbox = (bool) ($config['sandbox'] ?? env('NAGAD_SANDBOX', true));
        $this->currency = (string) ($config['currency'] ?? 'BDT');
        $this->apiVersion = (string) ($config['api_version'] ?? 'v-0.2');
        $this->callbackUrl = (string) ($config['callback_url'] ?? env('NAGAD_CALLBACK_URL', '/nagad/callback'));
    }

    /**
     * Get the base API endpoint for Nagad.
     */
    public function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs'
            : 'https://api.mynagad.com/api/dfs';
    }

    /**
     * Set a mock transport for unit testing without making network requests.
     */
    public static function setMockTransport(?callable $transport): void
    {
        self::$transportMock = $transport;
    }

    /**
     * Generate a new 2048-bit RSA keypair in PEM format.
     * Useful for sandbox testing and initial merchant key generation.
     *
     * @return array{private: string, public: string}
     */
    public static function generateKeyPair(int $bits = 2048): array
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException("OpenSSL extension is required for Nagad RSA operations.");
        }

        $configArgs = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $tempCnf = null;
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidates = [
                getenv('OPENSSL_CONF') ?: '',
                'C:/laragon/bin/git/mingw64/ssl/openssl.cnf',
                'C:/laragon/bin/apache/httpd-2.4.62-240904-win64-VS17/conf/openssl.cnf',
                'C:/laragon/bin/apache/httpd-2.4.54-win64-VS16/conf/openssl.cnf',
                dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
                dirname(PHP_BINARY) . '/openssl.cnf',
            ];
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && is_file($candidate)) {
                    $configArgs['config'] = $candidate;
                    break;
                }
            }

            if (!isset($configArgs['config'])) {
                $tempCnf = tempnam(sys_get_temp_dir(), 'cnf_');
                if ($tempCnf !== false) {
                    file_put_contents($tempCnf, "[req]\ndistinguished_name = req_dn\n[req_dn]\n");
                    $configArgs['config'] = $tempCnf;
                }
            }
        }

        try {
            $res = openssl_pkey_new($configArgs);
            if ($res === false) {
                throw new RuntimeException("Failed to generate RSA keypair via OpenSSL: " . openssl_error_string());
            }

            $privateKey = '';
            if (isset($configArgs['config'])) {
                openssl_pkey_export($res, $privateKey, null, $configArgs);
            } else {
                openssl_pkey_export($res, $privateKey);
            }

            $details = openssl_pkey_get_details($res);
            $publicKey = $details['key'] ?? '';

            return [
                'private' => $privateKey,
                'public'  => $publicKey,
            ];
        } finally {
            if ($tempCnf !== null && is_file($tempCnf)) {
                @unlink($tempCnf);
            }
        }
    }

    /**
     * Step 1 & 2: Initiate and complete checkout, returning redirect URL for customer payment.
     *
     * @param array $orderData Must contain 'order_id' and 'amount'
     * @return array Response including 'callBackUrl', 'payment_ref_id', 'order_id'
     */
    public function createPayment(array $orderData): array
    {
        $this->ensureConfigured();

        $orderId = (string) ($orderData['order_id'] ?? uniqid('ORD_'));
        $amount = (float) ($orderData['amount'] ?? 0);

        if ($amount <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        $dateTime = date('YmdHis');
        $challenge = bin2hex(random_bytes(16));
        $clientIp = $this->resolveClientIp();

        // ----------------------------------------------------
        // Step 1: Check-out Initialize
        // ----------------------------------------------------
        $initData = [
            'merchantId' => $this->merchantId,
            'datetime'   => $dateTime,
            'orderId'    => $orderId,
            'challenge'  => $challenge,
        ];

        $initPayload = [
            'dateTime'      => $dateTime,
            'sensitiveData' => $this->encryptSensitiveData($initData),
            'signature'     => $this->generateSignature(json_encode($initData, JSON_UNESCAPED_SLASHES)),
        ];

        $initEndpoint = $this->getBaseUrl() . "/check-out/initialize/{$this->merchantId}/{$orderId}";
        $headers = [
            'X-KM-Api-Version: ' . $this->apiVersion,
            'X-KM-IP-Add: ' . $clientIp,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $initResponse = $this->request($initEndpoint, 'POST', $initPayload, $headers);

        if (empty($initResponse['sensitiveData'])) {
            $msg = $initResponse['message'] ?? $initResponse['reason'] ?? 'Failed to initialize Nagad checkout';
            throw new RuntimeException("Nagad checkout initialization error: $msg");
        }

        $decryptedInit = $this->decryptSensitiveData((string) $initResponse['sensitiveData']);
        $paymentRefId = (string) ($decryptedInit['paymentReferenceId'] ?? '');
        $resChallenge = (string) ($decryptedInit['challenge'] ?? $challenge);

        if ($paymentRefId === '') {
            throw new RuntimeException("Nagad initialization did not return a paymentReferenceId.");
        }

        // ----------------------------------------------------
        // Step 2: Check-out Complete
        // ----------------------------------------------------
        $callbackUrl = $this->resolveCallbackUrl();
        $completeData = [
            'merchantId'   => $this->merchantId,
            'orderId'      => $orderId,
            'currencyCode' => '050', // 050 is BDT in ISO 4217 numeric format
            'amount'       => number_format($amount, 2, '.', ''),
            'challenge'    => $resChallenge,
        ];

        $completePayload = [
            'dateTime'      => $dateTime,
            'sensitiveData' => $this->encryptSensitiveData($completeData),
            'signature'     => $this->generateSignature(json_encode($completeData, JSON_UNESCAPED_SLASHES)),
            'merchantId'    => $this->merchantId,
            'orderId'       => $orderId,
            'currencyCode'  => '050',
            'amount'        => number_format($amount, 2, '.', ''),
            'challenge'     => $resChallenge,
            'callbackUrl'   => $callbackUrl,
        ];

        $completeEndpoint = $this->getBaseUrl() . "/check-out/complete/{$paymentRefId}";
        $completeResponse = $this->request($completeEndpoint, 'POST', $completePayload, $headers);

        $status = (string) ($completeResponse['status'] ?? '');
        $redirectUrl = (string) ($completeResponse['callBackUrl'] ?? '');

        if ($status !== 'Success' && $redirectUrl === '') {
            $msg = $completeResponse['message'] ?? $completeResponse['reason'] ?? 'Failed to complete Nagad checkout';
            throw new RuntimeException("Nagad checkout complete error: $msg");
        }

        // Record initiated transaction in database
        $this->recordTransaction([
            'order_id'       => $orderId,
            'payment_ref_id' => $paymentRefId,
            'amount'         => $amount,
            'currency'       => $this->currency,
            'status'         => 'INITIATED',
            'challenge'      => $challenge,
            'raw_response'   => json_encode($completeResponse),
        ]);

        return [
            'status'         => 'Success',
            'callBackUrl'    => $redirectUrl,
            'payment_ref_id' => $paymentRefId,
            'order_id'       => $orderId,
            'amount'         => $amount,
            'raw'            => $completeResponse,
        ];
    }

    /**
     * Verify payment status using the paymentReferenceId (Nagad's API is the only source of truth).
     *
     * The stored row changes under a lock and only along allowed paths (a SUCCESS is never undone),
     * the amount must match what was stored at createPayment(), and payment.success / payment.failed /
     * payment.cancelled fire once per real change, so refreshing the callback page never credits twice.
     */
    public function verifyPayment(string $paymentRefId): array
    {
        $this->ensureConfigured();
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $paymentRefId)) {
            throw new InvalidArgumentException('Invalid Nagad payment reference.');
        }

        $clientIp = $this->resolveClientIp();
        $endpoint = $this->getBaseUrl() . '/verify/payment/' . rawurlencode($paymentRefId);
        $headers = [
            'X-KM-Api-Version: ' . $this->apiVersion,
            'X-KM-IP-Add: ' . $clientIp,
            'Accept: application/json',
        ];

        $response = $this->request($endpoint, 'GET', [], $headers);

        $status = (string) ($response['status'] ?? 'Failed');
        $orderId = (string) ($response['orderId'] ?? '');
        $trxId = (string) ($response['issuerTransactionId'] ?? '');
        $amount = (float) ($response['amount'] ?? 0);
        $clientMobile = (string) ($response['clientMobileNo'] ?? '');

        $normalizedStatus = match ($status) {
            'Success'   => 'SUCCESS',
            'Cancelled' => 'CANCELLED',
            'Aborted'   => 'ABORTED',
            'OrderInitiated', 'Initiated', 'Ready', 'InProgress' => 'PENDING',
            default     => 'FAILED',
        };

        // The order this payment was created for, and the amount it must cover
        $stored = $this->storedTransaction($paymentRefId);
        if ($stored) {
            $orderId = (string) $stored->order_id;
            if ($normalizedStatus === 'SUCCESS' && abs((float) $stored->amount - $amount) > 0.009) {
                $normalizedStatus = 'AMOUNT_MISMATCH';
            }
        }
        $isValid = $normalizedStatus === 'SUCCESS';

        $changed = $normalizedStatus === 'PENDING' ? false : $this->transition(
            $paymentRefId,
            $isValid ? ['INITIATED', 'CANCELLED', 'ABORTED', 'FAILED'] : ['INITIATED'],
            $normalizedStatus,
            $trxId !== '' ? $trxId : null,
            $clientMobile !== '' ? $clientMobile : null,
            $response
        );

        // Events only for a real change of a stored payment (createPayment() stores it)
        if ($changed === true && class_exists(Event::class)) {
            if ($isValid) {
                Event::dispatch('payment.success', [
                    'gateway'        => 'nagad',
                    'order_id'       => $orderId,
                    'payment_ref_id' => $paymentRefId,
                    'issuer_trx_id'  => $trxId,
                    'amount'         => $amount,
                    'mobile'         => $clientMobile,
                    'raw'            => $response,
                ]);
            } elseif ($normalizedStatus === 'CANCELLED') {
                Event::dispatch('payment.cancelled', [
                    'gateway'        => 'nagad',
                    'payment_ref_id' => $paymentRefId,
                    'order_id'       => $orderId,
                    'raw'            => $response,
                ]);
            } else {
                Event::dispatch('payment.failed', [
                    'gateway'        => 'nagad',
                    'payment_ref_id' => $paymentRefId,
                    'order_id'       => $orderId,
                    'reason'         => $normalizedStatus === 'AMOUNT_MISMATCH' ? 'Paid amount does not match the order' : ($response['message'] ?? 'Payment validation failed'),
                    'raw'            => $response,
                ]);
            }
        }

        return [
            'valid'            => $isValid,
            'status'           => $normalizedStatus,
            'order_id'         => $orderId,
            'payment_ref_id'   => $paymentRefId,
            'issuer_trx_id'    => $trxId,
            'amount'           => $amount,
            'client_mobile_no' => $clientMobile,
            'raw'              => $response,
        ];
    }

    /**
     * Process return callback from Nagad customer redirect.
     * The "status" in the redirect is only a hint (anyone can open the URL): Nagad's API decides.
     */
    public function verify(array $callbackParams): array
    {
        $paymentRefId = (string) (
            $callbackParams['payment_ref_id'] ??
            $callbackParams['paymentReferenceId'] ??
            $callbackParams['payment_id'] ??
            ''
        );

        if ($paymentRefId === '') {
            return [
                'valid'   => false,
                'status'  => 'FAILED',
                'message' => 'Missing payment_ref_id in callback parameters',
                'raw'     => $callbackParams,
            ];
        }

        return $this->verifyPayment($paymentRefId);
    }

    /**
     * Encrypt sensitive payload using Nagad's RSA Public Key with PKCS1 padding.
     */
    public function encryptSensitiveData(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $publicKey = $this->loadPublicKey($this->nagadPublicKey);

        $details = openssl_pkey_get_details($publicKey);
        $maxLen = (int) (($details['bits'] ?? 2048) / 8) - 11;
        if ($maxLen <= 0) {
            $maxLen = 117;
        }

        $encrypted = '';
        $chunks = str_split($json, $maxLen);
        foreach ($chunks as $chunk) {
            $crypted = '';
            if (!openssl_public_encrypt($chunk, $crypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException("OpenSSL public key encryption failed: " . openssl_error_string());
            }
            $encrypted .= $crypted;
        }

        return base64_encode($encrypted);
    }

    /**
     * Decrypt sensitive payload using Merchant's RSA Private Key with PKCS1 padding.
     */
    public function decryptSensitiveData(string $base64Data): array
    {
        $cipherText = base64_decode($base64Data);
        $privateKey = $this->loadPrivateKey($this->merchantPrivateKey);

        $details = openssl_pkey_get_details($privateKey);
        $chunkSize = (int) (($details['bits'] ?? 2048) / 8);
        if ($chunkSize <= 0) {
            $chunkSize = 256;
        }

        $decrypted = '';
        $chunks = str_split($cipherText, $chunkSize);
        foreach ($chunks as $chunk) {
            $plain = '';
            if (!openssl_private_decrypt($chunk, $plain, $privateKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException("OpenSSL private key decryption failed: " . openssl_error_string());
            }
            $decrypted .= $plain;
        }

        $result = json_decode($decrypted, true);
        if (!is_array($result)) {
            throw new RuntimeException("Decrypted sensitive data could not be parsed as valid JSON: $decrypted");
        }

        return $result;
    }

    /**
     * Generate SHA256withRSA signature using Merchant's Private Key.
     */
    public function generateSignature(string $data): string
    {
        $privateKey = $this->loadPrivateKey($this->merchantPrivateKey);
        $signature = '';

        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException("OpenSSL signature generation failed: " . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Verify SHA256withRSA signature using Nagad's Public Key.
     */
    public function verifySignature(string $data, string $signature): bool
    {
        $publicKey = $this->loadPublicKey($this->nagadPublicKey);
        $rawSignature = base64_decode($signature);

        return openssl_verify($data, $rawSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Load RSA private key from path, PEM string, or base64.
     */
    public function loadPrivateKey(string $key): mixed
    {
        if (is_file($key)) {
            $key = (string) file_get_contents($key);
        }

        $key = trim($key);
        if (!str_contains($key, '-----BEGIN')) {
            $decoded = base64_decode($key, true);
            if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
                $key = $decoded;
            } else {
                $key = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
            }
        }

        $res = openssl_pkey_get_private($key);
        if ($res === false) {
            throw new RuntimeException("Invalid merchant private key provided for Nagad: " . openssl_error_string());
        }

        return $res;
    }

    /**
     * Load RSA public key from path, PEM string, or base64.
     */
    public function loadPublicKey(string $key): mixed
    {
        if (is_file($key)) {
            $key = (string) file_get_contents($key);
        }

        $key = trim($key);
        if (!str_contains($key, '-----BEGIN')) {
            $decoded = base64_decode($key, true);
            if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
                $key = $decoded;
            } else {
                $key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
            }
        }

        $res = openssl_pkey_get_public($key);
        if ($res === false) {
            throw new RuntimeException("Invalid Nagad public key provided: " . openssl_error_string());
        }

        return $res;
    }

    protected function resolveCallbackUrl(): string
    {
        if (str_starts_with($this->callbackUrl, 'http://') || str_starts_with($this->callbackUrl, 'https://')) {
            return $this->callbackUrl;
        }

        $appUrl = function_exists('url') ? url('/') : (env('APP_URL', 'http://127.0.0.1:8000'));
        return rtrim($appUrl, '/') . '/' . ltrim($this->callbackUrl, '/');
    }

    protected function resolveClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    protected function ensureConfigured(): void
    {
        if ($this->merchantId === '' || $this->merchantPrivateKey === '' || $this->nagadPublicKey === '') {
            throw new RuntimeException(
                "Nagad credentials missing. Configure NAGAD_MERCHANT_ID, NAGAD_MERCHANT_PRIVATE_KEY, and NAGAD_PUBLIC_KEY in .env"
            );
        }
    }

    /**
     * Dispatch HTTP request to Nagad API with mock intercept support.
     */
    protected function request(string $url, string $method, array $data, array $headers): array
    {
        if (self::$transportMock !== null) {
            return (array) call_user_func(self::$transportMock, $url, $method, $data, $headers);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException("Nagad network error: $error");
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid response received from Nagad API (HTTP $status): $raw");
        }

        return $decoded;
    }

    protected function recordTransaction(array $data): void
    {
        if (!class_exists(DB::class)) {
            return;
        }

        try {
            DB::table('nagad_transactions')->insert([
                'order_id'       => $data['order_id'],
                'payment_ref_id' => $data['payment_ref_id'],
                'amount'         => $data['amount'],
                'currency'       => $data['currency'] ?? 'BDT',
                'status'         => $data['status'] ?? 'INITIATED',
                'challenge'      => $data['challenge'] ?? null,
                'raw_response'   => $data['raw_response'] ?? null,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Table may not exist if migrations haven't run yet
        }
    }

    protected function storedTransaction(string $paymentRefId): ?object
    {
        try {
            return DB::table('nagad_transactions')->where('payment_ref_id', $paymentRefId)->first() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Change the stored status inside a row lock, only from one of $from.
     * true = changed, false = not allowed (e.g. already SUCCESS), null = no stored row.
     */
    protected function transition(
        string $paymentRefId,
        array $from,
        string $status,
        ?string $trxId,
        ?string $clientMobile,
        array $rawResponse
    ): ?bool {
        try {
            return DB::transaction(function () use ($paymentRefId, $from, $status, $trxId, $clientMobile, $rawResponse): ?bool {
                $row = DB::table('nagad_transactions')->where('payment_ref_id', $paymentRefId)->lockForUpdate()->first();
                if (!$row) {
                    return null;
                }
                if (!in_array((string) $row->status, $from, true)) {
                    return false;
                }

                $updates = [
                    'status'       => $status,
                    'raw_response' => json_encode($rawResponse),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ];
                if ($trxId !== null) {
                    $updates['issuer_trx_id'] = $trxId;
                }
                if ($clientMobile !== null) {
                    $updates['client_mobile_no'] = $clientMobile;
                }
                DB::table('nagad_transactions')->where('payment_ref_id', $paymentRefId)->update($updates);
                return true;
            });
        } catch (Throwable) {
            return null;
        }
    }
}
