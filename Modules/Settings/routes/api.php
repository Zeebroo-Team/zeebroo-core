<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\SettingsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.api.index');
    Route::post('settings', [SettingsController::class, 'store'])->name('settings.api.store');
    Route::delete('settings', [SettingsController::class, 'destroy'])->name('settings.api.destroy');
});
