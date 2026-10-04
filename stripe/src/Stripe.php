<?php

declare(strict_types=1);

namespace Bongo\Packages\Stripe;

use InvalidArgumentException;
use RuntimeException;
use System\Events\Event;

class Stripe
{
    protected string $key;
    protected string $secret;
    protected string $webhookSecret;
    protected string $currency;
    protected string $successUrl;
    protected string $cancelUrl;
    protected string $apiBase = 'https://api.stripe.com/v1';

    /**
     * @var callable|null Mock transport for testing: fn(string $method, string $url, array $params, array $headers): array
     */
    protected static $mockTransport = null;

    public function __construct(array $config = [])
    {
        $this->key = (string) ($config['key'] ?? (function_exists('config') ? config('stripe.key') : null) ?? env('STRIPE_KEY', ''));
        $this->secret = (string) ($config['secret'] ?? (function_exists('config') ? config('stripe.secret') : null) ?? env('STRIPE_SECRET', ''));
        $this->webhookSecret = (string) ($config['webhook_secret'] ?? (function_exists('config') ? config('stripe.webhook_secret') : null) ?? env('STRIPE_WEBHOOK_SECRET', ''));
        $this->currency = strtolower((string) ($config['currency'] ?? (function_exists('config') ? config('stripe.currency') : null) ?? env('STRIPE_CURRENCY', 'usd')));
        $this->successUrl = (string) ($config['success_url'] ?? (function_exists('config') ? config('stripe.success_url') : null) ?? '');
        $this->cancelUrl = (string) ($config['cancel_url'] ?? (function_exists('config') ? config('stripe.cancel_url') : null) ?? '');
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Create a Stripe Checkout Session for hosted checkout.
     *
     * Params can include:
     * - amount: (float) total amount, e.g. 25.50
     * - currency: (string) e.g. 'usd'
     * - name: (string) item/order description
     * - customer_email: (string)
     * - success_url: (string)
     * - cancel_url: (string)
     * - metadata: (array)
     * - mode: (string) 'payment' or 'subscription' (default: 'payment')
     * - line_items: (array) optional custom items
     */
    public function createCheckoutSession(array $params): array
    {
        $this->ensureConfigured();

        $amount = (float) ($params['amount'] ?? 0);
        $currency = strtolower((string) ($params['currency'] ?? $this->currency));
        $name = (string) ($params['name'] ?? 'Payment');
        $successUrl = (string) ($params['success_url'] ?? $this->successUrl);
        $cancelUrl = (string) ($params['cancel_url'] ?? $this->cancelUrl);
        $mode = (string) ($params['mode'] ?? 'payment');

        if ($amount <= 0 && empty($params['line_items'])) {
            throw new InvalidArgumentException('Stripe checkout requires a positive amount or custom line_items.');
        }

        if (empty($successUrl) || empty($cancelUrl)) {
            throw new InvalidArgumentException('Stripe checkout requires success_url and cancel_url.');
        }

        // Build Stripe line_items if not explicitly provided
        $lineItems = $params['line_items'] ?? [
            [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $this->toZeroDecimalAmount($amount, $currency),
                    'product_data' => [
                        'name' => $name,
                    ],
                ],
                'quantity' => 1,
            ],
        ];

        $payload = [
            'mode' => $mode,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => $lineItems,
        ];

        if (!empty($params['customer_email'])) {
            $payload['customer_email'] = $params['customer_email'];
        }

        if (!empty($params['metadata']) && is_array($params['metadata'])) {
            $payload['metadata'] = $params['metadata'];
        }

        if (!empty($params['client_reference_id'])) {
            $payload['client_reference_id'] = (string) $params['client_reference_id'];
        }

        $response = $this->request('POST', '/checkout/sessions', $payload);

        // Record in database if available
        $this->recordPayment([
            'session_id'     => $response['id'] ?? null,
            'amount'         => $amount,
            'currency'       => $currency,
            'status'         => 'initiated',
            'customer_email' => $params['customer_email'] ?? null,
            'metadata'       => !empty($params['metadata']) ? json_encode($params['metadata']) : null,
        ]);

        return $response;
    }

    /**
     * Create a Payment Intent for custom UI / Stripe Elements integration.
     */
    public function createPaymentIntent(array $params): array
    {
        $this->ensureConfigured();

        $amount = (float) ($params['amount'] ?? 0);
        $currency = strtolower((string) ($params['currency'] ?? $this->currency));

        if ($amount <= 0) {
            throw new InvalidArgumentException('PaymentIntent requires a positive amount.');
        }

        $payload = [
            'amount' => $this->toZeroDecimalAmount($amount, $currency),
            'currency' => $currency,
            'payment_method_types' => $params['payment_method_types'] ?? ['card'],
        ];

        if (!empty($params['description'])) {
            $payload['description'] = $params['description'];
        }

        if (!empty($params['metadata']) && is_array($params['metadata'])) {
            $payload['metadata'] = $params['metadata'];
        }

        if (!empty($params['customer'])) {
            $payload['customer'] = $params['customer'];
        }

        if (!empty($params['receipt_email'])) {
            $payload['receipt_email'] = $params['receipt_email'];
        }

        $response = $this->request('POST', '/payment_intents', $payload);

        $this->recordPayment([
            'payment_intent_id' => $response['id'] ?? null,
            'amount'            => $amount,
            'currency'          => $currency,
            'status'            => $response['status'] ?? 'requires_payment_method',
            'customer_email'    => $params['receipt_email'] ?? null,
            'metadata'          => !empty($params['metadata']) ? json_encode($params['metadata']) : null,
        ]);

        return $response;
    }

