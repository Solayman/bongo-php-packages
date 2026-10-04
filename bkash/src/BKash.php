<?php

declare(strict_types=1);

namespace Bongo\Packages\Bkash;

use InvalidArgumentException;
use RuntimeException;
use System\Database\DB;
use System\Events\Event;
use Throwable;

/**
 * bKash Payment Gateway Tokenized Checkout (PGW v1.2.0-beta) integration.
 * Zero external dependencies.
 */
class BKash
{
    protected string $appKey;
    protected string $appSecret;
    protected string $username;
    protected string $password;
    protected bool $sandbox;
    protected string $currency;
    protected string $intent;
    protected string $callbackUrl;

    protected static ?string $cachedToken = null;
    protected static ?int $tokenExpiresAt = null;

    /** @var callable|null Test mock transport: fn(string $url, string $method, array $data, array $headers): array */
    protected static $transportMock = null;

    public function __construct(?array $config = null)
    {
        $config ??= (array) (function_exists('config') ? config('bkash', []) : []);

        $this->appKey = (string) ($config['app_key'] ?? env('BKASH_APP_KEY', ''));
        $this->appSecret = (string) ($config['app_secret'] ?? env('BKASH_APP_SECRET', ''));
        $this->username = (string) ($config['username'] ?? env('BKASH_USERNAME', ''));
        $this->password = (string) ($config['password'] ?? env('BKASH_PASSWORD', ''));
        $this->sandbox = (bool) ($config['sandbox'] ?? env('BKASH_SANDBOX', true));
        $this->currency = (string) ($config['currency'] ?? 'BDT');
        $this->intent = (string) ($config['intent'] ?? 'sale');
        $this->callbackUrl = (string) ($config['callback_url'] ?? '/bkash/callback');
    }

    public function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    /**
     * Set a mock transport for unit testing without hitting real bKash endpoints.
     */
    public static function setMockTransport(?callable $transport): void
    {
        self::$transportMock = $transport;
        self::$cachedToken = null;
        self::$tokenExpiresAt = null;
    }

