<?php

use Illuminate\Support\Facades\Route;
use Modules\Business\Http\Controllers\BusinessController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('businesses', BusinessController::class)->names('business');
});
