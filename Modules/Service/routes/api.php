<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\Http\Middleware\PosIdempotency;
use Modules\Service\Http\Controllers\Api\ServicePosController;

// PosIdempotency: writes the desktop app queued offline (Idempotency-Key header) run once.
Route::middleware(['auth:sanctum', PosIdempotency::class])->prefix('v1/pos')->name('service.pos.')->group(function (): void {
    Route::get ('service/pos/catalog',  [ServicePosController::class, 'catalog'])->name('catalog');
    Route::post('service/pos/checkout', [ServicePosController::class, 'checkout'])->name('checkout');
});
