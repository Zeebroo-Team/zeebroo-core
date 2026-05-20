<?php

use Illuminate\Support\Facades\Route;
use Modules\Account\Http\Controllers\AccountController;
use Modules\Account\Http\Controllers\BillController;
use Modules\Account\Http\Controllers\LoanController;
use Modules\Account\Http\Controllers\PropertyController;
use Modules\Account\Http\Controllers\RentalController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('account/onboarding', [AccountController::class, 'onboarding'])->name('account.onboarding');
    Route::get('loans', [LoanController::class, 'index'])->name('account.loans.index');
    Route::get('loans/{loan}', [LoanController::class, 'show'])->name('account.loans.show');
    Route::post('loans/{loan}/installments/settle', [LoanController::class, 'settleInstallment'])->name('account.loans.installments.settle');
    Route::post('loans/{loan}/installments/mark-external', [LoanController::class, 'markInstallmentPaidExternally'])->name('account.loans.installments.mark-external');
    Route::post('loans/{loan}/installments/unmark-external', [LoanController::class, 'unmarkInstallmentPaidExternally'])->name('account.loans.installments.unmark-external');
    Route::post('loans', [LoanController::class, 'store'])->name('account.loans.store');
    Route::delete('loans/{loan}', [LoanController::class, 'destroy'])->name('account.loans.destroy');
    Route::get('rentals', [RentalController::class, 'index'])->name('account.rentals.index');
    Route::post('rentals', [RentalController::class, 'store'])->name('account.rentals.store');
    Route::get('rentals/{rental}', [RentalController::class, 'show'])->name('account.rentals.show');
    Route::post('rentals/{rental}/billing/settle', [RentalController::class, 'settleBilling'])->name('account.rentals.billing.settle');
    Route::get('rentals/{rental}/edit', [RentalController::class, 'edit'])->name('account.rentals.edit');
    Route::patch('rentals/{rental}', [RentalController::class, 'update'])->name('account.rentals.update');
    Route::delete('rentals/{rental}', [RentalController::class, 'destroy'])->name('account.rentals.destroy');
    Route::get('properties', [PropertyController::class, 'index'])->name('account.properties.index');
    Route::post('properties', [PropertyController::class, 'store'])->name('account.properties.store');
    Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('account.properties.destroy');

    Route::get('bills', [BillController::class, 'index'])->name('account.bills.index');
    Route::post('bills', [BillController::class, 'store'])->name('account.bills.store');
    Route::get('bills/{bill}', [BillController::class, 'show'])->name('account.bills.show');
    Route::post('bills/{bill}/billing/settle', [BillController::class, 'settleBilling'])->name('account.bills.billing.settle');
    Route::get('bills/{bill}/edit', [BillController::class, 'edit'])->name('account.bills.edit');
    Route::patch('bills/{bill}', [BillController::class, 'update'])->name('account.bills.update');
    Route::delete('bills/{bill}', [BillController::class, 'destroy'])->name('account.bills.destroy');
    Route::resource('accounts', AccountController::class)->names('account');
});
