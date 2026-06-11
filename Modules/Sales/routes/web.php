<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\QuotationController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/sales/quotations',                            [QuotationController::class, 'index'])          ->name('sales.quotations.index');
    Route::post('/sales/quotations',                           [QuotationController::class, 'store'])          ->name('sales.quotations.store');
    Route::get('/sales/quotations/{quotation}',                [QuotationController::class, 'show'])           ->name('sales.quotations.show');
    Route::get('/sales/quotations/{quotation}/print',          [QuotationController::class, 'printQuotation']) ->name('sales.quotations.print');
    Route::get('/sales/quotations/{quotation}/edit',           [QuotationController::class, 'edit'])           ->name('sales.quotations.edit');
    Route::put('/sales/quotations/{quotation}',                [QuotationController::class, 'update'])         ->name('sales.quotations.update');
    Route::post('/sales/quotations/{quotation}/mark-sent',     [QuotationController::class, 'markSent'])       ->name('sales.quotations.mark-sent');
    Route::post('/sales/quotations/{quotation}/mark-accepted', [QuotationController::class, 'markAccepted'])   ->name('sales.quotations.mark-accepted');
    Route::post('/sales/quotations/{quotation}/mark-rejected', [QuotationController::class, 'markRejected'])   ->name('sales.quotations.mark-rejected');
    Route::delete('/sales/quotations/{quotation}',             [QuotationController::class, 'destroy'])        ->name('sales.quotations.destroy');
});
