<?php

use Bongo\Packages\Sms\Controllers\OTPController;
use System\Routing\Route;

// Default OTP Authentication Routes provided by SMS package
// Session + CSRF; per-IP limits stop SMS pumping (sending to many numbers at your cost) and guessing
Route::middleware(['web'])->group(function () {
    Route::get('/otp/login', [OTPController::class, 'showLoginForm'])->name('otp.login');
    Route::post('/otp/send', [OTPController::class, 'sendOtp'])->name('otp.send')
        ->middleware('throttle:' . (int) config('sms.otp.send_per_minute', 3) . ',1');
    Route::get('/otp/verify', [OTPController::class, 'showVerifyForm'])->name('otp.verify.form');
    Route::post('/otp/verify', [OTPController::class, 'verifyOtp'])->name('otp.verify')
        ->middleware('throttle:' . (int) config('sms.otp.verify_per_minute', 10) . ',1');
});
