<?php

use Illuminate\Support\Facades\Route;
use Modules\Service\Http\Controllers\Api\ServicePosController;

Route::middleware(['auth:sanctum'])->prefix('v1/pos')->name('service.pos.')->group(function (): void {
    Route::get ('service/pos/catalog',  [ServicePosController::class, 'catalog'])->name('catalog');
    Route::post('service/pos/checkout', [ServicePosController::class, 'checkout'])->name('checkout');
});
