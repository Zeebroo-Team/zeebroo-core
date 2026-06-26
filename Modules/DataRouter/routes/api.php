<?php

use Illuminate\Support\Facades\Route;
use Modules\DataRouter\Http\Controllers\Api\DataRouterConfigApiController;

Route::middleware(['auth:sanctum'])->prefix('v1/data-router')->name('data-router.')->group(function (): void {
    Route::get   ('config',        [DataRouterConfigApiController::class, 'show']   )->name('config.show');
    Route::put   ('config',        [DataRouterConfigApiController::class, 'upsert'] )->name('config.upsert');
    Route::patch ('config',        [DataRouterConfigApiController::class, 'upsert'] )->name('config.upsert.patch');
    Route::delete('config',        [DataRouterConfigApiController::class, 'destroy'])->name('config.destroy');
    Route::post  ('config/health', [DataRouterConfigApiController::class, 'health'] )->name('config.health');
});