    /**
     * Retrieve a Payment Intent by ID.
     */
    public function retrievePaymentIntent(string $id): array
    {
        $this->ensureConfigured();
        if (trim($id) === '') {
            throw new InvalidArgumentException('PaymentIntent ID cannot be empty.');
        }

        return $this->request('GET', '/payment_intents/' . urlencode($id));
    }

    /**
     * Retrieve a Checkout Session by ID.
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $this->ensureConfigured();
        if (trim($sessionId) === '') {
            throw new InvalidArgumentException('Checkout Session ID cannot be empty.');
        }

        return $this->request('GET', '/checkout/sessions/' . urlencode($sessionId));
    }

    /**
     * Issue a full or partial refund for a Payment Intent or Charge.
     */
    public function refund(string $paymentIntentId, ?float $amount = null, ?string $reason = null): array
    {
        $this->ensureConfigured();
        if (trim($paymentIntentId) === '') {
            throw new InvalidArgumentException('PaymentIntent ID is required for refund.');
        }

        $payload = ['payment_intent' => $paymentIntentId];
        if ($amount !== null && $amount > 0) {
            $payload['amount'] = $this->toZeroDecimalAmount($amount, $this->currency);
        }
        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        $response = $this->request('POST', '/refunds', $payload);

        // Update database record if exists
        $this->updatePaymentStatusByIntent($paymentIntentId, 'refunded');

        return $response;
    }

