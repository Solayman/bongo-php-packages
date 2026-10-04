<?php

namespace Bongo\Packages\Bkash\Controllers;

use Bongo\Packages\Bkash\BKash;
use System\Http\Request;
use Throwable;

class BKashController
{
    /**
     * Handle bKash redirect callback after customer interaction.
     */
    public function callback(Request $request): string
    {
        $paymentId = (string) $request->get('paymentID', $request->get('payment_id', ''));
        $status = strtolower((string) $request->get('status', ''));

        if ($status === 'cancel') {
            return view('bkash::cancel', [
                'payment_id' => $paymentId,
            ]);
        }

        if ($status === 'failure') {
            return view('bkash::fail', [
                'payment_id' => $paymentId,
                'error'      => $request->get('statusMessage', 'Payment was declined or failed'),
            ]);
        }

        try {
            $gateway = new BKash();
            $verified = $gateway->verify($request->all());

            if ($verified['valid']) {
                return view('bkash::success', $verified);
            }

            return view('bkash::fail', $verified);
        } catch (Throwable $e) {
            return view('bkash::fail', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle Instant Payment Notification (IPN).
     */
    public function ipn(Request $request): array
    {
        try {
            $gateway = new BKash();
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
