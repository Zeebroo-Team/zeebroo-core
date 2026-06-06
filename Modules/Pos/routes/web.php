<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\Http\Controllers\CustomerController;
use Modules\Pos\Http\Controllers\EndOfDayController;
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

    Route::get('/pos/customers/search', [CustomerController::class, 'search'])->name('pos.customers.search');
    Route::get('/pos/customers', [CustomerController::class, 'index'])->name('pos.customers.index');
    Route::post('/pos/customers', [CustomerController::class, 'store'])->name('pos.customers.store');
    Route::put('/pos/customers/{customer}', [CustomerController::class, 'update'])->name('pos.customers.update');
    Route::delete('/pos/customers/{customer}', [CustomerController::class, 'destroy'])->name('pos.customers.destroy');

    Route::get('/pos/sale-lookup', [SaleController::class, 'saleLookup'])->name('pos.sale-lookup');
    Route::post('/pos/online/modal-return-open', [SaleController::class, 'onlineModalReturnOpen'])->name('pos.online.modal-return-open');
    Route::post('/pos/online/modal-return/{sale}', [SaleController::class, 'onlineModalReturn'])->name('pos.online.modal-return');
    Route::get('/pos/returns', [SaleController::class, 'returnsIndex'])->name('pos.returns.index');
    Route::get('/pos/returns/create', [SaleController::class, 'createReturn'])->name('pos.returns.create');
    Route::post('/pos/returns', [SaleController::class, 'storeOpenReturn'])->name('pos.returns.store-open');
    Route::get('/pos/sales', [SaleController::class, 'index'])->name('pos.sales.index');
    Route::get('/pos/sales/{sale}', [SaleController::class, 'show'])->name('pos.sales.show');
    Route::post('/pos/sales/{sale}/void', [SaleController::class, 'void'])->name('pos.sales.void');
    Route::post('/pos/sales/{sale}/returns', [SaleController::class, 'storeReturn'])->name('pos.sales.returns.store');

    Route::get('/pos/end-of-day', [EndOfDayController::class, 'index'])->name('pos.end-of-day');
    Route::post('/pos/end-of-day/settle', [EndOfDayController::class, 'settle'])->name('pos.end-of-day.settle');
});
