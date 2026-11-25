<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\Admin\AdminPaymentCheckController;
use Modules\Payment\Http\Controllers\Admin\AdminPaymentController;
use Modules\Payment\Http\Controllers\Admin\AdminUserSeatController;
use Modules\Payment\Http\Controllers\BillingController;
use Modules\Payment\Http\Controllers\PaymentController;

// Admin — platform-wide payment history and per-payment detail view.
Route::middleware(['auth', 'role:admin'])->prefix('admin/payments')->name('admin.payments.')->group(function (): void {
    Route::get('/', [AdminPaymentController::class, 'index'])->name('index');
    // Stripe double-check (scheduled + "Run now") results and schedule settings.
    Route::get('/checks', [AdminPaymentCheckController::class, 'index'])->name('checks.index');
    Route::put('/checks/settings', [AdminPaymentCheckController::class, 'updateSettings'])->name('checks.settings');
    Route::post('/checks/run', [AdminPaymentCheckController::class, 'runNow'])->name('checks.run');
    Route::get('/{payment}', [AdminPaymentController::class, 'show'])->whereNumber('payment')->name('show');
});

// Admin — user seats per business (package limit, extra users bought, who holds each seat).
Route::middleware(['auth', 'role:admin'])->prefix('admin/user-seats')->name('admin.user-seats.')->group(function (): void {
    Route::get('/', [AdminUserSeatController::class, 'index'])->name('index');
    Route::get('/{business}', [AdminUserSeatController::class, 'show'])->whereNumber('business')->name('show');
});

Route::middleware(['auth'])->prefix('payment')->name('payment.')->group(function (): void {
    Route::get('/checkout/success', [PaymentController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', [PaymentController::class, 'cancel'])->name('checkout.cancel');
    Route::post('/{payment}/resume', [PaymentController::class, 'resume'])->name('checkout.resume');
});

// Web billing page — subscription status, payment history, cancel/resume and
// receipts, mirroring the desktop app's "Billing & Payments" screen.
Route::middleware(['auth'])->prefix('billing')->name('payment.billing.')->group(function (): void {
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::post('/subscription/cancel', [BillingController::class, 'cancel'])->name('cancel');
    Route::post('/subscription/resume', [BillingController::class, 'resume'])->name('resume');
    Route::get('/{payment}/receipt', [BillingController::class, 'receipt'])->name('receipt');
});

// Desktop (Electron) counterpart — reached by the system browser after
// shell.openExternal() opens Stripe Checkout, which has no Laravel session
// for this app. Authorization comes from Stripe's session_id instead of
// `auth`, same trust model PaymentController::success() already relies on.
Route::prefix('payment/desktop')->name('payment.desktop.')->group(function (): void {
    Route::get('/checkout/success', [PaymentController::class, 'desktopSuccess'])->name('checkout.success');
    Route::get('/checkout/cancel', [PaymentController::class, 'desktopCancel'])->name('checkout.cancel');
});
