<?php

use Illuminate\Support\Facades\Route;
use Modules\Developers\Http\Controllers\Api\DeveloperApiKeyApiController;
use Modules\Developers\Http\Controllers\Api\DeveloperWebhookApiController;

Route::middleware(['auth:sanctum'])->prefix('v1/developers')->name('developers.')->group(function (): void {
    // API Keys
    Route::get   ('keys',                    [DeveloperApiKeyApiController::class, 'index'])->name('keys.index');
    Route::post  ('keys',                    [DeveloperApiKeyApiController::class, 'store'])->name('keys.store');
    Route::patch ('keys/{id}/toggle',        [DeveloperApiKeyApiController::class, 'toggle'])->where('id', '[0-9]+')->name('keys.toggle');
    Route::delete('keys/{id}',              [DeveloperApiKeyApiController::class, 'destroy'])->where('id', '[0-9]+')->name('keys.destroy');

    // Webhooks
    Route::get   ('webhooks',                        [DeveloperWebhookApiController::class, 'index'])->name('webhooks.index');
    Route::post  ('webhooks',                        [DeveloperWebhookApiController::class, 'store'])->name('webhooks.store');
    Route::patch ('webhooks/{id}',                   [DeveloperWebhookApiController::class, 'update'])->where('id', '[0-9]+')->name('webhooks.update');
    Route::post  ('webhooks/{id}/regenerate-secret', [DeveloperWebhookApiController::class, 'regenerateSecret'])->where('id', '[0-9]+')->name('webhooks.regenerate-secret');
    Route::delete('webhooks/{id}',                   [DeveloperWebhookApiController::class, 'destroy'])->where('id', '[0-9]+')->name('webhooks.destroy');
});
