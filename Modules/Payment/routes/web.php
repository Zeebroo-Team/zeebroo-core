<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;

Route::middleware(['auth'])->prefix('payment')->name('payment.')->group(function (): void {
    Route::get('/checkout/success', [PaymentController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', [PaymentController::class, 'cancel'])->name('checkout.cancel');
    Route::post('/{payment}/resume', [PaymentController::class, 'resume'])->name('checkout.resume');
});

// Desktop (Electron) counterpart — reached by the system browser after
// shell.openExternal() opens Stripe Checkout, which has no Laravel session
// for this app. Authorization comes from Stripe's session_id instead of
// `auth`, same trust model PaymentController::success() already relies on.
Route::prefix('payment/desktop')->name('payment.desktop.')->group(function (): void {
    Route::get('/checkout/success', [PaymentController::class, 'desktopSuccess'])->name('checkout.success');
    Route::get('/checkout/cancel', [PaymentController::class, 'desktopCancel'])->name('checkout.cancel');
});
