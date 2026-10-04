<?php

namespace Bongo\Packages\Sslcommerz;

use RuntimeException;
use System\Database\DB;

/**
 * Official Bongo SSLCommerz Payment Gateway Integration.
 * Zero external dependencies. Uses native PHP streams/cURL and Bongo architecture.
 */
class SSLCommerz
{
    protected string $storeId;
    protected string $storePassword;
    protected bool $sandbox;
    protected string $currency;
    protected string $successUrl;
    protected string $failUrl;
    protected string $cancelUrl;
    protected string $ipnUrl;
    protected bool $logTransactions;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (function_exists('config') ? config('sslcommerz', []) : []);

        $this->storeId = (string) ($cfg['store_id'] ?? env('SSLCOMMERZ_STORE_ID', ''));
        $this->storePassword = (string) ($cfg['store_password'] ?? env('SSLCOMMERZ_STORE_PASSWORD', ''));
        $this->sandbox = (bool) ($cfg['sandbox'] ?? env('SSLCOMMERZ_SANDBOX', true));
        $this->currency = (string) ($cfg['currency'] ?? 'BDT');
        $this->successUrl = (string) ($cfg['success_url'] ?? '/sslcommerz/success');
        $this->failUrl = (string) ($cfg['fail_url'] ?? '/sslcommerz/fail');
        $this->cancelUrl = (string) ($cfg['cancel_url'] ?? '/sslcommerz/cancel');
        $this->ipnUrl = (string) ($cfg['ipn_url'] ?? '/sslcommerz/ipn');
        $this->logTransactions = (bool) ($cfg['log_transactions'] ?? true);
    }

    /**
     * Initiate a payment session and retrieve the gateway redirect URL.
     *
     * @param array $params Required: amount, tran_id, customer_name, customer_email, customer_phone
     * @return array ['status' => 'SUCCESS', 'gateway_url' => string, 'session_key' => string, ...]
     */
    public function initiate(array $params): array
    {
        $this->assertCredentials();

        if (empty($params['amount']) || (float) $params['amount'] <= 0) {
            throw new RuntimeException("Invalid payment amount specified for SSLCommerz.");
        }

        $tranId = (string) ($params['tran_id'] ?? ('TXN_' . uniqid()));
        $amount = (float) $params['amount'];
        $currency = (string) ($params['currency'] ?? $this->currency);

        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'tran_id' => $tranId,
            'success_url' => $this->absoluteUrl($this->successUrl),
            'fail_url' => $this->absoluteUrl($this->failUrl),
            'cancel_url' => $this->cancelUrl ? $this->absoluteUrl($this->cancelUrl) : $this->absoluteUrl($this->failUrl),
            'ipn_url' => $this->ipnUrl ? $this->absoluteUrl($this->ipnUrl) : '',
            // Customer Info
            'cus_name' => $params['customer_name'] ?? 'Customer',
            'cus_email' => $params['customer_email'] ?? 'customer@example.com',
            'cus_add1' => $params['customer_address'] ?? 'Dhaka',
            'cus_city' => $params['customer_city'] ?? 'Dhaka',
            'cus_country' => $params['customer_country'] ?? 'Bangladesh',
            'cus_phone' => $params['customer_phone'] ?? '01700000000',
            // Product info
            'product_name' => $params['product_name'] ?? 'Order Payment',
            'product_category' => $params['product_category'] ?? 'General',
            'product_profile' => $params['product_profile'] ?? 'general',
            'shipping_method' => 'NO',
            'num_of_item' => 1,
        ];

        // Merge any optional parameters (e.g. cus_postcode, emi_option)
        foreach ($params as $k => $v) {
            if (!isset($payload[$k]) && is_scalar($v)) {
                $payload[$k] = $v;
            }
        }

        $endpoint = $this->getBaseUrl() . '/gwprocess/v4/api.php';
        $response = $this->postHttp($endpoint, $payload);

        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new RuntimeException("SSLCommerz gateway returned invalid response: " . substr($response, 0, 200));
        }

        if (($json['status'] ?? '') !== 'SUCCESS' || empty($json['GatewayPageURL'])) {
            $reason = $json['failedreason'] ?? ($json['status'] ?? 'Unknown gateway error');
            throw new RuntimeException("SSLCommerz initialization failed: $reason");
        }

        if ($this->logTransactions) {
            $this->recordInitialTransaction($tranId, $amount, $currency, $params, $json);
        }

        return [
            'status' => 'SUCCESS',
            'gateway_url' => $json['GatewayPageURL'],
            'session_key' => $json['sessionkey'] ?? '',
            'tran_id' => $tranId,
            'raw' => $json,
        ];
    }

    /**
     * Server-side callback verification using val_id.
     * Prevents client-side manipulation of payment status or amounts.
     *
     * @param array $callbackData Array received in POST/GET from SSLCommerz (e.g. $_POST)
     * @return array Verified transaction details
     */
    public function verify(array $callbackData): array
    {
        $this->assertCredentials();

        $valId = (string) ($callbackData['val_id'] ?? '');
        $tranId = (string) ($callbackData['tran_id'] ?? '');

        if ($valId === '') {
            throw new RuntimeException("Missing val_id in SSLCommerz payment callback.");
        }

        $query = http_build_query([
            'val_id' => $valId,
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'format' => 'json',
        ]);

        $endpoint = $this->getBaseUrl() . '/validator/api/validationserverAPI.php?' . $query;
        $response = $this->getHttp($endpoint);

        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new RuntimeException("Invalid validation response from SSLCommerz.");
        }

        $status = strtoupper((string) ($json['status'] ?? ''));
        $isValid = ($status === 'VALID' || $status === 'VALIDATED');

        $result = [
            'valid' => $isValid,
            'status' => $status,
            'tran_id' => $json['tran_id'] ?? $tranId,
            'val_id' => $valId,
            'amount' => (float) ($json['amount'] ?? ($callbackData['amount'] ?? 0)),
            'currency' => $json['currency'] ?? ($callbackData['currency'] ?? 'BDT'),
            'bank_tran_id' => $json['bank_tran_id'] ?? '',
            'card_type' => $json['card_type'] ?? '',
            'card_brand' => $json['card_brand'] ?? '',
            'card_issuer' => $json['card_issuer'] ?? '',
            'card_no' => $json['card_no'] ?? '',
            'raw' => $json,
        ];

        if ($this->logTransactions && !empty($result['tran_id'])) {
            $this->updateTransactionStatus($result['tran_id'], $result);
        }

        // Dispatch Bongo framework events if available
        if (class_exists('System\\Events\\Event')) {
            $event = $isValid ? 'payment.success' : 'payment.failed';
            \System\Events\Event::dispatch($event, $result);
        }

        return $result;
    }

    /**
     * Query transaction status by merchant transaction ID.
     */
    public function queryByTransactionId(string $tranId): array
    {
        $this->assertCredentials();

        $query = http_build_query([
            'tran_id' => $tranId,
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'format' => 'json',
        ]);

        $endpoint = $this->getBaseUrl() . '/validator/api/merchantTransIDvalidationAPI.php?' . $query;
        $response = $this->getHttp($endpoint);

        return json_decode($response, true) ?: [];
    }

    /**
     * Request a refund for a previously validated transaction.
     */
    public function refund(string $bankTranId, float $amount, string $reason = ''): array
    {
        $this->assertCredentials();

        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'bank_tran_id' => $bankTranId,
            'refund_amount' => number_format($amount, 2, '.', ''),
            'refund_remarks' => $reason ?: 'Merchant refund',
            'format' => 'json',
        ];

        $endpoint = $this->getBaseUrl() . '/validator/api/merchantTransIDvalidationAPI.php';
        $response = $this->postHttp($endpoint, $payload);

        return json_decode($response, true) ?: [];
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    protected function assertCredentials(): void
    {
        if ($this->storeId === '' || $this->storePassword === '') {
            throw new RuntimeException(
                "SSLCommerz credentials missing. Set SSLCOMMERZ_STORE_ID and SSLCOMMERZ_STORE_PASSWORD in your .env file."
            );
        }
    }

    protected function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $appUrl = function_exists('config') ? (string) config('app.url', 'http://127.0.0.1:8000') : 'http://127.0.0.1:8000';
        return rtrim($appUrl, '/') . '/' . ltrim($url, '/');
    }

    protected function postHttp(string $url, array $data): string
    {
        $postData = http_build_query($data);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                            "Content-Length: " . strlen($postData) . "\r\n" .
                            "User-Agent: Bongo-SSLCommerz/1.0.0\r\n",
                'content' => $postData,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException("Could not connect to SSLCommerz gateway at $url.");
        }

        return $response;
    }

    protected function getHttp(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Bongo-SSLCommerz/1.0.0\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException("Could not connect to SSLCommerz verification API at $url.");
        }

        return $response;
    }

    protected function recordInitialTransaction(string $tranId, float $amount, string $currency, array $params, array $response): void
    {
        try {
            if (class_exists('System\\Database\\DB')) {
                DB::table('sslcommerz_transactions')->insert([
                    'tran_id' => $tranId,
                    'val_id' => null,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'pending',
                    'customer_name' => substr((string) ($params['customer_name'] ?? ''), 0, 150),
                    'customer_email' => substr((string) ($params['customer_email'] ?? ''), 0, 150),
                    'customer_phone' => substr((string) ($params['customer_phone'] ?? ''), 0, 50),
                    'raw_response' => json_encode($response, JSON_UNESCAPED_SLASHES),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            // Silently ignore if table doesn't exist yet before migrations run
        }
    }

    protected function updateTransactionStatus(string $tranId, array $data): void
    {
        try {
            if (class_exists('System\\Database\\DB')) {
                DB::table('sslcommerz_transactions')
                    ->where('tran_id', $tranId)
                    ->update([
                        'val_id' => $data['val_id'] ?? null,
                        'status' => strtolower($data['status']),
                        'card_type' => substr((string) ($data['card_type'] ?? ''), 0, 50),
                        'bank_tran_id' => substr((string) ($data['bank_tran_id'] ?? ''), 0, 100),
                        'card_brand' => substr((string) ($data['card_brand'] ?? ''), 0, 50),
                        'raw_response' => json_encode($data['raw'] ?? [], JSON_UNESCAPED_SLASHES),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        } catch (\Throwable $e) {
            // Silently ignore if table doesn't exist yet
        }
    }
}
