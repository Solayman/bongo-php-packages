<?php

declare(strict_types=1);

use Bongo\Packages\FileManager\Controllers\FileManagerController;
use System\Routing\Route;

$prefix = function_exists('config') ? (string) config('filemanager.route_prefix', 'filemanager') : 'filemanager';
$middleware = function_exists('config') ? (array) config('filemanager.middleware', ['web']) : ['web'];

Route::middleware($middleware)->group(function () use ($prefix) {
    Route::get("/{$prefix}", [FileManagerController::class, 'index']);
    Route::get("/{$prefix}/items", [FileManagerController::class, 'items']);
    Route::get("/{$prefix}/tree", [FileManagerController::class, 'tree']);
    Route::post("/{$prefix}/upload", [FileManagerController::class, 'upload']);
    Route::post("/{$prefix}/folder", [FileManagerController::class, 'folder']);
    Route::post("/{$prefix}/delete", [FileManagerController::class, 'delete']);
    Route::post("/{$prefix}/rename", [FileManagerController::class, 'rename']);
    Route::post("/{$prefix}/move", [FileManagerController::class, 'move']);
    Route::post("/{$prefix}/copy", [FileManagerController::class, 'copy']);
    Route::get("/{$prefix}/read", [FileManagerController::class, 'read']);
    Route::post("/{$prefix}/save", [FileManagerController::class, 'save']);
    Route::get("/{$prefix}/download", [FileManagerController::class, 'download']);
    Route::get("/{$prefix}/thumb", [FileManagerController::class, 'thumbnail']);
    Route::get("/{$prefix}/script", [FileManagerController::class, 'script']);
});
