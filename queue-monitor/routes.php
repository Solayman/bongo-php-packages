<?php

use Bongo\Packages\QueueMonitor\Controllers\QueueMonitorController;
use System\Routing\Route;

// Session + CSRF, and the configured access check (default: signed-in users; add a gate such as 'can:admin')
$middleware = array_merge(['web'], (array) config('queue-monitor.middleware', ['auth']));

Route::middleware($middleware)->group(function () {
    Route::get('/queue-monitor', [QueueMonitorController::class, 'index']);
    Route::get('/queue-monitor/api/stats', [QueueMonitorController::class, 'apiStats']);
    Route::post('/queue-monitor/retry/{id}', [QueueMonitorController::class, 'retry']);
    Route::post('/queue-monitor/retry-all', [QueueMonitorController::class, 'retryAll']);
    Route::post('/queue-monitor/delete/{id}', [QueueMonitorController::class, 'delete']);
    Route::post('/queue-monitor/flush', [QueueMonitorController::class, 'flush']);
});
