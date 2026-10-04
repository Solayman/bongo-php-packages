<?php

use Bongo\Packages\Sslcommerz\Controllers\SSLCommerzController;
use System\Routing\Route;

// Default callback handlers provided by SSLCommerz package
Route::post('/sslcommerz/success', [SSLCommerzController::class, 'success'])->name('sslcommerz.success');
Route::post('/sslcommerz/fail', [SSLCommerzController::class, 'fail'])->name('sslcommerz.fail');
Route::post('/sslcommerz/cancel', [SSLCommerzController::class, 'cancel'])->name('sslcommerz.cancel');
Route::post('/sslcommerz/ipn', [SSLCommerzController::class, 'ipn'])->name('sslcommerz.ipn');
