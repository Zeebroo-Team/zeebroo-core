<?php

use Illuminate\Support\Facades\Route;
use Modules\Package\Http\Controllers\PlanController;

// Admin package management routes are registered centrally in Modules/Auth/routes/web.php,
// alongside the other admin.* routes (users, releases).

// "Choose your plan" for businesses on the free trial (during it, or once it ran out).
// The `plan.` prefix is exempt from EnsureWebSubscriptionSettled so a locked trial can reach it.
Route::middleware(['auth'])->prefix('plan')->name('plan.')->group(function (): void {
    Route::get('/', [PlanController::class, 'choose'])->name('choose');
    Route::get('/{package}', [PlanController::class, 'review'])->whereNumber('package')->name('review');
    Route::post('/{package}', [PlanController::class, 'change'])->whereNumber('package')->name('change');
});
