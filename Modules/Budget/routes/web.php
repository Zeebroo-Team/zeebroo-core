<?php

use Illuminate\Support\Facades\Route;
use Modules\Budget\Http\Controllers\BudgetController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('budgets', [BudgetController::class, 'index'])->name('budget.index');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budget.store');
    Route::get('budgets/{budget}', [BudgetController::class, 'show'])->name('budget.show');
    Route::put('budgets/{budget}', [BudgetController::class, 'update'])->name('budget.update');
    Route::put('budgets/{budget}/items', [BudgetController::class, 'updateItems'])->name('budget.items.update');
    Route::patch('budgets/{budget}/view-period', [BudgetController::class, 'updateViewPeriod'])->name('budget.view-period.update');
    Route::post('budgets/{budget}/activate', [BudgetController::class, 'activate'])->name('budget.activate');
    Route::post('budgets/{budget}/deactivate', [BudgetController::class, 'deactivate'])->name('budget.deactivate');
    Route::post('budgets/{budget}/actuals', [BudgetController::class, 'storeActual'])->name('budget.actuals.store');
    Route::delete('budgets/{budget}/actuals/{actual}', [BudgetController::class, 'destroyActual'])->name('budget.actuals.destroy');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budget.destroy');
});
