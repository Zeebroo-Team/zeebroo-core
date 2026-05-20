<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\SettingsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/user', [SettingsController::class, 'user'])->name('settings.user');
    Route::get('settings/business', [SettingsController::class, 'business'])->name('settings.business');
    Route::post('settings', [SettingsController::class, 'store'])->name('settings.store');
    Route::post('settings/bulk', [SettingsController::class, 'bulkStore'])->name('settings.bulk.store');
    Route::delete('settings', [SettingsController::class, 'destroy'])->name('settings.destroy');
});
