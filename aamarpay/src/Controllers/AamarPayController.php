<?php

declare(strict_types=1);

namespace Bongo\Packages\Aamarpay\Controllers;

use Bongo\Packages\Aamarpay\AamarPay;
use System\Http\Request;

class AamarPayController
{
    protected AamarPay $gateway;

    public function __construct()
    {
        $this->gateway = new AamarPay();
    }

    public function checkout(Request $request): string
    {
        return view('aamarpay::checkout', [
            'store_id' => $this->gateway->getStoreId(),
            'sandbox'  => $this->gateway->isSandbox(),
        ]);
    }

    public function success(Request $request): string
    {
        $tranId = $this->safeId($request->input('mer_txnid') ?: $request->input('tran_id') ?: '');
        $status = 'PENDING';
        $verified = false;
        $amount = null;
        $currency = 'BDT';

        if ($tranId !== '') {
            try {
                // Only AamarPay's own answer counts, never the posted pay_status or amount
                $verification = $this->gateway->verify($tranId);
                $verified = $verification['success'] ?? false;
                $status = $verification['status'] ?? 'PENDING';
                $amount = $verification['amount'] ?? null;
                $currency = $verification['currency'] ?? 'BDT';
            } catch (\Throwable $e) {
                $status = 'VERIFICATION_ERROR';
            }
        }

        return view('aamarpay::success', [
            'tran_id'  => $tranId,
            'verified' => $verified,
            'status'   => $status,
            'amount'   => $amount,
            'currency' => $currency,
        ]);
    }

    public function fail(Request $request): string
    {
        $tranId = $this->safeId($request->input('mer_txnid') ?: $request->input('tran_id') ?: '');
        // Fixed wording: anyone can post to this URL, so its text is never shown back
        $reason = 'Payment failed or declined by card issuer.';

        return view('aamarpay::fail', [
            'tran_id' => $tranId,
            'reason'  => $reason,
        ]);
    }

    public function cancel(Request $request): string
    {
        $tranId = $this->safeId($request->input('mer_txnid') ?: $request->input('tran_id') ?: '');

        return view('aamarpay::cancel', [
            'tran_id' => $tranId,
        ]);
    }

    protected function safeId(mixed $value): string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $value) ? $value : '';
    }
}
