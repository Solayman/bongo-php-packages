<?php

use Bongo\Packages\SocialLogin\Controllers\OAuthController;
use System\Routing\Route;

Route::get('/auth/{provider}', [OAuthController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('social.callback');
