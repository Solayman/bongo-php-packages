<?php

declare(strict_types=1);

namespace Bongo\Packages\Paypal;

use Bongo\Packages\Paypal\Exceptions\PaypalException;
use InvalidArgumentException;
use System\Events\Event;

class Paypal
{
    protected string $mode;
    protected string $clientId;
    protected string $clientSecret;
    protected string $webhookId;
    protected string $currency;
    protected string $brandName;
    protected string $locale;
    protected string $returnUrl;
    protected string $cancelUrl;
    protected string $intent;

    protected ?string $cachedToken = null;
    protected int $tokenExpiresAt = 0;

    /**
     * @var callable|null Mock transport for testing: fn(string $method, string $url, array|string $body, array $headers): array
     */
    protected static $mockTransport = null;

    public function __construct(array $config = [])
    {
        $this->mode = strtolower((string) ($config['mode'] ?? (function_exists('config') ? config('paypal.mode') : null) ?? env('PAYPAL_MODE', 'sandbox')));
        $this->clientId = (string) ($config['client_id'] ?? (function_exists('config') ? config('paypal.client_id') : null) ?? env('PAYPAL_CLIENT_ID', ''));
        $this->clientSecret = (string) ($config['client_secret'] ?? (function_exists('config') ? config('paypal.client_secret') : null) ?? env('PAYPAL_CLIENT_SECRET', ''));
        $this->webhookId = (string) ($config['webhook_id'] ?? (function_exists('config') ? config('paypal.webhook_id') : null) ?? env('PAYPAL_WEBHOOK_ID', ''));
        $this->currency = strtoupper((string) ($config['currency'] ?? (function_exists('config') ? config('paypal.currency') : null) ?? env('PAYPAL_CURRENCY', 'USD')));
        $this->brandName = (string) ($config['brand_name'] ?? (function_exists('config') ? config('paypal.brand_name') : null) ?? env('APP_NAME', 'Bongo Store'));
        $this->locale = (string) ($config['locale'] ?? (function_exists('config') ? config('paypal.locale') : null) ?? env('PAYPAL_LOCALE', 'en-US'));
        $this->returnUrl = (string) ($config['return_url'] ?? (function_exists('config') ? config('paypal.return_url') : null) ?? env('PAYPAL_RETURN_URL', '/paypal/success'));
        $this->cancelUrl = (string) ($config['cancel_url'] ?? (function_exists('config') ? config('paypal.cancel_url') : null) ?? env('PAYPAL_CANCEL_URL', '/paypal/cancel'));
        $this->intent = strtoupper((string) ($config['intent'] ?? (function_exists('config') ? config('paypal.intent') : null) ?? env('PAYPAL_INTENT', 'CAPTURE')));
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public static function fake(?callable $callback = null): void
    {
        if ($callback !== null) {
            self::$mockTransport = $callback;
            return;
        }

        self::$mockTransport = function (string $method, string $url, $body, array $headers): array {
            if (str_contains($url, '/v1/oauth2/token')) {
                return [
                    'access_token' => 'mock_paypal_access_token_12345',
                    'token_type'   => 'Bearer',
                    'expires_in'   => 32400,
                ];
            }

            if (str_contains($url, '/v2/checkout/orders') && $method === 'POST') {
                if (str_ends_with($url, '/capture')) {
                    return [
                        'id'     => 'ORDER-MOCK-12345',
                        'status' => 'COMPLETED',
                        'purchase_units' => [
                            [
                                'reference_id' => 'default',
                                'payments' => [
                                    'captures' => [
                                        [
                                            'id'     => 'CAPTURE-MOCK-67890',
                                            'status' => 'COMPLETED',
                                            'amount' => ['currency_code' => 'USD', 'value' => '25.00'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'payer' => [
                            'email_address' => 'buyer@example.com',
                            'payer_id'      => 'PAYER-123',
                            'name'          => ['given_name' => 'John', 'surname' => 'Doe'],
                        ],
                    ];
                }

                $orderData = is_string($body) ? json_decode($body, true) : $body;
                $amount = $orderData['purchase_units'][0]['amount']['value'] ?? '10.00';
                $currency = $orderData['purchase_units'][0]['amount']['currency_code'] ?? 'USD';

                return [
                    'id'     => 'ORDER-MOCK-12345',
                    'status' => 'CREATED',
                    'purchase_units' => [
                        [
                            'reference_id' => 'default',
                            'amount'       => ['currency_code' => $currency, 'value' => $amount],
                        ],
                    ],
                    'links' => [
                        [
                            'href'   => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-MOCK-12345',
                            'rel'    => 'approve',
                            'method' => 'GET',
                        ],
                        [
                            'href'   => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-MOCK-12345',
                            'rel'    => 'self',
                            'method' => 'GET',
                        ],
                    ],
                ];
            }

            if (str_contains($url, '/v2/checkout/orders/')) {
                return [
                    'id'     => 'ORDER-MOCK-12345',
                    'status' => 'APPROVED',
                    'purchase_units' => [
                        [
                            'amount' => ['currency_code' => 'USD', 'value' => '25.00'],
                        ],
                    ],
                ];
            }

            if (str_contains($url, '/refund')) {
                return [
                    'id'     => 'REFUND-MOCK-99999',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '25.00'],
                ];
            }

            if (str_contains($url, '/verify-webhook-signature')) {
                return ['verification_status' => 'SUCCESS'];
            }

            return ['status' => 'SUCCESS'];
        };
    }

    public function getBaseUrl(): string
    {
        return $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get or fetch valid OAuth2 access token with memory caching.
     */
    public function getAccessToken(): string
    {
        if ($this->cachedToken !== null && time() < ($this->tokenExpiresAt - 60)) {
            return $this->cachedToken;
        }

        $this->ensureConfigured();

        $url = $this->getBaseUrl() . '/v1/oauth2/token';
        $authHeader = 'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret);
        $headers = [
            $authHeader,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];
        $body = 'grant_type=client_credentials';

        if (self::$mockTransport !== null) {
            $response = (self::$mockTransport)('POST', $url, $body, $headers);
        } else {
            $response = $this->executeCurl('POST', $url, $body, $headers);
        }

        if (empty($response['access_token'])) {
            $msg = $response['error_description'] ?? $response['message'] ?? 'Failed to authenticate with PayPal API';
            throw new PaypalException("PayPal Auth Error: {$msg}", 401, null, $response);
        }

        $this->cachedToken = (string) $response['access_token'];
        $expiresIn = (int) ($response['expires_in'] ?? 3600);
        $this->tokenExpiresAt = time() + $expiresIn;

        return $this->cachedToken;
    }

    /**
     * Create PayPal Checkout Order (v2).
     */
    public function createOrder(array $params): array
    {
        $amount = (float) ($params['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('PayPal checkout requires a positive amount greater than 0.');
        }

        $currency = strtoupper((string) ($params['currency'] ?? $this->currency));
        $referenceId = (string) ($params['reference_id'] ?? 'default');
        $description = (string) ($params['description'] ?? 'Order payment');
        $customId = (string) ($params['custom_id'] ?? '');

        $formattedAmount = number_format($amount, 2, '.', '');

        $payload = [
            'intent' => strtoupper((string) ($params['intent'] ?? $this->intent)),
            'purchase_units' => [
                [
                    'reference_id' => $referenceId,
                    'description'  => $description,
                    'amount'       => [
                        'currency_code' => $currency,
                        'value'         => $formattedAmount,
                    ],
                ],
            ],
            'application_context' => [
                'brand_name'   => (string) ($params['brand_name'] ?? $this->brandName),
                'locale'       => (string) ($params['locale'] ?? $this->locale),
                'landing_page' => (string) ($params['landing_page'] ?? 'BILLING'),
                'user_action'  => 'PAY_NOW',
                'return_url'   => (string) ($params['return_url'] ?? $this->returnUrl),
                'cancel_url'   => (string) ($params['cancel_url'] ?? $this->cancelUrl),
            ],
        ];

        if ($customId !== '') {
            $payload['purchase_units'][0]['custom_id'] = $customId;
        }

        if (!empty($params['items']) && is_array($params['items'])) {
            $payload['purchase_units'][0]['items'] = $params['items'];
        }

        $response = $this->authenticatedRequest('POST', '/v2/checkout/orders', $payload);

        $orderId = (string) ($response['id'] ?? '');
        if ($orderId !== '') {
            $this->recordOrder([
                'order_id'    => $orderId,
                'status'      => $response['status'] ?? 'CREATED',
                'amount'      => $amount,
                'currency'    => $currency,
                'payer_email' => $params['payer_email'] ?? null,
                'metadata'    => !empty($params['metadata']) ? json_encode($params['metadata']) : null,
            ]);
        }

        return $response;
    }

    /**
     * Get redirect approval URL from an order response.
     */
    public function getApprovalUrl(array $order): ?string
    {
        foreach ($order['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return (string) $link['href'];
            }
        }
        return null;
    }

    /**
     * Get Order Details (v2).
     */
    public function getOrder(string $orderId): array
    {
        return $this->authenticatedRequest('GET', '/v2/checkout/orders/' . $this->pathId($orderId));
    }

    /**
     * Capture Payment for an Order (v2).
     */
    public function captureOrder(string $orderId): array
    {
        $response = $this->authenticatedRequest('POST', '/v2/checkout/orders/' . $this->pathId($orderId) . '/capture', []);

        $status = (string) ($response['status'] ?? '');
        $captureId = null;
        $captures = $response['purchase_units'][0]['payments']['captures'] ?? [];
        if (!empty($captures) && is_array($captures)) {
            $captureId = $captures[0]['id'] ?? null;
        }

        $payerEmail = $response['payer']['email_address'] ?? null;
        $payerId = $response['payer']['payer_id'] ?? null;
        $payerName = null;
        if (!empty($response['payer']['name'])) {
            $nameParts = array_filter([
                $response['payer']['name']['given_name'] ?? null,
                $response['payer']['name']['surname'] ?? null,
            ]);
            $payerName = implode(' ', $nameParts);
        }

        $completed = $status === 'COMPLETED';
        $attributes = [
            'capture_id'  => $captureId,
            'payer_email' => $payerEmail,
            'payer_id'    => $payerId,
            'payer_name'  => $payerName,
        ];
        if ($completed) {
            $first = $this->completeOnce($orderId, $attributes);
        } else {
            $this->changeStatus($orderId, $status, ['COMPLETED', 'REFUNDED'], $attributes);
            $first = false;
        }

        if (class_exists(Event::class) && $completed && $first) {
            $amount = 0.0;
            $currency = $this->currency;
            if (!empty($captures[0]['amount'])) {
                $amount = (float) ($captures[0]['amount']['value'] ?? 0);
                $currency = $captures[0]['amount']['currency_code'] ?? $this->currency;
            }

            Event::dispatch('payment.success', [
                'gateway'     => 'paypal',
                'order_id'    => $orderId,
                'capture_id'  => $captureId,
                'amount'      => $amount,
                'currency'    => $currency,
                'payer_email' => $payerEmail,
                'payer_name'  => $payerName,
                'raw'         => $response,
            ]);
        }

        return $response;
    }

    /**
     * Authorize Payment for an Order (v2).
     */
    public function authorizeOrder(string $orderId): array
    {
        $response = $this->authenticatedRequest('POST', '/v2/checkout/orders/' . $this->pathId($orderId) . '/authorize', []);
        $this->updateOrderStatus($orderId, [
            'status' => $response['status'] ?? 'AUTHORIZED',
        ]);
        return $response;
    }

    /**
     * Refund a captured payment (v2).
     */
    public function refund(string $captureId, ?float $amount = null, ?string $currency = null, string $note = ''): array
    {
        $payload = [];
        if ($amount !== null && $amount > 0) {
            $payload['amount'] = [
                'value'         => number_format($amount, 2, '.', ''),
                'currency_code' => strtoupper($currency ?? $this->currency),
            ];
        }
        if ($note !== '') {
            $payload['note_to_payer'] = $note;
        }

        $response = $this->authenticatedRequest('POST', '/v2/payments/captures/' . $this->pathId($captureId) . '/refund', $payload);

        $status = (string) ($response['status'] ?? 'COMPLETED');
        $this->updateOrderStatusByCapture($captureId, 'REFUNDED');

        if (class_exists(Event::class)) {
            Event::dispatch('payment.refunded', [
                'gateway'    => 'paypal',
                'capture_id' => $captureId,
                'refund_id'  => $response['id'] ?? null,
                'amount'     => $amount,
                'currency'   => $currency ?? $this->currency,
                'raw'        => $response,
            ]);
        }

        return $response;
    }

    /**
     * Verify incoming webhook signature using PayPal's verification endpoint.
     */
    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower((string) $key)] = is_array($value) ? ($value[0] ?? '') : (string) $value;
        }

        $authAlgo = $normalizedHeaders['paypal-auth-algo'] ?? '';
        $certUrl = $normalizedHeaders['paypal-cert-url'] ?? '';
        $transmissionId = $normalizedHeaders['paypal-transmission-id'] ?? '';
        $transmissionSig = $normalizedHeaders['paypal-transmission-sig'] ?? '';
        $transmissionTime = $normalizedHeaders['paypal-transmission-time'] ?? '';

        if (empty($authAlgo) || empty($certUrl) || empty($transmissionId) || empty($transmissionSig) || empty($transmissionTime)) {
            return false;
        }

        $webhookId = $this->webhookId;
        if (empty($webhookId)) {
            return false;
        }

        $payload = [
            'auth_algo'         => $authAlgo,
            'cert_url'          => $certUrl,
            'transmission_id'   => $transmissionId,
            'transmission_sig'  => $transmissionSig,
            'transmission_time' => $transmissionTime,
            'webhook_id'        => $webhookId,
            'webhook_event'     => json_decode($rawBody, true) ?: [],
        ];

        try {
            $response = $this->authenticatedRequest('POST', '/v1/notifications/verify-webhook-signature', $payload);
            return ($response['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Handle incoming webhook event.
     */
    public function handleWebhook(array $headers, string $rawBody): void
    {
        if (!$this->verifyWebhook($headers, $rawBody)) {
            throw new PaypalException('Invalid PayPal webhook signature.', 400);
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            throw new PaypalException('Invalid JSON in PayPal webhook payload.', 400);
        }

        // PayPal retries deliveries: handle each webhook event id once
        if (!empty($event['id']) && $this->remembered('event:' . $event['id'])) {
            return;
        }

        $eventType = (string) ($event['event_type'] ?? '');
        $resource = $event['resource'] ?? [];

        switch ($eventType) {
            case 'CHECKOUT.ORDER.APPROVED':
                $orderId = (string) ($resource['id'] ?? '');
                $this->changeStatus($orderId, 'APPROVED', ['COMPLETED', 'REFUNDED', 'DENIED']);
                if (class_exists(Event::class)) {
                    Event::dispatch('paypal.order.approved', [
                        'order_id' => $orderId,
                        'resource' => $resource,
                    ]);
                }
                break;

            case 'PAYMENT.CAPTURE.COMPLETED':
                $captureId = (string) ($resource['id'] ?? '');
                $orderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
                $amount = (float) ($resource['amount']['value'] ?? 0);
                $currency = (string) ($resource['amount']['currency_code'] ?? $this->currency);

                $first = $orderId !== ''
                    ? $this->completeOnce($orderId, ['capture_id' => $captureId])
                    : $this->completeOnce('', ['capture_id' => $captureId], $captureId);

                // The return page usually captured (and reported) the order already
                if ($first && class_exists(Event::class)) {
                    Event::dispatch('payment.success', [
                        'gateway'    => 'paypal',
                        'order_id'   => $orderId,
                        'capture_id' => $captureId,
                        'amount'     => $amount,
                        'currency'   => $currency,
                        'raw'        => $resource,
                    ]);
                }
                break;

            case 'PAYMENT.CAPTURE.DENIED':
                $captureId = (string) ($resource['id'] ?? '');
                $orderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');

                $denied = $orderId !== '' && $this->changeStatus($orderId, 'DENIED', ['COMPLETED', 'REFUNDED', 'DENIED']);

                if ($denied && class_exists(Event::class)) {
                    Event::dispatch('payment.failed', [
                        'gateway'    => 'paypal',
                        'order_id'   => $orderId,
                        'capture_id' => $captureId,
                        'reason'     => $resource['status_details']['reason'] ?? 'Capture denied',
                        'raw'        => $resource,
                    ]);
                }
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
                $captureId = (string) ($resource['id'] ?? '');
                $this->updateOrderStatusByCapture($captureId, 'REFUNDED');
                if (class_exists(Event::class)) {
                    Event::dispatch('payment.refunded', [
                        'gateway'    => 'paypal',
                        'capture_id' => $captureId,
                        'raw'        => $resource,
                    ]);
                }
                break;
        }
    }

    protected function authenticatedRequest(string $method, string $endpoint, array $payload = []): array
    {
        $token = $this->getAccessToken();
        $url = $this->getBaseUrl() . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Bongo-PayPal/1.0.0 (PHP ' . PHP_VERSION . ')',
        ];

        $encodedPayload = !empty($payload) || $method === 'POST' ? json_encode($payload) : '';

        if (self::$mockTransport !== null) {
            $mockResponse = (self::$mockTransport)($method, $url, $encodedPayload, $headers);
            if (isset($mockResponse['error']) || isset($mockResponse['name']) && $mockResponse['name'] === 'UNPROCESSABLE_ENTITY') {
                $msg = $mockResponse['message'] ?? $mockResponse['error'] ?? 'PayPal API Error';
                throw new PaypalException("PayPal API Error: {$msg}", 400, null, $mockResponse);
            }
            return $mockResponse;
        }

        return $this->executeCurl($method, $url, $encodedPayload, $headers);
    }

    protected function executeCurl(string $method, string $url, string $body, array $headers): array
    {
        $ch = curl_init();
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'GET' && $body !== '') {
            $url .= '?' . $body;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawOutput = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawOutput === false || !empty($curlError)) {
            throw new PaypalException("PayPal API Network Error: {$curlError}", 500);
        }

        $data = json_decode((string) $rawOutput, true);
        if (!is_array($data)) {
            throw new PaypalException("Invalid response from PayPal API (HTTP {$status}): {$rawOutput}", $status);
        }

        if ($status >= 400) {
            $msg = $data['message'] ?? $data['error_description'] ?? "HTTP error {$status}";
            throw new PaypalException("PayPal API Error: {$msg}", $status, null, $data);
        }

        return $data;
    }

    protected function ensureConfigured(): void
    {
        if (self::$mockTransport !== null) {
            return;
        }

        if (trim($this->clientId) === '' || trim($this->clientSecret) === '') {
            throw new InvalidArgumentException('PayPal Client ID and Secret are not configured. Set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET in .env.');
        }
    }

    protected function recordOrder(array $data): void
    {
        if (!class_exists(\System\Database\DB::class)) {
            return;
        }

        try {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            \System\Database\DB::table('paypal_orders')->insert($data);
        } catch (\Throwable $e) {
            // Silently allow payment flow to proceed if DB table is unmigrated
        }
    }

    /** An id that is placed in an API path: PayPal ids are letters, digits and dashes */
    protected function pathId(string $id): string
    {
        if (!preg_match('/^[A-Za-z0-9-]{1,64}$/', $id)) {
            throw new PaypalException('Invalid PayPal id.', 400);
        }
        return $id;
    }

    /**
     * Mark the order COMPLETED the first time only (row lock). Returns true when this call completed it.
     * Without a stored row (table not migrated), the order/capture id is remembered in the cache instead.
     */
    protected function completeOnce(string $orderId, array $attributes, string $captureId = ''): bool
    {
        $changed = $orderId !== ''
            ? $this->changeStatus($orderId, 'COMPLETED', ['COMPLETED', 'REFUNDED'], $attributes)
            : $this->changeStatusByCapture($captureId, 'COMPLETED', ['COMPLETED', 'REFUNDED']);
        if ($changed !== null) {
            return $changed;
        }
        $key = $orderId !== '' ? $orderId : $captureId;
        return $key !== '' && !$this->remembered('paid:' . $key);
    }

    /**
     * Change paypal_orders.status under a row lock, unless it is one of $unless.
     * true = changed, false = refused (e.g. already COMPLETED), null = no stored row.
     */
    protected function changeStatus(string $orderId, string $status, array $unless, array $attributes = []): ?bool
    {
        if ($orderId === '' || !class_exists(\System\Database\DB::class)) {
            return null;
        }
        try {
            return \System\Database\DB::transaction(function () use ($orderId, $status, $unless, $attributes): ?bool {
                $row = \System\Database\DB::table('paypal_orders')->where('order_id', '=', $orderId)->lockForUpdate()->first();
                if (!$row) {
                    return null;
                }
                if (in_array((string) $row->status, $unless, true)) {
                    return false;
                }
                $attributes = array_filter($attributes, fn($value) => $value !== null);
                $attributes['status'] = $status;
                $attributes['updated_at'] = date('Y-m-d H:i:s');
                \System\Database\DB::table('paypal_orders')->where('order_id', '=', $orderId)->update($attributes);
                return true;
            });
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function changeStatusByCapture(string $captureId, string $status, array $unless): ?bool
    {
        if ($captureId === '' || !class_exists(\System\Database\DB::class)) {
            return null;
        }
        try {
            $row = \System\Database\DB::table('paypal_orders')->where('capture_id', '=', $captureId)->first();
        } catch (\Throwable $e) {
            return null;
        }
        return $row ? $this->changeStatus((string) $row->order_id, $status, $unless) : null;
    }

    /** True when $key was handled before; otherwise remembers it for 30 days and returns false */
    protected function remembered(string $key): bool
    {
        try {
            $cacheKey = 'paypal:' . sha1($key);
            if (\System\Cache\Cache::get($cacheKey)) {
                return true;
            }
            \System\Cache\Cache::put($cacheKey, 1, 2592000);
        } catch (\Throwable $e) {
            // no cache configured: the stored row still guards payments
        }
        return false;
    }

    protected function updateOrderStatus(string $orderId, array $attributes): void
    {
        if (!class_exists(\System\Database\DB::class) || empty($orderId)) {
            return;
        }

        try {
            $attributes['updated_at'] = date('Y-m-d H:i:s');
            \System\Database\DB::table('paypal_orders')
                ->where('order_id', '=', $orderId)
                ->update($attributes);
        } catch (\Throwable $e) {
            // Allow flow to continue
        }
    }

    protected function updateOrderStatusByCapture(string $captureId, string $status): void
    {
        if (!class_exists(\System\Database\DB::class) || empty($captureId)) {
            return;
        }

        try {
            \System\Database\DB::table('paypal_orders')
                ->where('capture_id', '=', $captureId)
                ->update([
                    'status'     => $status,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $e) {
            // Allow flow to continue
        }
    }

    /* -------------------------------------------------------------------------
     | Static Facade Methods
     | -------------------------------------------------------------------------
     */

    public static function create(array $params): array
    {
        return (new static())->createOrder($params);
    }

    public static function capture(string $orderId): array
    {
        return (new static())->captureOrder($orderId);
    }

    public static function order(string $orderId): array
    {
        return (new static())->getOrder($orderId);
    }

    public static function issueRefund(string $captureId, ?float $amount = null, ?string $currency = null, string $note = ''): array
    {
        return (new static())->refund($captureId, $amount, $currency, $note);
    }

    public static function approvalUrl(array $order): ?string
    {
        return (new static())->getApprovalUrl($order);
    }
}
