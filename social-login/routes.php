<?php

use Bongo\Packages\SocialLogin\Controllers\OAuthController;
use System\Routing\Route;

// Session middleware: the OAuth state check and the sign-in both need the session
Route::middleware(['web'])->group(function () {
    Route::get('/auth/{provider}', [OAuthController::class, 'redirect'])->name('social.redirect')->where('provider', '[a-z0-9_-]+');
    Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('social.callback')->where('provider', '[a-z0-9_-]+');
});
