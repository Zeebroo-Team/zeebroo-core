<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\InvoiceController;
use Modules\Sales\Http\Controllers\QuotationController;

Route::middleware(['web'])->group(function () {
    Route::get('/invoice/share/{token}', [InvoiceController::class, 'publicView'])->name('sales.invoices.public');
});

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

    Route::get('/sales/line-items/search', [InvoiceController::class, 'lineItemSearch'])->name('sales.line-items.search');
    Route::get('/sales/invoices',                              [InvoiceController::class, 'index'])             ->name('sales.invoices.index');
    Route::post('/sales/invoices',                             [InvoiceController::class, 'store'])             ->name('sales.invoices.store');
    Route::get('/sales/invoices/{invoice}',                    [InvoiceController::class, 'show'])              ->name('sales.invoices.show');
    Route::get('/sales/invoices/{invoice}/print',              [InvoiceController::class, 'printInvoice'])      ->name('sales.invoices.print');
    Route::get('/sales/invoices/{invoice}/edit',               [InvoiceController::class, 'edit'])              ->name('sales.invoices.edit');
    Route::put('/sales/invoices/{invoice}',                    [InvoiceController::class, 'update'])            ->name('sales.invoices.update');
    Route::post('/sales/invoices/{invoice}/mark-sent',         [InvoiceController::class, 'markSent'])          ->name('sales.invoices.mark-sent');
    Route::post('/sales/invoices/{invoice}/mark-paid',         [InvoiceController::class, 'markPaid'])          ->name('sales.invoices.mark-paid');
    Route::post('/sales/invoices/{invoice}/mark-overdue',      [InvoiceController::class, 'markOverdue'])       ->name('sales.invoices.mark-overdue');
    Route::post('/sales/invoices/{invoice}/cancel',            [InvoiceController::class, 'cancel'])            ->name('sales.invoices.cancel');
    Route::post('/sales/invoices/{invoice}/toggle-share',      [InvoiceController::class, 'toggleShare'])       ->name('sales.invoices.toggle-share');
    Route::delete('/sales/invoices/{invoice}',                 [InvoiceController::class, 'destroy'])           ->name('sales.invoices.destroy');
});
