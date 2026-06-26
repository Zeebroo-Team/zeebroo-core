<?php

use Illuminate\Support\Facades\Route;
use Modules\DataRouter\Http\Controllers\Web\DataVaultSettingsController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get ('settings/data-vault',                  [DataVaultSettingsController::class, 'index']          )->name('data-vault.settings');
    Route::post('settings/data-vault',                  [DataVaultSettingsController::class, 'save']           )->name('data-vault.settings.save');
    Route::post('settings/data-vault/disconnect',       [DataVaultSettingsController::class, 'disconnect']     )->name('data-vault.settings.disconnect');
    Route::post('settings/data-vault/test-connection',  [DataVaultSettingsController::class, 'testConnection'] )->name('data-vault.settings.test');
});
