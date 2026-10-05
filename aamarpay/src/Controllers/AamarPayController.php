<?php

declare(strict_types=1);

namespace Bongo\Packages\Aamarpay\Controllers;

use Bongo\Packages\Aamarpay\AamarPay;
use System\Http\Request;
use System\Http\Response;

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
        $tranId = (string) ($request->input('mer_txnid') ?: $request->input('tran_id') ?: '');
        $status = 'PENDING';
        $verified = false;

        if ($tranId !== '') {
            try {
                $verification = $this->gateway->verify($tranId);
                $verified = $verification['success'] ?? false;
                $status = $verification['status'] ?? 'SUCCESS';
            } catch (\Throwable $e) {
                $status = 'VERIFICATION_ERROR';
            }
        }

        return view('aamarpay::success', [
            'tran_id'  => $tranId,
            'verified' => $verified,
            'status'   => $status,
            'amount'   => $request->input('amount'),
            'currency' => $request->input('currency', 'BDT'),
        ]);
    }

    public function fail(Request $request): string
    {
        $tranId = (string) ($request->input('mer_txnid') ?: $request->input('tran_id') ?: '');
        $reason = (string) ($request->input('error_title') ?: 'Payment failed or declined by card issuer.');

        return view('aamarpay::fail', [
            'tran_id' => $tranId,
            'reason'  => $reason,
        ]);
    }

    public function cancel(Request $request): string
    {
        $tranId = (string) ($request->input('mer_txnid') ?: $request->input('tran_id') ?: '');

        return view('aamarpay::cancel', [
            'tran_id' => $tranId,
        ]);
    }
}
