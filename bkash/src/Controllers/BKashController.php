<?php

declare(strict_types=1);

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

        try {
            // verify() asks bKash for the real state; the "status" in this URL is only a hint
            $verified = (new BKash())->verify($request->all());
        } catch (Throwable $e) {
            return view('bkash::fail', [
                'payment_id' => preg_match('/^[A-Za-z0-9_-]{1,100}$/', $paymentId) ? $paymentId : '',
                'error'      => __('bkash::We could not verify your bKash payment. Please try again or use another payment method.'),
            ]);
        }

        if ($verified['valid']) {
            return view('bkash::success', $verified);
        }
        if ($verified['status'] === 'CANCELLED') {
            return view('bkash::cancel', ['payment_id' => $verified['payment_id']]);
        }

        // Fixed wording: never show text taken from the URL (anyone can craft the link)
        return view('bkash::fail', [
            'payment_id' => $verified['payment_id'],
            'error'      => __('bkash::We could not verify your bKash payment. Please try again or use another payment method.'),
        ]);
    }

    /**
     * Handle Instant Payment Notification (IPN).
     */
    public function ipn(Request $request): array
    {
        try {
            // Server-to-server: only the payment id is used, the state always comes from bKash's API
            $paymentId = (string) $request->get('paymentID', $request->get('payment_id', ''));
            $verified = (new BKash())->verify(['paymentID' => $paymentId]);
            return ['status' => 'OK', 'verified' => $verified['valid']];
        } catch (Throwable $e) {
            if (!headers_sent()) {
                http_response_code(400);
            }
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
}
