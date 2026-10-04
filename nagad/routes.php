<?php

use Bongo\Packages\Nagad\Controllers\NagadController;
use System\Routing\Route;

// Default payment and callback handlers provided by Nagad package
Route::post('/nagad/pay', [NagadController::class, 'pay'])->name('nagad.pay');
Route::any('/nagad/callback', [NagadController::class, 'callback'])->name('nagad.callback');
