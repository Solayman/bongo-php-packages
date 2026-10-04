<?php

use Bongo\Packages\ApiTools\Controllers\ApiDocsController;
use System\Routing\Route;

$docsEnabled = function_exists('config') ? (bool) config('api.docs_enabled', true) : true;

if ($docsEnabled) {
    $jsonRoute = function_exists('config') ? (string) config('api.json_route', '/api/openapi.json') : '/api/openapi.json';
    $docsRoute = function_exists('config') ? (string) config('api.docs_route', '/api/docs') : '/api/docs';
    $redocRoute = function_exists('config') ? (string) config('api.redoc_route', '/api/redoc') : '/api/redoc';

    Route::get($jsonRoute, [ApiDocsController::class, 'json'])->name('api.openapi.json');
    Route::get($docsRoute, [ApiDocsController::class, 'swagger'])->name('api.docs');
    Route::get($redocRoute, [ApiDocsController::class, 'redoc'])->name('api.redoc');
}
