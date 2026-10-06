<?php

use Bongo\Packages\ApiTools\Controllers\ApiDocsController;
use System\Routing\Route;

$docsEnabled = function_exists('config') ? (bool) config('api.docs_enabled', true) : true;

if ($docsEnabled) {
    $jsonRoute = function_exists('config') ? (string) config('api.json_route', '/docs/openapi.json') : '/docs/openapi.json';
    $docsRoute = function_exists('config') ? (string) config('api.docs_route', '/api/docs') : '/api/docs';
    $redocRoute = function_exists('config') ? (string) config('api.redoc_route', '/api/redoc') : '/api/redoc';

    Route::get($jsonRoute, [ApiDocsController::class, 'json'])->name('api.openapi.json');
    if ($jsonRoute !== '/api/openapi.json') {
        Route::get('/api/openapi.json', [ApiDocsController::class, 'json']);
    }
    if ($jsonRoute !== '/docs/openapi.json') {
        Route::get('/docs/openapi.json', [ApiDocsController::class, 'json']);
    }
    Route::get($docsRoute, [ApiDocsController::class, 'swagger'])->name('api.docs');
    Route::get($redocRoute, [ApiDocsController::class, 'redoc'])->name('api.redoc');
}
