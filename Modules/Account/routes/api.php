<?php

use Illuminate\Support\Facades\Route;
use Modules\Account\Http\Controllers\AccountController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('accounts', AccountController::class)->names('account');
});
