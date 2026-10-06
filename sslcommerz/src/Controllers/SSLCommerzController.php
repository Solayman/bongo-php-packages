<?php

declare(strict_types=1);

namespace Bongo\Packages\Sslcommerz\Controllers;

use Bongo\Packages\Sslcommerz\SSLCommerz;
use System\Http\Request;
use Throwable;

class SSLCommerzController
{
    /**
     * Handle successful payment callback.
     */
    public function success(Request $request): string
    {
        try {
            $gateway = new SSLCommerz();
            $verified = $gateway->verify($request->all());

            if ($verified['valid']) {
                return view('sslcommerz::success', $verified);
            }
            return view('sslcommerz::fail', $verified);
        } catch (Throwable $e) {
            return view('sslcommerz::fail', [
                'tran_id' => $this->safeId($request->get('tran_id', '')),
                'error' => 'We could not verify your payment. Please try again or contact us.',
            ]);
        }
    }

    /**
     * Handle failed payment callback.
     */
    public function fail(Request $request): string
    {
        // Fixed wording: anyone can post to this URL, so its text is never shown back
        return view('sslcommerz::fail', [
            'tran_id' => $this->safeId($request->get('tran_id', '')),
            'error' => 'Payment failed',
        ]);
    }

    /**
     * Handle cancelled payment callback.
     */
    public function cancel(Request $request): string
    {
        return view('sslcommerz::cancel', [
            'tran_id' => $this->safeId($request->get('tran_id', '')),
        ]);
    }

    /**
     * Handle Instant Payment Notification (IPN).
     */
    public function ipn(Request $request): array
    {
        try {
            $gateway = new SSLCommerz();
            $verified = $gateway->verify($request->all());
            return ['status' => 'OK', 'verified' => $verified['valid']];
        } catch (Throwable $e) {
            if (!headers_sent()) {
                http_response_code(400);
            }
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    protected function safeId(mixed $value): string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $value) ? $value : '';
    }
}
