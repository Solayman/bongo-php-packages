<?php

use Bongo\Packages\Paypal\Controllers\PaypalController;
use System\Routing\Route;

Route::post('/paypal/checkout', [PaypalController::class, 'checkout'])->name('paypal.checkout');
Route::get('/paypal/success', [PaypalController::class, 'success'])->name('paypal.success');
Route::get('/paypal/cancel', [PaypalController::class, 'cancel'])->name('paypal.cancel');
Route::post('/paypal/webhook', [PaypalController::class, 'webhook'])->name('paypal.webhook');
