<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\Http\Controllers\PosController;
use Modules\Pos\Http\Controllers\PosProductController;
use Modules\Pos\Http\Controllers\SaleController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/online', [PosController::class, 'online'])->name('pos.online');
    Route::get('/pos/register', [PosController::class, 'register'])->name('pos.register');
    Route::post('/pos/walking-customer', [PosController::class, 'toggleWalkingCustomer'])->name('pos.walking-customer.toggle');
    Route::post('/pos/settings', [PosController::class, 'saveSettings'])->name('pos.settings.save');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/products', [PosProductController::class, 'store'])->name('pos.products.store');

    Route::get('/pos/sales', [SaleController::class, 'index'])->name('pos.sales.index');
    Route::get('/pos/sales/{sale}', [SaleController::class, 'show'])->name('pos.sales.show');
    Route::post('/pos/sales/{sale}/void', [SaleController::class, 'void'])->name('pos.sales.void');
});
