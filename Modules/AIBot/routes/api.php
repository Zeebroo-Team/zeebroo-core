<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    // Register API endpoints when bot/chat integrations are modeled.
});
