<?php

use Illuminate\Support\Facades\Route;
use Modules\AppConnection\Http\Controllers\AppConnectionController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('settings/app-connections', [AppConnectionController::class, 'index'])->name('app-connection.index');
    Route::get('settings/app-connections/google/redirect', [AppConnectionController::class, 'redirectGoogle'])->name('app-connection.google.redirect');
    Route::get('settings/app-connections/google/callback', [AppConnectionController::class, 'callbackGoogle'])->name('app-connection.google.callback');
    Route::post('settings/app-connections/google/disconnect', [AppConnectionController::class, 'disconnectGoogle'])->name('app-connection.google.disconnect');
});
