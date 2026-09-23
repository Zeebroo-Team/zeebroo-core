<?php

use Illuminate\Support\Facades\Route;
use Modules\AutomationEditor\Http\Controllers\AutomationController;

Route::middleware(['auth', 'verified'])->prefix('automations')->name('automations.')->group(function (): void {
    Route::get   ('/',                      [AutomationController::class, 'index'])->name('index');
    Route::post  ('/',                      [AutomationController::class, 'store'])->name('store');
    Route::get   ('/{automation}/edit',     [AutomationController::class, 'edit'])->name('edit');
    Route::patch ('/{automation}',          [AutomationController::class, 'update'])->name('update');
    Route::delete('/{automation}',          [AutomationController::class, 'destroy'])->name('destroy');
    Route::get   ('/{automation}/runs',     [AutomationController::class, 'runs'])->name('runs');
    Route::post  ('/{automation}/trigger',  [AutomationController::class, 'trigger'])->name('trigger');
});
