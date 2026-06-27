<?php

use Illuminate\Support\Facades\Route;
use Modules\AppConnection\Http\Controllers\Api\AppReleaseApiController;

// Public — authenticated by X-Api-Key header inside the controller
Route::prefix('releases')->name('releases.')->group(function (): void {
    Route::post  ('',       [AppReleaseApiController::class, 'store'] )->name('store');
    Route::get   ('',       [AppReleaseApiController::class, 'index'] )->name('index');
    Route::get   ('latest', [AppReleaseApiController::class, 'latest'])->name('latest');
});
