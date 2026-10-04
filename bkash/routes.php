<?php

use Bongo\Packages\Bkash\Controllers\BKashController;
use System\Routing\Route;

// Default callback handlers provided by bKash package
Route::any('/bkash/callback', [BKashController::class, 'callback'])->name('bkash.callback');
Route::post('/bkash/ipn', [BKashController::class, 'ipn'])->name('bkash.ipn');