    /**
     * Cryptographically verify an incoming Stripe webhook signature header.
     *
     * Header format: t=1492774577,v1=5257a869e7ecebeda32affa62cd49231b2ec354b6890f65f4486ec6f7b0b982f
     *
     * @param string $payload Raw HTTP request body
     * @param string $sigHeader Value of Stripe-Signature HTTP header
     * @param string|null $secret Webhook signing secret (defaults to configured secret)
     * @param int $tolerance Max allowed age in seconds (defaults to 300)
     * @return array Decoded webhook event data
     */
    public function verifyWebhook(string $payload, string $sigHeader, ?string $secret = null, int $tolerance = 300): array
    {
        $secret = $secret ?? $this->webhookSecret;
        if (trim($secret) === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        if (trim($sigHeader) === '') {
            throw new InvalidArgumentException('Missing Stripe-Signature header.');
        }

        // Parse key-value pairs from header (t=..., v1=..., v1=...)
        $items = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) !== 2) {
                continue;
            }
            $k = trim($parts[0]);
            $v = trim($parts[1]);

            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }

        if ($timestamp === null || empty($signatures)) {
            throw new RuntimeException('Invalid Stripe-Signature header format.');
        }

        // Verify timestamp tolerance if tolerance > 0
        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            throw new RuntimeException('Stripe webhook timestamp is outside tolerance window.');
        }

        // Compute HMAC-SHA256 signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        $matched = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            throw new RuntimeException('Stripe webhook signature verification failed.');
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON payload in Stripe webhook.');
        }

        // Fire framework events
        $this->handleWebhookEvent($data);

        return $data;
    }

    /**
     * Dispatch framework events based on Stripe webhook event type.
     */
    protected function handleWebhookEvent(array $event): void
    {
        $type = $event['type'] ?? '';
        $obj = $event['data']['object'] ?? [];

        if (class_exists(Event::class)) {
            Event::dispatch('stripe.webhook', $event);
        }

        switch ($type) {
            case 'checkout.session.completed':
                $sessionId = $obj['id'] ?? null;
                $paymentIntentId = $obj['payment_intent'] ?? null;
                $status = ($obj['payment_status'] ?? '') === 'paid' ? 'paid' : 'completed';

                $this->updatePaymentStatusBySession($sessionId, $status, $paymentIntentId);

                if (class_exists(Event::class)) {
                    Event::dispatch('payment.success', [
                        'gateway'           => 'stripe',
                        'session_id'        => $sessionId,
                        'payment_intent_id' => $paymentIntentId,
                        'amount'            => isset($obj['amount_total']) ? ((float) $obj['amount_total']) / 100 : 0,
                        'currency'          => $obj['currency'] ?? 'usd',
                        'customer_email'    => $obj['customer_details']['email'] ?? null,
                        'raw'               => $obj,
                    ]);
                }
                break;

            case 'payment_intent.succeeded':
                $intentId = $obj['id'] ?? null;
                $this->updatePaymentStatusByIntent($intentId, 'succeeded');

                if (class_exists(Event::class)) {
                    Event::dispatch('payment.success', [
                        'gateway'           => 'stripe',
                        'payment_intent_id' => $intentId,
                        'amount'            => isset($obj['amount']) ? ((float) $obj['amount']) / 100 : 0,
                        'currency'          => $obj['currency'] ?? 'usd',
                        'customer_email'    => $obj['receipt_email'] ?? null,
                        'raw'               => $obj,
                    ]);
                }
                break;

            case 'payment_intent.payment_failed':
                $intentId = $obj['id'] ?? null;
                $this->updatePaymentStatusByIntent($intentId, 'failed');

                if (class_exists(Event::class)) {
                    Event::dispatch('payment.failed', [
                        'gateway'           => 'stripe',
                        'payment_intent_id' => $intentId,
                        'error'             => $obj['last_payment_error']['message'] ?? 'Payment failed',
                        'raw'               => $obj,
                    ]);
                }
                break;
        }
    }

    /**
     * Convert standard float amount to Stripe zero-decimal or cents integer.
     */
    public function toZeroDecimalAmount(float $amount, string $currency): int
    {
        // Zero-decimal currencies in Stripe (e.g. JPY, KRW, VND, BIF, DJF, GNF, etc.)
        $zeroDecimalCurrencies = [
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
            'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];

        if (in_array(strtolower($currency), $zeroDecimalCurrencies, true)) {
            return (int) round($amount);
        }

        // Standard currencies use cents (multiplied by 100)
        return (int) round($amount * 100);
    }

    /**
     * Internal HTTP request handler targeting Stripe API.
     */
    protected function request(string $method, string $endpoint, array $params = []): array
    {
        $url = $this->apiBase . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $this->secret,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2023-10-16',
            'User-Agent: Bongo-Stripe/1.0.0 (PHP ' . PHP_VERSION . ')',
        ];

        if (self::$mockTransport !== null) {
            $mockResponse = (self::$mockTransport)($method, $url, $params, $headers);
            if (isset($mockResponse['error'])) {
                $msg = $mockResponse['error']['message'] ?? 'Mock Stripe API error';
                throw new RuntimeException("Stripe API Error: {$msg}", 400);
            }
            return $mockResponse;
        }

        $ch = curl_init();
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } elseif ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || !empty($curlError)) {
            throw new RuntimeException("Stripe API network error: {$curlError}");
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid response from Stripe API (HTTP {$status}): {$body}");
        }

        if ($status >= 400 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? "HTTP error {$status}";
            throw new RuntimeException("Stripe API Error: {$msg}", $status);
        }

        return $data;
    }

    protected function ensureConfigured(): void
    {
        if (trim($this->secret) === '') {
            throw new InvalidArgumentException('Stripe secret key is not configured. Set STRIPE_SECRET in .env.');
        }
    }

    protected function recordPayment(array $data): void
    {
        if (!class_exists(\System\Database\DB::class)) {
            return;
        }

        try {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            \System\Database\DB::table('stripe_payments')->insert($data);
        } catch (\Throwable $e) {
            // Silently continue if table does not exist or db not connected
        }
    }

    protected function updatePaymentStatusBySession(?string $sessionId, string $status, ?string $paymentIntentId = null): void
    {
        if (!$sessionId || !class_exists(\System\Database\DB::class)) {
            return;
        }

        try {
            $updates = [
                'status'     => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($paymentIntentId !== null) {
                $updates['payment_intent_id'] = $paymentIntentId;
            }
            \System\Database\DB::table('stripe_payments')
                ->where('session_id', $sessionId)
                ->update($updates);
        } catch (\Throwable $e) {
            // Silently continue
        }
    }

    protected function updatePaymentStatusByIntent(?string $intentId, string $status): void
    {
        if (!$intentId || !class_exists(\System\Database\DB::class)) {
            return;
        }

        try {
            \System\Database\DB::table('stripe_payments')
                ->where('payment_intent_id', $intentId)
                ->update([
                    'status'     => $status,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $e) {
            // Silently continue
        }
    }

    /* -------------------------------------------------------------------------
     | Static Facade Methods
     | -------------------------------------------------------------------------
     */

    public static function checkout(array $params): array
    {
        return (new static())->createCheckoutSession($params);
    }

    public static function paymentIntent(array $params): array
    {
        return (new static())->createPaymentIntent($params);
    }

    public static function retrieve(string $paymentIntentId): array
    {
        return (new static())->retrievePaymentIntent($paymentIntentId);
    }

    public static function session(string $sessionId): array
    {
        return (new static())->retrieveCheckoutSession($sessionId);
    }

    public static function issueRefund(string $paymentIntentId, ?float $amount = null, ?string $reason = null): array
    {
        return (new static())->refund($paymentIntentId, $amount, $reason);
    }

    public static function verify(string $payload, string $sigHeader, ?string $secret = null, int $tolerance = 300): array
    {
        return (new static())->verifyWebhook($payload, $sigHeader, $secret, $tolerance);
    }
}
