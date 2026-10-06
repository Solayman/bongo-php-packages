<?php

declare(strict_types=1);

namespace Bongo\Packages\Nagad\Controllers;

use Bongo\Packages\Nagad\Nagad;
use System\Http\Request;
use Throwable;

class NagadController
{
    /**
     * Demo checkout: the browser sends order_id and amount, so it is OFF unless
     * NAGAD_DEMO_CHECKOUT=true. In an app, call Nagad::createPayment() from your own
     * controller with the amount of the order loaded on the server.
     */
    public function pay(Request $request): string
    {
        if (!config('nagad.demo_checkout', false)) {
            abort(404);
        }

        $orderId = (string) $request->input('order_id', 'ORD_' . bin2hex(random_bytes(6)));
        $amount = (float) $request->input('amount', 0);

        try {
            $res = (new Nagad())->createPayment([
                'order_id' => $orderId,
                'amount'   => $amount,
            ]);

            if (!empty($res['callBackUrl']) && preg_match('#^https://#', (string) $res['callBackUrl'])) {
                redirect((string) $res['callBackUrl']);
            }

            return view('nagad::fail', [
                'order_id' => $orderId,
                'error'    => 'Nagad did not return a valid payment URL.',
            ]);
        } catch (Throwable $e) {
            return view('nagad::fail', [
                'order_id' => $orderId,
                'error'    => 'The payment could not be started. Please try again.',
            ]);
        }
    }

    /**
     * Handle Nagad redirect callback after customer interaction.
     * Always verified with Nagad's API; the "status" in the URL only picks the wording.
     */
    public function callback(Request $request): string
    {
        $paymentRefId = (string) ($request->input('payment_ref_id') ?? $request->input('paymentReferenceId') ?? '');
        $safeRef = preg_match('/^[A-Za-z0-9_-]{1,100}$/', $paymentRefId) ? $paymentRefId : '';

        try {
            $verified = $safeRef !== '' ? (new Nagad())->verify(['payment_ref_id' => $safeRef]) : ['valid' => false, 'status' => 'FAILED'];
        } catch (Throwable $e) {
            $verified = ['valid' => false, 'status' => 'FAILED'];
        }

        if (!empty($verified['valid'])) {
            return view('nagad::success', $verified);
        }

        $hint = strtolower((string) $request->input('status', ''));
        if (($verified['status'] ?? '') === 'CANCELLED' || $hint === 'cancel' || $hint === 'cancelled') {
            return view('nagad::cancel', ['payment_ref_id' => $safeRef]);
        }

        return view('nagad::fail', [
            'payment_ref_id' => $safeRef,
            'error'          => $hint === 'aborted'
                ? 'Payment was aborted by user or session expired.'
                : 'We could not verify your Nagad payment. Please try again or use another payment method.',
        ]);
    }
}
