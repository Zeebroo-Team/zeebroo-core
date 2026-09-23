<?php

use Illuminate\Support\Facades\Route;
use Modules\Developers\Http\Controllers\Web\DeveloperSettingsController;

Route::middleware(['auth', 'verified'])->prefix('settings/developers')->name('developers.')->group(function (): void {
    Route::get   ('/',                              [DeveloperSettingsController::class, 'index'])->name('index');

    Route::post  ('keys',                           [DeveloperSettingsController::class, 'storeKey'])->name('keys.store');
    Route::patch ('keys/{key}/toggle',               [DeveloperSettingsController::class, 'toggleKey'])->name('keys.toggle');
    Route::delete('keys/{key}',                      [DeveloperSettingsController::class, 'destroyKey'])->name('keys.destroy');

    Route::post  ('webhooks',                        [DeveloperSettingsController::class, 'storeWebhook'])->name('webhooks.store');
    Route::patch ('webhooks/{webhook}/toggle',        [DeveloperSettingsController::class, 'toggleWebhook'])->name('webhooks.toggle');
    Route::post  ('webhooks/{webhook}/regenerate-secret', [DeveloperSettingsController::class, 'regenerateWebhookSecret'])->name('webhooks.regenerate-secret');
    Route::delete('webhooks/{webhook}',               [DeveloperSettingsController::class, 'destroyWebhook'])->name('webhooks.destroy');
});
