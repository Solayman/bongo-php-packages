<?php

use Bongo\Packages\Impersonate\Controllers\ImpersonateController;
use System\Routing\Route;

// Session + CSRF + a signed-in user; who may impersonate whom is decided by Impersonate::allowed()
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/impersonate/leave', [ImpersonateController::class, 'leave']);
    Route::post('/impersonate/{id}', [ImpersonateController::class, 'take'])->where('id', '\d+');
});
