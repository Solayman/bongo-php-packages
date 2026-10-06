<?php

declare(strict_types=1);

namespace Bongo\Packages\Stripe\Controllers;

use Bongo\Packages\Stripe\Stripe;
use System\Http\Request;
use Throwable;

class StripeController
{
    /**
     * Initiate Stripe Checkout Session and redirect to Stripe-hosted payment page.
     */
    public function checkout(Request $request): string
    {
        // The browser chooses amount and order here: demo/testing only (STRIPE_DEMO_CHECKOUT=true).
        // In an app, call Stripe::checkout() from your controller with the order loaded on the server.
        if (!config('stripe.demo_checkout', false)) {
            abort(404);
        }

        $amount = (float) $request->input('amount', 0);
        $currency = (string) $request->input('currency', 'usd');
        $name = (string) $request->input('name', 'Order Payment');
        $orderId = (string) $request->input('order_id', 'ORD_' . bin2hex(random_bytes(6)));
        $email = $request->input('customer_email');

        try {
            $session = Stripe::checkout([
                'amount'               => $amount,
                'currency'             => $currency,
                'name'                 => $name,
                'customer_email'       => $email,
                'client_reference_id'  => $orderId,
                'metadata'             => [
                    'order_id' => $orderId,
                ],
            ]);

            if (!empty($session['url'])) {
                header('Location: ' . $session['url']);
                return '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($session['url'], ENT_QUOTES, 'UTF-8') . '">';
            }

            return view('stripe::cancel');
        } catch (Throwable $e) {
            return view('stripe::cancel', [
                'error' => 'The payment could not be started. Please try again.',
            ]);
        }
    }

    /**
     * Display payment success page upon customer return.
     */
    public function success(Request $request): string
    {
        $sessionId = (string) $request->input('session_id', '');
        $paymentIntentId = '';
        $amount = 0;
        $currency = 'usd';

        if ($sessionId !== '') {
            try {
                $session = Stripe::session($sessionId);
                $paymentIntentId = (string) ($session['payment_intent'] ?? '');
                $amount = isset($session['amount_total']) ? ((float) $session['amount_total']) / 100 : 0;
                $currency = (string) ($session['currency'] ?? 'usd');
            } catch (Throwable $e) {
                // If retrieve fails (e.g. offline/mock), use session ID directly
            }
        }

        return view('stripe::success', [
            'session_id'        => $sessionId,
            'payment_intent_id' => $paymentIntentId,
            'amount'            => $amount,
            'currency'          => $currency,
        ]);
    }

    /**
     * Display payment cancellation page.
     */
    public function cancel(Request $request): string
    {
        return view('stripe::cancel');
    }

    /**
     * Handle incoming Stripe webhook notifications.
     */
    public function webhook(Request $request): string
    {
        $payload = file_get_contents('php://input');
        if ($payload === false || $payload === '') {
            $payload = (string) ($request->rawBody ?? '');
        }

        $sigHeader = (string) (
            $_SERVER['HTTP_STRIPE_SIGNATURE'] ??
            $request->header('Stripe-Signature') ??
            ''
        );

        try {
            $event = Stripe::verify($payload, $sigHeader);

            http_response_code(200);
            header('Content-Type: application/json');
            return json_encode([
                'received' => true,
                'type'     => $event['type'] ?? 'unknown',
            ], JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            return json_encode([
                'error'   => 'Webhook Error',
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }
    }
}
