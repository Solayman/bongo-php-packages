<?php

use Bongo\Packages\Aamarpay\Controllers\AamarPayController;
use System\Routing\Route;

Route::get('/aamarpay/checkout', [AamarPayController::class, 'checkout'])->name('aamarpay.checkout');
Route::post('/aamarpay/success', [AamarPayController::class, 'success'])->name('aamarpay.success');
Route::post('/aamarpay/fail', [AamarPayController::class, 'fail'])->name('aamarpay.fail');
Route::post('/aamarpay/cancel', [AamarPayController::class, 'cancel'])->name('aamarpay.cancel');
