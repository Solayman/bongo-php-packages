<?php

declare(strict_types=1);

namespace Bongo\Packages\Courier;

use RuntimeException;
use InvalidArgumentException;

class Courier
{
    protected string $driver;
    protected static bool $faking = false;
    protected static array $fakeResponses = [];
    protected static array $requests = [];

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? config('courier.default', 'steadfast');
    }

    public static function driver(?string $name = null): self
    {
        return new static($name);
    }

    /**
     * Enable fake mode for tests.
     */
    public static function fake(array $responses = []): void
    {
        static::$faking = true;
        static::$fakeResponses = $responses;
        static::$requests = [];
    }

    public static function restore(): void
    {
        static::$faking = false;
        static::$fakeResponses = [];
        static::$requests = [];
    }

    public static function getRequests(): array
    {
        return static::$requests;
    }

    /**
     * Create a new shipment order / consignment.
     *
     * Format:
     * [
     *   'invoice'          => 'ORD-1001',
     *   'recipient_name'   => 'Rahim Ahmed',
     *   'recipient_phone'  => '01700000000',
     *   'recipient_address'=> 'House 12, Road 4, Dhanmondi, Dhaka',
     *   'cod_amount'       => 1500.00,
     *   'note'             => 'Handle with care',
     * ]
     */
    public function createOrder(array $data): array
    {
        if (empty($data['recipient_phone']) || empty($data['recipient_address'])) {
            throw new InvalidArgumentException('Recipient phone and address are required to create a courier order.');
        }

        if ($this->driver === 'steadfast') {
            return $this->createSteadfastOrder($data);
        }

        return $this->createPathaoOrder($data);
    }

    /**
     * Check shipment status by tracking code or consignment ID.
     */
    public function checkStatus(string $identifier): array
    {
        if ($this->driver === 'steadfast') {
            return $this->checkSteadfastStatus($identifier);
        }

        return $this->checkPathaoStatus($identifier);
    }

    /**
     * Check merchant current COD balance.
     */
    public function checkBalance(): array
    {
        if ($this->driver === 'steadfast') {
            return $this->checkSteadfastBalance();
        }

        return ['driver' => $this->driver, 'balance' => 0.0];
    }

    /**
     * The webhook request carries the shared secret set in courier.webhook_secret
     * (COURIER_WEBHOOK_SECRET; set the same value in the courier's dashboard). Accepted as
     * "Authorization: Bearer <secret>" or "X-Pathao-Signature: <secret>". False when no secret is set.
     */
    public function verifyWebhook(array $headers): bool
    {
        $secret = (string) (function_exists('config') ? config('courier.webhook_secret', '') : '');
        if ($secret === '') {
            return false;
        }
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        $given = $normalized['x-pathao-signature'] ?? '';
        $authorization = $normalized['authorization'] ?? '';
        if ($given === '' && str_starts_with($authorization, 'Bearer ')) {
            $given = substr($authorization, 7);
        }
        return $given !== '' && hash_equals($secret, $given);
    }

    /**
     * Parse and normalize inbound webhook events from courier partners.
     * Pass the request headers: with courier.webhook_secret set, an unverified call throws, so a
     * forged "delivered" update can never be processed. Check 'verified' when no secret is set.
     */
    public function handleWebhook(array $payload, array $headers = []): array
    {
        $verified = $this->verifyWebhook($headers);
        $hasSecret = (string) (function_exists('config') ? config('courier.webhook_secret', '') : '') !== '';
        if ($hasSecret && !$verified) {
            throw new \RuntimeException('Courier webhook rejected: missing or wrong webhook secret.');
        }

        if ($this->driver === 'steadfast') {
            return [
                'driver'         => 'steadfast',
                'consignment_id' => $payload['consignment_id'] ?? null,
                'invoice'        => $payload['invoice'] ?? null,
                'status'         => strtolower($payload['status'] ?? 'unknown'),
                'updated_at'     => $payload['updated_at'] ?? gmdate('Y-m-d H:i:s'),
                'verified'       => $verified,
                'raw'            => $payload,
            ];
        }

        return [
            'driver'         => 'pathao',
            'consignment_id' => $payload['consignment_id'] ?? null,
            'status'         => strtolower($payload['order_status'] ?? 'unknown'),
            'updated_at'     => gmdate('Y-m-d H:i:s'),
            'verified'       => $verified,
            'raw'            => $payload,
        ];
    }

    /* --------------------------------------------------------------------------
     * Steadfast Implementation
     * -------------------------------------------------------------------------- */

    protected function createSteadfastOrder(array $data): array
    {
        $baseUrl = rtrim(config('courier.steadfast.base_url', 'https://portal.steadfast.com.bd/api/v1'), '/');
        $endpoint = $baseUrl . '/create_order';

        $payload = [
            'invoice'           => (string) ($data['invoice'] ?? 'ORD-' . time()),
            'recipient_name'    => $data['recipient_name'] ?? 'Customer',
            'recipient_phone'   => $data['recipient_phone'],
            'recipient_address' => $data['recipient_address'],
            'cod_amount'        => (float) ($data['cod_amount'] ?? 0),
            'note'              => $data['note'] ?? '',
        ];

        if (static::$faking) {
            static::$requests[] = ['method' => 'POST', 'url' => $endpoint, 'payload' => $payload];
            $cid = rand(100000, 999999);
            $trk = 'SF' . rand(10000000, 99999999);
            return static::$fakeResponses['create_order'] ?? [
                'status'         => 200,
                'message'        => 'Order created successfully',
                'consignment'    => [
                    'consignment_id' => $cid,
                    'invoice'        => $payload['invoice'],
                    'tracking_code'  => $trk,
                    'recipient_name' => $payload['recipient_name'],
                    'recipient_phone'=> $payload['recipient_phone'],
                    'recipient_address' => $payload['recipient_address'],
                    'cod_amount'     => $payload['cod_amount'],
                    'status'         => 'in_review',
                ],
            ];
        }

        $apiKey    = config('courier.steadfast.api_key', '');
        $secretKey = config('courier.steadfast.secret_key', '');

        return $this->sendHttpRequest('POST', $endpoint, $payload, [
            'Api-Key: ' . $apiKey,
            'Secret-Key: ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    protected function checkSteadfastStatus(string $identifier): array
    {
        $baseUrl = rtrim(config('courier.steadfast.base_url', 'https://portal.steadfast.com.bd/api/v1'), '/');
        // If starts with letters, it's tracking code, otherwise consignment ID
        $endpoint = ctype_digit($identifier)
            ? $baseUrl . '/status_by_cid/' . urlencode($identifier)
            : $baseUrl . '/status_by_trackingcode/' . urlencode($identifier);

        if (static::$faking) {
            static::$requests[] = ['method' => 'GET', 'url' => $endpoint, 'payload' => null];
            return static::$fakeResponses['check_status'] ?? [
                'status'         => 200,
                'delivery_status'=> 'delivered',
            ];
        }

        $apiKey    = config('courier.steadfast.api_key', '');
        $secretKey = config('courier.steadfast.secret_key', '');

        return $this->sendHttpRequest('GET', $endpoint, null, [
            'Api-Key: ' . $apiKey,
            'Secret-Key: ' . $secretKey,
            'Accept: application/json',
        ]);
    }

    protected function checkSteadfastBalance(): array
    {
        $baseUrl = rtrim(config('courier.steadfast.base_url', 'https://portal.steadfast.com.bd/api/v1'), '/');
        $endpoint = $baseUrl . '/get_balance';

        if (static::$faking) {
            static::$requests[] = ['method' => 'GET', 'url' => $endpoint, 'payload' => null];
            return static::$fakeResponses['get_balance'] ?? [
                'status'          => 200,
                'current_balance' => 12500.50,
            ];
        }

        $apiKey    = config('courier.steadfast.api_key', '');
        $secretKey = config('courier.steadfast.secret_key', '');

        return $this->sendHttpRequest('GET', $endpoint, null, [
            'Api-Key: ' . $apiKey,
            'Secret-Key: ' . $secretKey,
            'Accept: application/json',
        ]);
    }

    /* --------------------------------------------------------------------------
     * Pathao Implementation
     * -------------------------------------------------------------------------- */

    protected function createPathaoOrder(array $data): array
    {
        $baseUrl = rtrim(config('courier.pathao.base_url', 'https://courier-api-sandbox.pathao.com/aladdin/api/v1'), '/');
        $endpoint = $baseUrl . '/orders';

        $payload = [
            'store_id'            => config('courier.pathao.store_id', '1'),
            'merchant_order_id'   => $data['invoice'] ?? 'ORD-' . time(),
            'recipient_name'      => $data['recipient_name'] ?? 'Customer',
            'recipient_phone'     => $data['recipient_phone'],
            'recipient_address'   => $data['recipient_address'],
            'amount_to_collect'   => (int) ($data['cod_amount'] ?? 0),
            'item_type'           => 1, // parcel
            'delivery_type'       => 48, // normal delivery
            'item_weight'         => $data['weight'] ?? 0.5,
            'item_description'    => $data['note'] ?? 'Parcel',
        ];

        if (static::$faking) {
            static::$requests[] = ['method' => 'POST', 'url' => $endpoint, 'payload' => $payload];
            $cid = 'PTH-' . rand(1000000, 9999999);
            return static::$fakeResponses['create_order'] ?? [
                'type'           => 'success',
                'message'        => 'Order created successfully',
                'data'           => [
                    'consignment_id' => $cid,
                    'order_status'   => 'Pending',
                ],
            ];
        }

        return $this->sendHttpRequest('POST', $endpoint, $payload, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    protected function checkPathaoStatus(string $identifier): array
    {
        $baseUrl = rtrim(config('courier.pathao.base_url', 'https://courier-api-sandbox.pathao.com/aladdin/api/v1'), '/');
        $endpoint = $baseUrl . '/orders/' . urlencode($identifier);

        if (static::$faking) {
            static::$requests[] = ['method' => 'GET', 'url' => $endpoint, 'payload' => null];
            return static::$fakeResponses['check_status'] ?? [
                'type' => 'success',
                'data' => [
                    'consignment_id' => $identifier,
                    'order_status'   => 'Delivered',
                ],
            ];
        }

        return $this->sendHttpRequest('GET', $endpoint, null, [
            'Accept: application/json',
        ]);
    }

    /**
     * HTTP helper with cURL fallback.
     */
    protected function sendHttpRequest(string $method, string $url, ?array $payload = null, array $headers = []): array
    {
        $body = $payload !== null ? json_encode($payload) : '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            if (!empty($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $opts = [
                'http' => [
                    'method'  => $method,
                    'header'  => implode("\r\n", $headers) . "\r\n",
                    'content' => $body,
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ];
            $context = stream_context_create($opts);
            $raw = @file_get_contents($url, false, $context);
            $code = 200;
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : ['raw' => $raw, 'status_code' => $code ?? 200];
    }
}
