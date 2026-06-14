<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\Http\Controllers\Api\PosAuthApiController;
use Modules\Pos\Http\Controllers\Api\PosBusinessesApiController;
use Modules\Pos\Http\Controllers\Api\PosBranchesApiController;
use Modules\Pos\Http\Controllers\Api\PosApiDocsController;
use Modules\Pos\Http\Controllers\Api\PosCatalogApiController;
use Modules\Pos\Http\Controllers\Api\PosCheckoutApiController;
use Modules\Pos\Http\Controllers\Api\PosOnlineBootstrapApiController;
use Modules\Pos\Http\Controllers\Api\PosStockAuditApiController;
use Modules\Pos\Http\Controllers\Api\PosProductApiController;
use Modules\Pos\Http\Controllers\Api\PosPurchaseOrderApiController;
use Modules\Pos\Http\Controllers\Api\PosSaleApiController;
use Modules\Pos\Http\Controllers\Api\PosSaleReturnApiController;
use Modules\Pos\Http\Controllers\Api\PosExpenseBillApiController;
use Modules\Pos\Http\Controllers\Api\PosExpenseBillAssignmentApiController;
use Modules\Pos\Http\Controllers\Api\PosAccountApiController;
use Modules\Pos\Http\Controllers\Api\PosLoanApiController;
use Modules\Pos\Http\Controllers\Api\PosSettingsApiController;
use Modules\Pos\Http\Controllers\Api\PosSupplierApiController;

Route::prefix('v1/pos')->group(function (): void {
    Route::post('auth/token', [PosAuthApiController::class, 'token'])->name('auth.token');

    Route::get('docs', [PosApiDocsController::class, 'index'])->name('pos.docs');
    Route::get('docs/openapi.yaml', [PosApiDocsController::class, 'openapi'])->name('pos.docs.openapi');
    Route::get('docs/openapi.json', [PosApiDocsController::class, 'openapiJson'])->name('pos.docs.openapi.json');
    Route::get('docs/readme', [PosApiDocsController::class, 'readme'])->name('pos.docs.readme');
});

Route::middleware(['auth:sanctum'])->prefix('v1/pos')->name('pos.')->group(function (): void {
    Route::post('auth/revoke', [PosAuthApiController::class, 'revoke'])->name('auth.revoke');
    Route::get('businesses', [PosBusinessesApiController::class, 'index'])->name('businesses.index');
    Route::get('online/bootstrap', PosOnlineBootstrapApiController::class)->name('online.bootstrap');
    Route::get('online/branches', PosBranchesApiController::class)->name('online.branches');

    Route::get('online/categories', [PosCatalogApiController::class, 'categories'])->name('online.categories');
    Route::get('online/products', [PosCatalogApiController::class, 'products'])->name('online.products');
    Route::get('online/products/sku/{sku}', [PosCatalogApiController::class, 'productBySku'])->name('online.products.sku');

    Route::post('online/products', [PosProductApiController::class, 'store'])->name('online.products.store');
    Route::post('online/checkout', [PosCheckoutApiController::class, 'store'])->name('online.checkout');

    Route::get('online/settings', [PosSettingsApiController::class, 'show'])->name('online.settings.show');
    Route::put('online/settings', [PosSettingsApiController::class, 'update'])->name('online.settings.update');
    Route::patch('online/settings', [PosSettingsApiController::class, 'update']);

    Route::get('sales', [PosSaleApiController::class, 'index'])->name('sales.index');
    Route::get('sales/{sale}', [PosSaleApiController::class, 'show'])->name('sales.show');
    Route::post('sales/{sale}/void', [PosSaleApiController::class, 'void'])->name('sales.void');
    Route::post('sales/{sale}/return', [PosSaleReturnApiController::class, 'store'])->name('sales.return');

    Route::get('suppliers', [PosSupplierApiController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [PosSupplierApiController::class, 'store'])->name('suppliers.store');

    Route::get('purchase-orders', [PosPurchaseOrderApiController::class, 'index'])->name('purchase-orders.index');
    Route::post('purchase-orders', [PosPurchaseOrderApiController::class, 'store'])->name('purchase-orders.store');
    Route::get('purchase-orders/{purchase}', [PosPurchaseOrderApiController::class, 'show'])->name('purchase-orders.show');
    Route::post('purchase-orders/{purchase}/place', [PosPurchaseOrderApiController::class, 'placeOrder'])->name('purchase-orders.place');
    Route::post('purchase-orders/{purchase}/receive', [PosPurchaseOrderApiController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchase}/cancel', [PosPurchaseOrderApiController::class, 'cancel'])->name('purchase-orders.cancel');

    Route::post('expenses/bills', [PosExpenseBillApiController::class, 'store'])->name('expenses.bills.store');
    Route::get('expenses/bill-assignment-targets', [PosExpenseBillAssignmentApiController::class, 'index'])->name('expenses.bill-assignment-targets');
    Route::get('loans', [PosLoanApiController::class, 'index'])->name('loans.index');
    Route::get('accounts', [PosAccountApiController::class, 'index'])->name('accounts.index');

    Route::get('stock-audits', [PosStockAuditApiController::class, 'index'])->name('stock-audits.index');
    Route::post('stock-audits', [PosStockAuditApiController::class, 'store'])->name('stock-audits.store');
    Route::get('stock-audits/{stockAudit}', [PosStockAuditApiController::class, 'show'])->name('stock-audits.show');
    Route::put('stock-audits/{stockAudit}/lines', [PosStockAuditApiController::class, 'saveLines'])->name('stock-audits.save-lines');
    Route::post('stock-audits/{stockAudit}/finalize', [PosStockAuditApiController::class, 'finalize'])->name('stock-audits.finalize');
    Route::delete('stock-audits/{stockAudit}', [PosStockAuditApiController::class, 'destroy'])->name('stock-audits.destroy');
});
