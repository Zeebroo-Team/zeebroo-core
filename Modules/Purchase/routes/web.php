<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\ChequeController;
use Modules\Purchase\Http\Controllers\GoodsReceiveNoteController;
use Modules\Purchase\Http\Controllers\PurchaseController;
use Modules\Purchase\Http\Controllers\SupplierController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/purchases/suppliers', [SupplierController::class, 'index'])->name('purchase.suppliers.index');
    Route::post('/purchases/suppliers', [SupplierController::class, 'store'])->name('purchase.suppliers.store');
    Route::get('/purchases/suppliers/{supplier}', [SupplierController::class, 'show'])->name('purchase.suppliers.show');
    Route::get('/purchases/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('purchase.suppliers.edit');
    Route::put('/purchases/suppliers/{supplier}', [SupplierController::class, 'update'])->name('purchase.suppliers.update');
    Route::delete('/purchases/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('purchase.suppliers.destroy');

    Route::get('/purchases/cheques', [ChequeController::class, 'index'])->name('purchase.cheques.index');
    Route::get('/purchases/cheques/{chequePayment}', [ChequeController::class, 'show'])->name('purchase.cheques.show');
    Route::post('/purchases/cheques/{chequePayment}/deduct', [ChequeController::class, 'deduct'])->name('purchase.cheques.deduct');

    Route::get('/purchases/goods-receive', [GoodsReceiveNoteController::class, 'index'])->name('purchase.grn.index');
    Route::get('/purchases/goods-receive/{goodsReceiveNote}', [GoodsReceiveNoteController::class, 'show'])->name('purchase.grn.show');
    Route::get('/purchases/goods-receive/{goodsReceiveNote}/print', [GoodsReceiveNoteController::class, 'printInvoice'])->name('purchase.grn.print');
    Route::post('/purchases/goods-receive/{goodsReceiveNote}/pay', [GoodsReceiveNoteController::class, 'pay'])->name('purchase.grn.pay');
    Route::get('/purchases/{purchase}/goods-receive/create', [GoodsReceiveNoteController::class, 'create'])->name('purchase.grn.create');
    Route::post('/purchases/{purchase}/goods-receive', [GoodsReceiveNoteController::class, 'store'])->name('purchase.grn.store');

    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchase.index');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchase.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchase.show');
    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'printInvoice'])->name('purchase.print');
    Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchase.edit');
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchase.update');
    Route::post('/purchases/{purchase}/place-order', [PurchaseController::class, 'placeOrder'])->name('purchase.place-order');
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchase.receive');
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchase.cancel');
    Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchase.destroy');
});
