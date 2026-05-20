<?php

use Illuminate\Support\Facades\Route;
use Modules\Modification\Http\Controllers\ModificationController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('modification', [ModificationController::class, 'index'])->name('modification.index');
    Route::get('modification/create', [ModificationController::class, 'create'])->name('modification.create');
    Route::get('modification/{modification}/bills', [ModificationController::class, 'bills'])->name('modification.bills');
    Route::get('modification/{modification}', [ModificationController::class, 'show'])->name('modification.show');
    Route::post('modification', [ModificationController::class, 'store'])->name('modification.store');
    Route::post('modification/quick-property', [ModificationController::class, 'quickStoreProperty'])
        ->name('modification.quick-property.store');
});
