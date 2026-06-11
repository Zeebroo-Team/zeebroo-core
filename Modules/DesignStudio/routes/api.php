<?php

use Illuminate\Support\Facades\Route;
use Modules\DesignStudio\Http\Controllers\Api\DesignStudioApiController;

Route::middleware(['auth:sanctum'])->prefix('v1/design-studio')->name('designstudio.')->group(function (): void {
    Route::get('/', [DesignStudioApiController::class, 'index'])->name('index');
});
