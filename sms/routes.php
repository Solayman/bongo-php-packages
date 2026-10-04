<?php

use Bongo\Packages\Sms\Controllers\OTPController;
use System\Routing\Route;

// Default OTP Authentication Routes provided by SMS package
Route::get('/otp/login', [OTPController::class, 'showLoginForm'])->name('otp.login');
Route::post('/otp/send', [OTPController::class, 'sendOtp'])->name('otp.send');
Route::get('/otp/verify', [OTPController::class, 'showVerifyForm'])->name('otp.verify.form');
Route::post('/otp/verify', [OTPController::class, 'verifyOtp'])->name('otp.verify');
