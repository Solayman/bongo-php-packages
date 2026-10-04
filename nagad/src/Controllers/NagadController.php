<?php

namespace Bongo\Packages\Nagad\Controllers;

use Bongo\Packages\Nagad\Nagad;
use System\Http\Request;
use Throwable;

class NagadController
{
    /**
     * Initiate payment and redirect customer to Nagad payment portal.
     */
    public function pay(Request $request): string
    {
        $orderId = (string) $request->input('order_id', uniqid('ORD_'));
        $amount = (float) $request->input('amount', 0);

        try {
            $gateway = new Nagad();
            $res = $gateway->createPayment([
                'order_id' => $orderId,
                'amount'   => $amount,
            ]);

            if (!empty($res['callBackUrl'])) {
                header('Location: ' . $res['callBackUrl']);
                exit;
            }

            return view('nagad::fail', [
                'order_id' => $orderId,
                'error'    => 'Nagad did not return a valid payment URL.',
            ]);
        } catch (Throwable $e) {
            return view('nagad::fail', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle Nagad redirect callback after customer interaction.
     */
    public function callback(Request $request): string
    {
        $status = strtolower((string) $request->input('status', ''));
        $paymentRefId = (string) (
            $request->input('payment_ref_id') ??
            $request->input('paymentReferenceId') ??
            ''
        );

        if ($status === 'cancel' || $status === 'cancelled') {
            return view('nagad::cancel', [
                'payment_ref_id' => $paymentRefId,
            ]);
        }

        if ($status === 'aborted') {
            return view('nagad::fail', [
                'payment_ref_id' => $paymentRefId,
                'error'          => 'Payment was aborted by user or session expired.',
            ]);
        }

        try {
            $gateway = new Nagad();
            $verified = $gateway->verify($request->all());

            if (!empty($verified['valid'])) {
                return view('nagad::success', $verified);
            }

            return view('nagad::fail', $verified);
        } catch (Throwable $e) {
            return view('nagad::fail', [
                'payment_ref_id' => $paymentRefId,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
