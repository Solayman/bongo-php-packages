<?php

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
                'tran_id' => $request->get('tran_id', ''),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle failed payment callback.
     */
    public function fail(Request $request): string
    {
        return view('sslcommerz::fail', [
            'tran_id' => $request->get('tran_id', ''),
            'error' => $request->get('error', 'Payment failed'),
        ]);
    }

    /**
     * Handle cancelled payment callback.
     */
    public function cancel(Request $request): string
    {
        return view('sslcommerz::cancel', [
            'tran_id' => $request->get('tran_id', ''),
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
}
