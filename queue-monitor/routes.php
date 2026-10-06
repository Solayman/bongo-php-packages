<?php

use Bongo\Packages\QueueMonitor\Controllers\QueueMonitorController;
use System\Routing\Router;

Router::get('/queue-monitor', [QueueMonitorController::class, 'index']);
Router::get('/queue-monitor/api/stats', [QueueMonitorController::class, 'apiStats']);
Router::post('/queue-monitor/retry/{id}', [QueueMonitorController::class, 'retry']);
Router::post('/queue-monitor/retry-all', [QueueMonitorController::class, 'retryAll']);
Router::post('/queue-monitor/delete/{id}', [QueueMonitorController::class, 'delete']);
Router::post('/queue-monitor/flush', [QueueMonitorController::class, 'flush']);
