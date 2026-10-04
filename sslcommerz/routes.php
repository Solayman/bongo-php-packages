<?php

use Bongo\Packages\Sslcommerz\SSLCommerz;
use System\Http\Request;
use System\Http\Response;
use System\Routing\Route;

// Default callback handlers provided by SSLCommerz package
Route::post('/sslcommerz/success', function (Request $request) {
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
});

Route::post('/sslcommerz/fail', function (Request $request) {
    return view('sslcommerz::fail', [
        'tran_id' => $request->get('tran_id', ''),
    ]);
});

Route::post('/sslcommerz/cancel', function (Request $request) {
    return view('sslcommerz::cancel', [
        'tran_id' => $request->get('tran_id', ''),
    ]);
});

Route::post('/sslcommerz/ipn', function (Request $request) {
    try {
        $gateway = new SSLCommerz();
        $verified = $gateway->verify($request->all());
        return Response::json(['status' => 'OK', 'verified' => $verified['valid']]);
    } catch (Throwable $e) {
        return Response::json(['status' => 'ERROR', 'message' => $e->getMessage()], 400);
    }
});