    /**
     * Grant or reuse an active ID token from bKash.
     */
    public function grantToken(): string
    {
        $now = time();
        if (self::$cachedToken !== null && self::$tokenExpiresAt !== null && self::$tokenExpiresAt > ($now + 60)) {
            return self::$cachedToken;
        }

        $this->ensureConfigured();

        $endpoint = $this->getBaseUrl() . '/tokenized/checkout/token/grant';
        $headers = [
            'username: ' . $this->username,
            'password: ' . $this->password,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $payload = [
            'app_key'    => $this->appKey,
            'app_secret' => $this->appSecret,
        ];

        $response = $this->request($endpoint, 'POST', $payload, $headers);

        if (!isset($response['id_token'])) {
            $msg = $response['statusMessage'] ?? $response['message'] ?? 'Failed to obtain bKash authorization token';
            throw new RuntimeException("bKash token grant error: $msg");
        }

        self::$cachedToken = (string) $response['id_token'];
        $expiresIn = (int) ($response['expires_in'] ?? 3600);
        self::$tokenExpiresAt = $now + $expiresIn;

        return self::$cachedToken;
    }

    /**
     * Initiate a payment session and obtain the redirect checkout URL.
     *
     * @param array $order Required keys: amount, invoice_no (or order_id)
     * @return array{status: string, gateway_url: string, payment_id: string, data: array}
     */
    public function pay(array $order): array
    {
        $this->ensureConfigured();

        $amount = (float) ($order['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Order amount must be greater than zero.');
        }

        $invoiceNo = (string) ($order['invoice_no'] ?? $order['order_id'] ?? ('INV-' . bin2hex(random_bytes(6))));
        $payerRef = (string) ($order['payer_reference'] ?? $order['customer_phone'] ?? 'Customer');
        $callback = (string) ($order['callback_url'] ?? $this->resolveCallbackUrl());

        $token = $this->grantToken();
        $endpoint = $this->getBaseUrl() . '/tokenized/checkout/create';
        $headers = [
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $payload = [
            'mode'                  => '0011',
            'payerReference'        => $payerRef,
            'callbackURL'           => $callback,
            'amount'                => number_format($amount, 2, '.', ''),
            'currency'              => $this->currency,
            'intent'                => $this->intent,
            'merchantInvoiceNumber' => $invoiceNo,
        ];

        $response = $this->request($endpoint, 'POST', $payload, $headers);

        $statusCode = (string) ($response['statusCode'] ?? '');
        $paymentId = (string) ($response['paymentID'] ?? '');
        $bkashUrl = (string) ($response['bkashURL'] ?? '');

        if ($statusCode === '0000' && $bkashUrl !== '') {
            $this->recordInitialTransaction($paymentId, $invoiceNo, $amount, $response);

            return [
                'status'      => 'SUCCESS',
                'gateway_url' => $bkashUrl,
                'payment_id'  => $paymentId,
                'data'        => $response,
            ];
        }

        $errorMsg = $response['statusMessage'] ?? 'bKash payment initiation failed';
        throw new RuntimeException("bKash create payment failed: $errorMsg");
    }

    /**
     * Verify and execute a payment upon return from bKash.
     *
     * @param array $params Query or POST parameters from bKash redirect (paymentID, status)
     * @return array{valid: bool, status: string, trx_id: ?string, payment_id: string, amount: float, raw: array}
     */
    public function verify(array $params): array
    {
        $paymentId = (string) ($params['paymentID'] ?? $params['payment_id'] ?? '');
        $status = strtolower((string) ($params['status'] ?? ''));

        if ($paymentId === '') {
            throw new InvalidArgumentException('Missing paymentID in bKash callback.');
        }

        if ($status === 'cancel') {
            $this->updateTransactionStatus($paymentId, 'CANCELLED', null, $params);
            if (class_exists(Event::class)) {
                Event::dispatch('payment.failed', ['gateway' => 'bkash', 'payment_id' => $paymentId, 'reason' => 'cancelled']);
            }
            return [
                'valid'      => false,
                'status'     => 'CANCELLED',
                'trx_id'     => null,
                'payment_id' => $paymentId,
                'amount'     => 0.0,
                'raw'        => $params,
            ];
        }

        if ($status === 'failure') {
            $this->updateTransactionStatus($paymentId, 'FAILED', null, $params);
            if (class_exists(Event::class)) {
                Event::dispatch('payment.failed', ['gateway' => 'bkash', 'payment_id' => $paymentId, 'reason' => 'failure']);
            }
            return [
                'valid'      => false,
                'status'     => 'FAILED',
                'trx_id'     => null,
                'payment_id' => $paymentId,
                'amount'     => 0.0,
                'raw'        => $params,
            ];
        }

        // Execute payment on bKash server to finalize transaction
        return $this->execute($paymentId);
    }

    /**
     * Server-side payment execution after customer approval.
     */
    public function execute(string $paymentId): array
    {
        $this->ensureConfigured();

        $token = $this->grantToken();
        $endpoint = $this->getBaseUrl() . '/tokenized/checkout/execute';
        $headers = [
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $payload = ['paymentID' => $paymentId];
        $response = $this->request($endpoint, 'POST', $payload, $headers);

        $statusCode = (string) ($response['statusCode'] ?? '');
        $trxStatus = (string) ($response['transactionStatus'] ?? '');
        $trxId = isset($response['trxID']) ? (string) $response['trxID'] : null;
        $amount = (float) ($response['amount'] ?? 0.0);

        if ($statusCode === '0000' && ($trxStatus === 'Completed' || $trxStatus === 'Authorized')) {
            $this->updateTransactionStatus($paymentId, 'COMPLETED', $trxId, $response);
            $eventPayload = [
                'gateway'         => 'bkash',
                'payment_id'      => $paymentId,
                'trx_id'          => $trxId,
                'amount'          => $amount,
                'currency'        => (string) ($response['currency'] ?? $this->currency),
                'customer_msisdn' => (string) ($response['customerMsisdn'] ?? ''),
                'raw'             => $response,
            ];
            if (class_exists(Event::class)) {
                Event::dispatch('payment.success', $eventPayload);
            }

            return [
                'valid'      => true,
                'status'     => 'COMPLETED',
                'trx_id'     => $trxId,
                'payment_id' => $paymentId,
                'amount'     => $amount,
                'raw'        => $response,
            ];
        }

        $this->updateTransactionStatus($paymentId, 'FAILED', $trxId, $response);
        if (class_exists(Event::class)) {
            Event::dispatch('payment.failed', [
                'gateway'    => 'bkash',
                'payment_id' => $paymentId,
                'reason'     => $response['statusMessage'] ?? 'Execution failed',
                'raw'        => $response,
            ]);
        }

        return [
            'valid'      => false,
            'status'     => 'FAILED',
            'trx_id'     => $trxId,
            'payment_id' => $paymentId,
            'amount'     => $amount,
            'raw'        => $response,
        ];
    }

    /**
     * Query status of an existing payment.
     */
    public function query(string $paymentId): array
    {
        $this->ensureConfigured();

        $token = $this->grantToken();
        $endpoint = $this->getBaseUrl() . '/tokenized/checkout/payment/status';
        $headers = [
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        return $this->request($endpoint, 'POST', ['paymentID' => $paymentId], $headers);
    }

    /**
     * Refund a completed transaction.
     */
    public function refund(
        string $paymentId,
        string $trxId,
        float $amount,
        string $reason = 'Customer Request',
        string $sku = 'default'
    ): array {
        $this->ensureConfigured();

        $token = $this->grantToken();
        $endpoint = $this->getBaseUrl() . '/tokenized/checkout/payment/refund';
        $headers = [
            'Authorization: ' . $token,
            'X-APP-Key: ' . $this->appKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $payload = [
            'paymentID' => $paymentId,
            'amount'    => number_format($amount, 2, '.', ''),
            'trxID'     => $trxId,
            'sku'       => $sku,
            'reason'    => $reason,
        ];

        return $this->request($endpoint, 'POST', $payload, $headers);
    }

    protected function resolveCallbackUrl(): string
    {
        if (str_starts_with($this->callbackUrl, 'http://') || str_starts_with($this->callbackUrl, 'https://')) {
            return $this->callbackUrl;
        }

        $appUrl = function_exists('url') ? url('/') : (env('APP_URL', 'http://127.0.0.1:8000'));
        return rtrim($appUrl, '/') . '/' . ltrim($this->callbackUrl, '/');
    }

    protected function ensureConfigured(): void
    {
        if ($this->appKey === '' || $this->appSecret === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException(
                "bKash credentials missing. Configure BKASH_APP_KEY, BKASH_APP_SECRET, BKASH_USERNAME, and BKASH_PASSWORD in .env"
            );
        }
    }

    protected function request(string $url, string $method, array $data, array $headers): array
    {
        if (self::$transportMock !== null) {
            return (array) call_user_func(self::$transportMock, $url, $method, $data, $headers);
        }

        $body = !empty($data) ? json_encode($data) : null;
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers) . "\r\n",
                'content'       => $body,
                'timeout'       => 25,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            $err = error_get_last()['message'] ?? 'Network error';
            throw new RuntimeException("bKash gateway connection failure ($url): $err");
        }

        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            throw new RuntimeException("bKash returned invalid JSON response: " . substr($raw, 0, 150));
        }

        return $parsed;
    }

    protected function recordInitialTransaction(string $paymentId, string $invoiceNo, float $amount, array $raw): void
    {
        try {
            DB::table('bkash_transactions')->insert([
                'payment_id'      => $paymentId,
                'trx_id'          => null,
                'amount'          => $amount,
                'currency'        => $this->currency,
                'status'          => 'INITIATED',
                'customer_msisdn' => null,
                'invoice_no'      => $invoiceNo,
                'raw_response'    => json_encode($raw),
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Non-fatal if table doesn't exist yet before migrations run
        }
    }

    protected function updateTransactionStatus(string $paymentId, string $status, ?string $trxId, array $raw): void
    {
        try {
            DB::table('bkash_transactions')->where('payment_id', $paymentId)->update([
                'status'          => $status,
                'trx_id'          => $trxId,
                'customer_msisdn' => $raw['customerMsisdn'] ?? null,
                'raw_response'    => json_encode($raw),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Non-fatal if table doesn't exist
        }
    }
}
