<?php

use Illuminate\Support\Facades\Route;
use Modules\Transaction\Http\Controllers\TransactionController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
});
