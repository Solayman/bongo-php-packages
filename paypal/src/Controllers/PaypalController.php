<?php

declare(strict_types=1);

namespace Bongo\Packages\Paypal\Controllers;

use Bongo\Packages\Paypal\Paypal;
use System\Http\Request;
use Throwable;

class PaypalController
{
    /**
     * Initiate PayPal Checkout order and redirect to PayPal approval URL.
     */
    public function checkout(Request $request): string
    {
        // The browser chooses amount and order here: demo/testing only (PAYPAL_DEMO_CHECKOUT=true).
        // In an app, call Paypal::create() from your controller with the order loaded on the server.
        if (!config('paypal.demo_checkout', false)) {
            abort(404);
        }

        $amount = (float) $request->input('amount', 0);
        $currency = (string) $request->input('currency', 'USD');
        $description = (string) $request->input('description', 'Order Payment');
        $referenceId = (string) $request->input('reference_id', 'ORD_' . bin2hex(random_bytes(6)));
        $customId = (string) $request->input('custom_id', '');

        try {
            $order = Paypal::create([
                'amount'       => $amount,
                'currency'     => $currency,
                'description'  => $description,
                'reference_id' => $referenceId,
                'custom_id'    => $customId,
            ]);

            $approvalUrl = Paypal::approvalUrl($order);
            if (!empty($approvalUrl)) {
                if (!headers_sent()) {
                    header('Location: ' . $approvalUrl);
                }
                return '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($approvalUrl, ENT_QUOTES, 'UTF-8') . '">';
            }

            return function_exists('view') ? view('paypal::cancel') : 'Cancelled';
        } catch (Throwable $e) {
            return function_exists('view')
                ? view('paypal::cancel', ['error' => 'The payment could not be started. Please try again.'])
                : 'The payment could not be started.';
        }
    }

    /**
     * Display payment success page upon customer return after PayPal approval.
     */
    public function success(Request $request): string
    {
        // PayPal passes order ID as 'token' query param
        $orderId = (string) ($request->input('token') ?? $request->input('order_id') ?? '');
        $orderId = preg_match('/^[A-Za-z0-9-]{1,64}$/', $orderId) ? $orderId : '';
        $payerId = (string) $request->input('PayerID', '');
        $captureId = '';
        $amount = 0.0;
        $currency = 'USD';
        $status = 'COMPLETED';

        if ($orderId !== '') {
            try {
                $captureResult = Paypal::capture($orderId);
                $status = (string) ($captureResult['status'] ?? 'COMPLETED');
                $captures = $captureResult['purchase_units'][0]['payments']['captures'] ?? [];
                if (!empty($captures)) {
                    $captureId = (string) ($captures[0]['id'] ?? '');
                    $amount = (float) ($captures[0]['amount']['value'] ?? 0);
                    $currency = (string) ($captures[0]['amount']['currency_code'] ?? 'USD');
                }
            } catch (Throwable $e) {
                // If already captured or offline mock
                $status = 'PROCESSED';
            }
        }

        return function_exists('view')
            ? view('paypal::success', [
                'order_id'   => $orderId,
                'capture_id' => $captureId,
                'payer_id'   => $payerId,
                'amount'     => $amount,
                'currency'   => $currency,
                'status'     => $status,
            ])
            : "Success! Order: {$orderId}";
    }

    /**
     * Display payment cancellation page.
     */
    public function cancel(Request $request): string
    {
        $orderId = (string) ($request->input('token') ?? $request->input('order_id') ?? '');
        $orderId = preg_match('/^[A-Za-z0-9-]{1,64}$/', $orderId) ? $orderId : '';
        return function_exists('view')
            ? view('paypal::cancel', ['order_id' => $orderId])
            : 'Payment cancelled';
    }

    /**
     * Handle incoming PayPal webhook notifications.
     */
    public function webhook(Request $request): string
    {
        $rawPayload = file_get_contents('php://input');
        if ($rawPayload === false || $rawPayload === '') {
            $rawPayload = (string) ($request->rawBody ?? '');
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }

        try {
            $paypal = new Paypal();
            $paypal->handleWebhook($headers, $rawPayload);
            http_response_code(200);
            return json_encode(['status' => 'success']);
        } catch (Throwable $e) {
            http_response_code(400);
            return json_encode(['error' => $e->getMessage()]);
        }
    }
}
