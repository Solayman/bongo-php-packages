<?php

declare(strict_types=1);

use Bongo\Packages\Health\Controllers\HealthController;
use System\Routing\Route;

Route::get('/health', [HealthController::class, 'check']);
Route::get('/api/health', [HealthController::class, 'check']);
