<?php

declare(strict_types=1);

namespace Bongo\Packages\Aamarpay;

use RuntimeException;
use System\Database\DB;

/**
 * Official Bongo AamarPay Payment Gateway Integration.
 * Zero external dependencies. Uses native PHP streams/cURL.
 */
class AamarPay
{
    protected string $storeId;
    protected string $signatureKey;
    protected bool $sandbox;
    protected string $currency;
    protected string $successUrl;
    protected string $failUrl;
    protected string $cancelUrl;
    protected bool $logTransactions;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (function_exists('config') ? config('aamarpay', []) : []);

        $this->storeId = (string) ($cfg['store_id'] ?? env('AAMARPAY_STORE_ID', 'aamarpaytest'));
        $this->signatureKey = (string) ($cfg['signature_key'] ?? env('AAMARPAY_SIGNATURE_KEY', '28c78bb1f45112f5a40b956f9d102abb'));
        $this->sandbox = (bool) ($cfg['sandbox'] ?? env('AAMARPAY_SANDBOX', true));
        $this->currency = (string) ($cfg['currency'] ?? 'BDT');
        $this->successUrl = (string) ($cfg['success_url'] ?? '/aamarpay/success');
        $this->failUrl = (string) ($cfg['fail_url'] ?? '/aamarpay/fail');
        $this->cancelUrl = (string) ($cfg['cancel_url'] ?? '/aamarpay/cancel');
        $this->logTransactions = (bool) ($cfg['log_transactions'] ?? true);
    }

    /**
     * Get base gateway URL depending on sandbox mode.
     */
    public function getBaseUrl(): string
    {
        return $this->sandbox ? 'https://sandbox.aamarpay.com' : 'https://secure.aamarpay.com';
    }

    /**
     * Initiate a payment session with AamarPay and get payment URL.
     *
     * @param array $params Required: amount, tran_id, customer_name, customer_email, customer_phone
     */
    public function initiate(array $params): array
    {
        $this->assertCredentials();

        if (empty($params['amount']) || (float) $params['amount'] <= 0) {
            throw new RuntimeException("Invalid payment amount specified for AamarPay.");
        }

        $tranId = (string) ($params['tran_id'] ?? ('AP_' . bin2hex(random_bytes(8))));
        $amount = number_format((float) $params['amount'], 2, '.', '');
        $currency = (string) ($params['currency'] ?? $this->currency);

        $payload = [
            'store_id'      => $this->storeId,
            'signature_key' => $this->signatureKey,
            'tran_id'       => $tranId,
            'amount'        => $amount,
            'currency'      => $currency,
            'desc'          => (string) ($params['desc'] ?? 'Order Payment ' . $tranId),
            'cus_name'      => (string) ($params['customer_name'] ?? 'Customer'),
            'cus_email'     => (string) ($params['customer_email'] ?? 'customer@example.com'),
            'cus_phone'     => (string) ($params['customer_phone'] ?? '01700000000'),
            'cus_add1'      => (string) ($params['customer_address'] ?? 'Dhaka'),
            'cus_city'      => (string) ($params['customer_city'] ?? 'Dhaka'),
            'cus_country'   => (string) ($params['customer_country'] ?? 'Bangladesh'),
            'success_url'   => $this->absoluteUrl($this->successUrl),
            'fail_url'      => $this->absoluteUrl($this->failUrl),
            'cancel_url'    => $this->absoluteUrl($this->cancelUrl),
            'type'          => 'json',
        ];

        if (!empty($params['opt_a'])) {
            $payload['opt_a'] = (string) $params['opt_a'];
        }
        if (!empty($params['opt_b'])) {
            $payload['opt_b'] = (string) $params['opt_b'];
        }

        $url = $this->getBaseUrl() . '/jsonpost.php';
        $response = $this->postJson($url, $payload);

        // AamarPay returns json with payment_url or raw redirect string
        $paymentUrl = null;
        if (is_array($response)) {
            $paymentUrl = $response['payment_url'] ?? null;
        } elseif (is_string($response) && str_starts_with($response, 'http')) {
            $paymentUrl = trim($response);
        }

        if (empty($paymentUrl)) {
            $errorMsg = is_array($response) && isset($response['errors'])
                ? json_encode($response['errors'])
                : (is_string($response) ? $response : 'Failed to retrieve payment redirect URL from AamarPay.');
            throw new RuntimeException("AamarPay payment initiation failed: " . $errorMsg);
        }

        if ($this->logTransactions) {
            $this->logTransaction([
                'tran_id'         => $tranId,
                'amount'          => $amount,
                'currency'        => $currency,
                'status'          => 'INITIATED',
                'customer_name'   => $payload['cus_name'],
                'customer_email'  => $payload['cus_email'],
                'customer_phone'  => $payload['cus_phone'],
                'request_payload' => json_encode($payload),
            ]);
        }

        return [
            'status'      => 'SUCCESS',
            'tran_id'     => $tranId,
            'payment_url' => $paymentUrl,
            'raw'         => $response,
        ];
    }

    /**
     * Verify payment status using AamarPay TrxCheck API.
     */
    public function verify(string $tranId): array
    {
        $this->assertCredentials();
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $tranId)) {
            throw new RuntimeException('Invalid AamarPay transaction id.');
        }

        $params = [
            'request_id'    => $tranId,
            'store_id'      => $this->storeId,
            'signature_key' => $this->signatureKey,
            'type'          => 'json',
        ];

        $url = $this->getBaseUrl() . '/api/v1/trxcheck/request.php?' . http_build_query($params);
        $result = $this->getJson($url);

        if (!is_array($result)) {
            throw new RuntimeException("Invalid response from AamarPay verification endpoint.");
        }

        $payStatus = (string) ($result['pay_status'] ?? $result['status_code'] ?? '');
        $isSuccess = strcasecmp($payStatus, 'Successful') === 0 || $payStatus === '2' || $payStatus === 'SUCCESS';
        $amount = (float) ($result['amount'] ?? 0);
        $currency = strtoupper((string) ($result['currency'] ?? 'BDT'));

        // The payment must cover the transaction stored by initiate()
        $stored = $this->storedTransaction($tranId);
        if ($isSuccess && $stored) {
            $storedCurrency = strtoupper((string) ($stored->currency ?? ''));
            if (abs((float) $stored->amount - $amount) > 0.009 || ($storedCurrency !== '' && $storedCurrency !== $currency)) {
                $isSuccess = false;
                $payStatus = 'AMOUNT_MISMATCH';
            }
        }

        $changed = null;
        if ($this->logTransactions) {
            // One way only: a completed payment is never marked failed by a later check
            $changed = $this->transition($tranId, $isSuccess ? ['INITIATED', 'FAILED'] : ['INITIATED'], $isSuccess ? 'COMPLETED' : 'FAILED', [
                'payment_method'   => $result['card_type'] ?? $result['payment_processor'] ?? null,
                'bank_tran_id'     => $result['pg_txnid'] ?? $result['bank_trxid'] ?? null,
                'response_payload' => json_encode($result),
            ]);
        }

        $verified = [
            'success'        => $isSuccess,
            'tran_id'        => $tranId,
            'amount'         => $amount,
            'currency'       => $currency,
            'status'         => $payStatus,
            'bank_tran_id'   => $result['pg_txnid'] ?? null,
            'payment_method' => $result['card_type'] ?? null,
            'raw'            => $result,
        ];

        // Once per stored transaction, like the other gateways
        if ($changed === true && class_exists(\System\Events\Event::class)) {
            \System\Events\Event::dispatch($isSuccess ? 'payment.success' : 'payment.failed', ['gateway' => 'aamarpay'] + $verified);
        }

        return $verified;
    }

    /**
     * Check if a callback post array represents a successful payment.
     */
    public function isSuccess(array $post): bool
    {
        // The posted pay_status is chosen by whoever sends the request: always ask AamarPay
        try {
            return (bool) ($this->verify((string) ($post['mer_txnid'] ?? $post['tran_id'] ?? ''))['success'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getStoreId(): string
    {
        return $this->storeId;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    protected function assertCredentials(): void
    {
        if (empty($this->storeId) || empty($this->signatureKey)) {
            throw new RuntimeException("AamarPay Store ID or Signature Key is missing. Check config/aamarpay.php or .env.");
        }
    }

    protected function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = function_exists('url') ? rtrim(url('/'), '/') : (($_SERVER['HTTP_HOST'] ?? 'localhost') ? 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') : 'http://localhost');
        return $base . '/' . ltrim($path, '/');
    }

    protected function postJson(string $url, array $data)
    {
        $payload = json_encode($data);
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                             "Accept: application/json\r\n" .
                             "Content-Length: " . strlen((string) $payload) . "\r\n" .
                             "User-Agent: Bongo-AamarPay/1.0\r\n",
                'content' => $payload,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException("Could not connect to AamarPay gateway at {$url}.");
        }

        $decoded = json_decode((string) $response, true);
        return $decoded !== null ? $decoded : $response;
    }

    protected function getJson(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "Accept: application/json\r\nUser-Agent: Bongo-AamarPay/1.0\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function logTransaction(array $data): void
    {
        try {
            if (class_exists(DB::class)) {
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                DB::table('aamarpay_transactions')->insert($data);
            }
        } catch (\Throwable) {
            // Silently continue if table does not exist
        }
    }

    protected function storedTransaction(string $tranId): ?object
    {
        try {
            return class_exists(DB::class) ? (DB::table('aamarpay_transactions')->where('tran_id', $tranId)->first() ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Change the stored status under a row lock, only from one of $from.
     * true = changed, false = refused (e.g. already COMPLETED), null = no stored row.
     */
    protected function transition(string $tranId, array $from, string $status, array $data): ?bool
    {
        try {
            if (!class_exists(DB::class)) {
                return null;
            }
            return DB::transaction(function () use ($tranId, $from, $status, $data): ?bool {
                $row = DB::table('aamarpay_transactions')->where('tran_id', $tranId)->lockForUpdate()->first();
                if (!$row) {
                    return null;
                }
                if (!in_array((string) $row->status, $from, true)) {
                    return false;
                }
                $data['status'] = $status;
                $data['updated_at'] = date('Y-m-d H:i:s');
                DB::table('aamarpay_transactions')->where('tran_id', $tranId)->update($data);
                return true;
            });
        } catch (\Throwable $e) {
            return null;
        }
    }
}
