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
use Modules\Pos\Http\Controllers\Api\PosStockTransferApiController;
use Modules\Pos\Http\Controllers\Api\PosProductApiController;
use Modules\Pos\Http\Controllers\Api\PosPurchaseOrderApiController;
use Modules\Pos\Http\Controllers\Api\PosSaleApiController;
use Modules\Pos\Http\Controllers\Api\PosSaleReturnApiController;
use Modules\Pos\Http\Controllers\Api\PosExpenseBillApiController;
use Modules\Pos\Http\Controllers\Api\PosExpenseBillAssignmentApiController;
use Modules\Pos\Http\Controllers\Api\PosAccountApiController;
use Modules\Pos\Http\Controllers\Api\PosFinanceFlowApiController;
use Modules\Pos\Http\Controllers\Api\PosLoanApiController;
use Modules\Pos\Http\Controllers\Api\PosPropertyApiController;
use Modules\Pos\Http\Controllers\Api\PosRentalApiController;
use Modules\Pos\Http\Controllers\Api\PosExpenseModificationApiController;
use Modules\Pos\Http\Controllers\Api\PosEndOfDayApiController;
use Modules\Pos\Http\Controllers\Api\PosQuotationApiController;
use Modules\Pos\Http\Controllers\Api\PosInvoiceApiController;
use Modules\Pos\Http\Controllers\Api\PosSalesOrderApiController;
use Modules\Pos\Http\Controllers\Api\PosFeatureReviewApiController;
use Modules\Pos\Http\Controllers\Api\PosSettingsApiController;
use Modules\Pos\Http\Controllers\Api\PosCustomerApiController;
use Modules\Pos\Http\Controllers\Api\PosCustomerCategoryApiController;
use Modules\Pos\Http\Controllers\Api\PosSupplierApiController;
use Modules\Pos\Http\Controllers\Api\PosSupplierCategoryApiController;
use Modules\Pos\Http\Controllers\Api\PosReturnReasonsApiController;
use Modules\Pos\Http\Controllers\Api\PosGoodsReceiveNoteApiController;
use Modules\Pos\Http\Controllers\Api\PosGrnPermissionApiController;
use Modules\Pos\Http\Controllers\Api\PosGrnChequeApiController;
use Modules\Pos\Http\Controllers\Api\PosProductCategoryApiController;
use Modules\Pos\Http\Controllers\Api\PosProductUnitApiController;
use Modules\Pos\Http\Controllers\Api\PosProductDiscountApiController;
use Modules\Pos\Http\Controllers\Api\PosProductBrandApiController;
use Modules\Pos\Http\Controllers\Api\PosFileManagerApiController;
use Modules\Pos\Http\Controllers\Api\PosTodaySummaryApiController;
use Modules\Pos\Http\Controllers\Api\PosExpensesOverviewApiController;
use Modules\Pos\Http\Controllers\Api\PosProfitReportApiController;
use Modules\Pos\Http\Controllers\Api\PosPayrollOverviewApiController;
use Modules\Pos\Http\Controllers\Api\PosUserManagementApiController;
use Modules\Pos\Http\Controllers\Api\PosBranchManagementApiController;
use Modules\Pos\Http\Controllers\Api\PosRoleManagementApiController;
use Modules\Pos\Http\Controllers\Api\PosCounterApiController;
use Modules\Pos\Http\Controllers\Api\PosRegisterLockApiController;
use Modules\Pos\Http\Controllers\Api\PosCashierApiController;
use Modules\Pos\Http\Controllers\Api\PosNotificationApiController;
use Modules\Pos\Http\Controllers\Api\PosSubscriptionApiController;

Route::prefix('v1/pos')->group(function (): void {
    Route::post('auth/token',             [PosAuthApiController::class, 'token'])->name('auth.token');
    Route::post('auth/register',          [PosAuthApiController::class, 'register'])->name('auth.register');
    Route::get ('auth/business-categories',[PosAuthApiController::class, 'businessCategories'])->name('auth.business-categories');
    Route::post('cashier/login',          [PosCashierApiController::class, 'login'])->name('cashier.login');
    Route::get('docs', [PosApiDocsController::class, 'index'])->name('pos.docs');
    Route::get('docs/openapi.yaml', [PosApiDocsController::class, 'openapi'])->name('pos.docs.openapi');
    Route::get('docs/openapi.json', [PosApiDocsController::class, 'openapiJson'])->name('pos.docs.openapi.json');
    Route::get('docs/readme', [PosApiDocsController::class, 'readme'])->name('pos.docs.readme');
});

Route::middleware(['auth:sanctum'])->prefix('v1/pos')->name('pos.')->group(function (): void {
    Route::get ('auth/me',       [PosAuthApiController::class, 'me'])->name('auth.me');
    Route::put ('auth/profile',  [PosAuthApiController::class, 'updateProfile'])->name('auth.profile.update');
    Route::put ('auth/password', [PosAuthApiController::class, 'updatePassword'])->name('auth.password.update');
    Route::post('auth/revoke',   [PosAuthApiController::class, 'revoke'])->name('auth.revoke');
    Route::get ('businesses', [PosBusinessesApiController::class, 'index'])->name('businesses.index');
    Route::post('businesses', [PosBusinessesApiController::class, 'store'])->name('businesses.store');
    Route::get('online/bootstrap', PosOnlineBootstrapApiController::class)->name('online.bootstrap');
    Route::get('online/branches', PosBranchesApiController::class)->name('online.branches');

    Route::get('online/categories', [PosCatalogApiController::class, 'categories'])->name('online.categories');
    Route::get('online/products', [PosCatalogApiController::class, 'products'])->name('online.products');
    Route::get('online/products/{id}', [PosCatalogApiController::class, 'show'])->where('id', '[0-9]+')->name('online.products.show');
    Route::get  ('online/products/{id}/stock-history',                       [PosCatalogApiController::class, 'stockHistory'])->where('id', '[0-9]+')->name('online.products.stock-history');
    Route::patch('online/products/{id}/stock-layers/{layerId}/selling-price',   [PosCatalogApiController::class, 'updateLayerSellingPrice'])->where(['id' => '[0-9]+', 'layerId' => '[0-9]+'])->name('online.products.stock-layers.selling-price');
    Route::patch('online/products/{id}/stock-layers/{layerId}/wholesale-price', [PosCatalogApiController::class, 'updateLayerWholesalePrice'])->where(['id' => '[0-9]+', 'layerId' => '[0-9]+'])->name('online.products.stock-layers.wholesale-price');
    Route::patch('online/products/{id}/stock-layers/{layerId}/cost-price',      [PosCatalogApiController::class, 'updateLayerCostPrice'])->where(['id' => '[0-9]+', 'layerId' => '[0-9]+'])->name('online.products.stock-layers.cost-price');
    Route::patch('online/products/{id}/stock-layers/{layerId}/barcode',         [PosCatalogApiController::class, 'updateLayerBarcode'])->where(['id' => '[0-9]+', 'layerId' => '[0-9]+'])->name('online.products.stock-layers.barcode');
    Route::get('online/products/{id}/sales-chart', \Modules\Pos\Http\Controllers\Api\PosProductSalesChartApiController::class)->where('id', '[0-9]+')->name('online.products.sales-chart');
    Route::get('online/products/sku/{sku}', [PosCatalogApiController::class, 'productBySku'])->name('online.products.sku');
    Route::post('online/products/{id}/backfill-batch-skus', [PosCatalogApiController::class, 'backfillBatchSkus'])->where('id', '[0-9]+')->name('online.products.backfill-batch-skus');

    Route::post('online/products',        [PosProductApiController::class, 'store']  )->name('online.products.store');
    Route::post('online/products/import', [PosProductApiController::class, 'import'] )->name('online.products.import');
    Route::patch('online/products/{product}', [PosProductApiController::class, 'update'])->name('online.products.update');
    Route::delete('online/products/{product}', [PosProductApiController::class, 'destroy'])->name('online.products.destroy');
    Route::get('online/file-manager', [PosFileManagerApiController::class, 'browse'])->name('online.file-manager.browse');
    Route::post('online/file-manager/upload', [PosFileManagerApiController::class, 'upload'])->name('online.file-manager.upload');
    Route::post('online/checkout', [PosCheckoutApiController::class, 'store'])->name('online.checkout');

    Route::get('online/features', [PosSettingsApiController::class, 'features'])->name('online.features');
    Route::put('online/features', [PosSettingsApiController::class, 'updateFeatures'])->name('online.features.update');
    Route::get('features/reviews/summary', [PosFeatureReviewApiController::class, 'summary'])->name('features.reviews.summary');
    Route::get('features/{key}/reviews', [PosFeatureReviewApiController::class, 'index'])->name('features.reviews.index');
    Route::post('features/{key}/reviews', [PosFeatureReviewApiController::class, 'store'])->name('features.reviews.store');
    Route::get('online/sync-status', [PosSettingsApiController::class, 'syncStatus'])->name('online.sync-status');
    Route::get('online/settings', [PosSettingsApiController::class, 'show'])->name('online.settings.show');
    Route::put('online/settings', [PosSettingsApiController::class, 'update'])->name('online.settings.update');
    Route::patch('online/settings', [PosSettingsApiController::class, 'update']);

    // Invoices
    Route::get   ('invoices',                          [PosInvoiceApiController::class, 'index']          )->name('invoices.index');
    Route::post  ('invoices',                          [PosInvoiceApiController::class, 'store']          )->name('invoices.store');
    Route::get   ('invoices/{invoice}',                [PosInvoiceApiController::class, 'show']           )->name('invoices.show');
    Route::patch ('invoices/{invoice}',                [PosInvoiceApiController::class, 'update']         )->name('invoices.update');
    Route::post  ('invoices/{invoice}/enable-share',    [PosInvoiceApiController::class, 'enableShare']    )->name('invoices.enable-share');
    Route::post  ('invoices/{invoice}/mark-sent',      [PosInvoiceApiController::class, 'markSent']       )->name('invoices.mark-sent');
    Route::post  ('invoices/{invoice}/mark-paid',      [PosInvoiceApiController::class, 'markPaid']       )->name('invoices.mark-paid');
    Route::post  ('invoices/{invoice}/mark-overdue',   [PosInvoiceApiController::class, 'markOverdue']    )->name('invoices.mark-overdue');
    Route::post  ('invoices/{invoice}/cancel',         [PosInvoiceApiController::class, 'cancel']         )->name('invoices.cancel');
    Route::delete('invoices/{invoice}',                [PosInvoiceApiController::class, 'destroy']        )->name('invoices.destroy');

    // Quotations
    Route::get   ('quotations',                        [PosQuotationApiController::class, 'index']        )->name('quotations.index');
    Route::post  ('quotations',                        [PosQuotationApiController::class, 'store']        )->name('quotations.store');
    Route::get   ('quotations/{quotation}',            [PosQuotationApiController::class, 'show']         )->name('quotations.show');
    Route::patch ('quotations/{quotation}',            [PosQuotationApiController::class, 'update']       )->name('quotations.update');
    Route::post  ('quotations/{quotation}/mark-sent',  [PosQuotationApiController::class, 'markSent']     )->name('quotations.mark-sent');
    Route::post  ('quotations/{quotation}/accept',     [PosQuotationApiController::class, 'markAccepted'] )->name('quotations.accept');
    Route::post  ('quotations/{quotation}/reject',     [PosQuotationApiController::class, 'markRejected'] )->name('quotations.reject');
    Route::delete('quotations/{quotation}',            [PosQuotationApiController::class, 'destroy']      )->name('quotations.destroy');

    // Sales Orders
    Route::get   ('sales-orders',                            [PosSalesOrderApiController::class, 'index']   )->name('sales-orders.index');
    Route::post  ('sales-orders',                            [PosSalesOrderApiController::class, 'store']   )->name('sales-orders.store');
    Route::get   ('sales-orders/{salesOrder}',               [PosSalesOrderApiController::class, 'show']    )->name('sales-orders.show');
    Route::patch ('sales-orders/{salesOrder}',               [PosSalesOrderApiController::class, 'update']  )->name('sales-orders.update');
    Route::post  ('sales-orders/{salesOrder}/confirm',       [PosSalesOrderApiController::class, 'confirm'] )->name('sales-orders.confirm');
    Route::post  ('sales-orders/{salesOrder}/process',       [PosSalesOrderApiController::class, 'process'] )->name('sales-orders.process');
    Route::post  ('sales-orders/{salesOrder}/complete',      [PosSalesOrderApiController::class, 'complete'])->name('sales-orders.complete');
    Route::post  ('sales-orders/{salesOrder}/cancel',        [PosSalesOrderApiController::class, 'cancel']  )->name('sales-orders.cancel');
    Route::delete('sales-orders/{salesOrder}',               [PosSalesOrderApiController::class, 'destroy'] )->name('sales-orders.destroy');

    // Customer Subscriptions
    Route::get   ('subscriptions',                            [PosSubscriptionApiController::class, 'index'] )->name('subscriptions.index');
    Route::get   ('subscriptions/{subscription}',              [PosSubscriptionApiController::class, 'show']  )->name('subscriptions.show');
    Route::post  ('subscriptions/{subscription}/cancel',       [PosSubscriptionApiController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post  ('subscriptions/{subscription}/pause',        [PosSubscriptionApiController::class, 'pause'] )->name('subscriptions.pause');
    Route::post  ('subscriptions/{subscription}/resume',       [PosSubscriptionApiController::class, 'resume'])->name('subscriptions.resume');
    Route::post  ('subscriptions/{subscription}/renew',        [PosSubscriptionApiController::class, 'renew'] )->name('subscriptions.renew');
    Route::post  ('subscriptions/{subscription}/notify',       [PosSubscriptionApiController::class, 'notify'])->name('subscriptions.notify');

    Route::get ('eod',            [PosEndOfDayApiController::class,    'status'])->name('eod.status');
    Route::post('eod/settle',     [PosEndOfDayApiController::class,    'settle'])->name('eod.settle');

    Route::get ('cash-drawer',          [\Modules\Pos\Http\Controllers\Api\PosCashDrawerApiController::class, 'status'])  ->name('cash-drawer.status');
    Route::post('cash-drawer/open',     [\Modules\Pos\Http\Controllers\Api\PosCashDrawerApiController::class, 'open'])    ->name('cash-drawer.open');
    Route::post('cash-drawer/withdraw', [\Modules\Pos\Http\Controllers\Api\PosCashDrawerApiController::class, 'withdraw'])->name('cash-drawer.withdraw');
    Route::get ('today-summary',      [PosTodaySummaryApiController::class, 'show'])->name('today-summary');
    Route::get ('expenses/overview',  [PosExpensesOverviewApiController::class, 'show'])->name('expenses.overview');
    Route::get ('profit-report',      [PosProfitReportApiController::class,     'show'])->name('profit-report');
    Route::get ('payroll-overview',   [PosPayrollOverviewApiController::class,  'show'])->name('payroll-overview');

    Route::get('sales',                  [PosSaleApiController::class, 'index']          )->name('sales.index');
    Route::get('sales/history',          [PosSaleApiController::class, 'history']         )->name('sales.history');
    Route::get('sales/pending-credits',  [PosSaleApiController::class, 'pendingCredits']  )->name('sales.pending-credits');
    Route::get('sales/{sale}',           [PosSaleApiController::class, 'show']            )->name('sales.show');
    Route::post('sales/{sale}/void', [PosSaleApiController::class, 'void'])->name('sales.void');
    Route::post('sales/{sale}/return', [PosSaleReturnApiController::class, 'store'])->name('sales.return');
    Route::get('online/return-reasons', [PosReturnReasonsApiController::class, 'index'])->name('online.return-reasons');

    // Product Units
    Route::get('units', [PosProductUnitApiController::class, 'index'])->name('units.index');
    Route::post('units', [PosProductUnitApiController::class, 'store'])->name('units.store');
    Route::patch('units/{productUnit}', [PosProductUnitApiController::class, 'update'])->name('units.update');
    Route::delete('units/{productUnit}', [PosProductUnitApiController::class, 'destroy'])->name('units.destroy');

    // Product Brands
    Route::get('brands', [PosProductBrandApiController::class, 'index'])->name('brands.index');
    Route::post('brands', [PosProductBrandApiController::class, 'store'])->name('brands.store');
    Route::patch('brands/{brand}', [PosProductBrandApiController::class, 'update'])->name('brands.update');
    Route::delete('brands/{brand}', [PosProductBrandApiController::class, 'destroy'])->name('brands.destroy');

    // Product Discounts
    Route::get('discounts', [PosProductDiscountApiController::class, 'index'])->name('discounts.index');
    Route::get('discounts/product-options', [PosProductDiscountApiController::class, 'productOptions'])->name('discounts.product-options');
    Route::post('discounts', [PosProductDiscountApiController::class, 'store'])->name('discounts.store');
    Route::patch('discounts/{discount}', [PosProductDiscountApiController::class, 'update'])->name('discounts.update');
    Route::delete('discounts/{discount}', [PosProductDiscountApiController::class, 'destroy'])->name('discounts.destroy');

    // Product Categories
    Route::get ('categories',              [PosProductCategoryApiController::class, 'index'])->name('categories.index');
    Route::get ('categories/parent-options',[PosProductCategoryApiController::class, 'parentOptions'])->name('categories.parent-options');
    Route::post('categories/generate-ai', [PosProductCategoryApiController::class, 'generateAi'])->name('categories.generate-ai');
    Route::post('categories',             [PosProductCategoryApiController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [PosProductCategoryApiController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}',[PosProductCategoryApiController::class, 'destroy'])->name('categories.destroy');

    Route::get('cheques', [PosGrnChequeApiController::class, 'index'])->name('cheques.index');
    Route::post('cheques/{cheque}/clear', [PosGrnChequeApiController::class, 'clear'])->name('cheques.clear');

    Route::get ('grn-permissions',     [PosGrnPermissionApiController::class, 'show'])  ->name('grn-permissions.show');
    Route::put ('grn-permissions',     [PosGrnPermissionApiController::class, 'update'])->name('grn-permissions.update');

    Route::get('grns', [PosGoodsReceiveNoteApiController::class, 'index'])->name('grns.index');
    Route::post('grns', [PosGoodsReceiveNoteApiController::class, 'storeDirect'])->name('grns.store-direct');
    Route::get('grns/{grn}', [PosGoodsReceiveNoteApiController::class, 'show'])->name('grns.show');
    Route::post('grns/{grn}/pay',     [PosGoodsReceiveNoteApiController::class, 'pay'])    ->name('grns.pay');
    Route::post('grns/{grn}/approve', [PosGoodsReceiveNoteApiController::class, 'approve'])->name('grns.approve');
    Route::post('grns/{grn}/reject',  [PosGoodsReceiveNoteApiController::class, 'reject']) ->name('grns.reject');
    Route::get('purchase-orders/{purchase}/grn-form', [PosGoodsReceiveNoteApiController::class, 'createForm'])->name('grns.create-form');
    Route::post('purchase-orders/{purchase}/grns', [PosGoodsReceiveNoteApiController::class, 'store'])->name('grns.store');

    Route::get('customers', [PosCustomerApiController::class, 'index'])->name('customers.index');
    Route::post('customers', [PosCustomerApiController::class, 'store'])->name('customers.store');
    Route::post('customers/import', [PosCustomerApiController::class, 'import'])->name('customers.import');
    Route::get('customers/{customer}', [PosCustomerApiController::class, 'show'])->name('customers.show');
    Route::patch('customers/{customer}', [PosCustomerApiController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customer}', [PosCustomerApiController::class, 'destroy'])->name('customers.destroy');
    Route::get('customers/{customer}/subscriptions', [PosSubscriptionApiController::class, 'forCustomer'])->name('customers.subscriptions.index');

    Route::get('customer-categories', [PosCustomerCategoryApiController::class, 'index'])->name('customer-categories.index');
    Route::post('customer-categories', [PosCustomerCategoryApiController::class, 'store'])->name('customer-categories.store');
    Route::patch('customer-categories/{category}', [PosCustomerCategoryApiController::class, 'update'])->name('customer-categories.update');
    Route::delete('customer-categories/{category}', [PosCustomerCategoryApiController::class, 'destroy'])->name('customer-categories.destroy');

    Route::get('suppliers', [PosSupplierApiController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [PosSupplierApiController::class, 'store'])->name('suppliers.store');
    Route::post('suppliers/import', [PosSupplierApiController::class, 'import'])->name('suppliers.import');
    Route::get('suppliers/{supplier}', [PosSupplierApiController::class, 'show'])->name('suppliers.show');
    Route::patch('suppliers/{supplier}', [PosSupplierApiController::class, 'update'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [PosSupplierApiController::class, 'destroy'])->name('suppliers.destroy');

    Route::get('supplier-categories', [PosSupplierCategoryApiController::class, 'index'])->name('supplier-categories.index');
    Route::post('supplier-categories', [PosSupplierCategoryApiController::class, 'store'])->name('supplier-categories.store');
    Route::patch('supplier-categories/{category}', [PosSupplierCategoryApiController::class, 'update'])->name('supplier-categories.update');
    Route::delete('supplier-categories/{category}', [PosSupplierCategoryApiController::class, 'destroy'])->name('supplier-categories.destroy');

    Route::get('purchase-orders', [PosPurchaseOrderApiController::class, 'index'])->name('purchase-orders.index');
    Route::post('purchase-orders', [PosPurchaseOrderApiController::class, 'store'])->name('purchase-orders.store');
    Route::get('purchase-orders/{purchase}', [PosPurchaseOrderApiController::class, 'show'])->name('purchase-orders.show');
    Route::post('purchase-orders/{purchase}/place', [PosPurchaseOrderApiController::class, 'placeOrder'])->name('purchase-orders.place');
    Route::post('purchase-orders/{purchase}/receive', [PosPurchaseOrderApiController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchase}/cancel', [PosPurchaseOrderApiController::class, 'cancel'])->name('purchase-orders.cancel');

    Route::post('expenses/bills', [PosExpenseBillApiController::class, 'store'])->name('expenses.bills.store');
    Route::get('expenses/bills', [\Modules\Pos\Http\Controllers\Api\PosExpenseBillListApiController::class, 'index'])->name('expenses.bills.index');
    Route::get('expenses/bills/{bill}', [PosExpenseBillApiController::class, 'show'])->name('expenses.bills.show');
    Route::post('expenses/bills/{bill}/pay', [PosExpenseBillApiController::class, 'pay'])->name('expenses.bills.pay');
    Route::delete('expenses/bills/{bill}', [PosExpenseBillApiController::class, 'destroy'])->name('expenses.bills.destroy');
    Route::get('expenses/bill-assignment-targets', [PosExpenseBillAssignmentApiController::class, 'index'])->name('expenses.bill-assignment-targets');
    Route::get('expenses/rentals', [\Modules\Pos\Http\Controllers\Api\PosExpenseRentalListApiController::class, 'index'])->name('expenses.rentals.index');
    Route::get('expenses/modifications', [PosExpenseModificationApiController::class, 'index'])->name('expenses.modifications.index');
    Route::post('expenses/modifications', [PosExpenseModificationApiController::class, 'store'])->name('expenses.modifications.store');
    Route::get('expenses/modifications/{modification}', [PosExpenseModificationApiController::class, 'show'])->name('expenses.modifications.show');
    Route::delete('expenses/modifications/{modification}', [PosExpenseModificationApiController::class, 'destroy'])->name('expenses.modifications.destroy');
    Route::get('hr/employees', [\Modules\Pos\Http\Controllers\Api\PosHrEmployeeListApiController::class, 'index'])->name('hr.employees.index');
    Route::post('hr/employees', [\Modules\Pos\Http\Controllers\Api\PosHrEmployeeListApiController::class, 'store'])->name('hr.employees.store');
    Route::get('hr/employees/{employee}', [\Modules\Pos\Http\Controllers\Api\PosHrEmployeeListApiController::class, 'show'])->name('hr.employees.show');
    Route::get('hr/departments', [\Modules\Pos\Http\Controllers\Api\PosHrDepartmentListApiController::class, 'index'])->name('hr.departments.index');
    Route::post('hr/departments', [\Modules\Pos\Http\Controllers\Api\PosHrDepartmentListApiController::class, 'store'])->name('hr.departments.store');
    Route::delete('hr/departments/{department}', [\Modules\Pos\Http\Controllers\Api\PosHrDepartmentListApiController::class, 'destroy'])->name('hr.departments.destroy');
    Route::get('hr/job-titles', [\Modules\Pos\Http\Controllers\Api\PosHrJobTitleListApiController::class, 'index'])->name('hr.job-titles.index');
    Route::post('hr/job-titles', [\Modules\Pos\Http\Controllers\Api\PosHrJobTitleListApiController::class, 'store'])->name('hr.job-titles.store');
    Route::put('hr/job-titles/{jobTitle}', [\Modules\Pos\Http\Controllers\Api\PosHrJobTitleListApiController::class, 'update'])->name('hr.job-titles.update');
    Route::delete('hr/job-titles/{jobTitle}', [\Modules\Pos\Http\Controllers\Api\PosHrJobTitleListApiController::class, 'destroy'])->name('hr.job-titles.destroy');
    Route::get('hr/allowance-types', [\Modules\Pos\Http\Controllers\Api\PosHrAllowanceTypeApiController::class, 'index'])->name('hr.allowance-types.index');
    Route::post('hr/allowance-types', [\Modules\Pos\Http\Controllers\Api\PosHrAllowanceTypeApiController::class, 'store'])->name('hr.allowance-types.store');
    Route::delete('hr/allowance-types/{allowanceType}', [\Modules\Pos\Http\Controllers\Api\PosHrAllowanceTypeApiController::class, 'destroy'])->name('hr.allowance-types.destroy');
    Route::get('hr/attendance', [\Modules\Pos\Http\Controllers\Api\PosHrAttendanceApiController::class, 'index'])->name('hr.attendance.index');
    Route::post('hr/attendance/import', [\Modules\Pos\Http\Controllers\Api\PosHrAttendanceApiController::class, 'import'])->name('hr.attendance.import');

    Route::get('hr/payroll/rule-sets',                                     [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'index'])->name('hr.payroll.rule-sets.index');
    Route::post('hr/payroll/rule-sets',                                    [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'store'])->name('hr.payroll.rule-sets.store');
    Route::get('hr/payroll/rule-sets/{ruleSet}',                           [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'show'])->name('hr.payroll.rule-sets.show');
    Route::patch('hr/payroll/rule-sets/{ruleSet}',                         [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'update'])->name('hr.payroll.rule-sets.update');
    Route::delete('hr/payroll/rule-sets/{ruleSet}',                        [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'destroy'])->name('hr.payroll.rule-sets.destroy');
    Route::post('hr/payroll/rule-sets/{ruleSet}/rules',                    [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'storeRule'])->name('hr.payroll.rules.store');
    Route::patch('hr/payroll/rule-sets/{ruleSet}/rules/{rule}',            [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'updateRule'])->name('hr.payroll.rules.update');
    Route::delete('hr/payroll/rule-sets/{ruleSet}/rules/{rule}',           [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'destroyRule'])->name('hr.payroll.rules.destroy');
    Route::get('hr/payroll/templates',                                     [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'templateIndex'])->name('hr.payroll.templates.index');
    Route::post('hr/payroll/templates/{key}/install',                      [\Modules\Pos\Http\Controllers\Api\PosHrPayrollRuleSetApiController::class, 'templateInstall'])->name('hr.payroll.templates.install');
    Route::get('hr/payroll/cycles', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'index'])->name('hr.payroll.cycles.index');
    Route::post('hr/payroll/cycles', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'store'])->name('hr.payroll.cycles.store');
    Route::get('hr/payroll/cycles/{cycle}', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'show'])->name('hr.payroll.cycles.show');
    Route::delete('hr/payroll/cycles/{cycle}', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'destroy'])->name('hr.payroll.cycles.destroy');
    Route::post('hr/payroll/cycles/{cycle}/compute', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'compute'])->name('hr.payroll.cycles.compute');
    Route::post('hr/payroll/cycles/{cycle}/finalize', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'finalize'])->name('hr.payroll.cycles.finalize');
    Route::post('hr/payroll/cycles/{cycle}/payment', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'payment'])->name('hr.payroll.cycles.payment');
    Route::get('hr/payroll/cycles/{cycle}/salary-sheet', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollCycleApiController::class, 'salarySheet'])->name('hr.payroll.cycles.salary-sheet');
    Route::post('hr/payroll/cycles/{cycle}/items/{item}/recompute', [\Modules\Pos\Http\Controllers\Api\PosHrPayrollItemApiController::class, 'recompute'])->name('hr.payroll.items.recompute');
    Route::get('rentals', [PosRentalApiController::class, 'index'])->name('rentals.index');
    Route::post('rentals', [PosRentalApiController::class, 'store'])->name('rentals.store');
    Route::get('rentals/{rental}', [PosRentalApiController::class, 'show'])->name('rentals.show');
    Route::post('rentals/{rental}/pay', [PosRentalApiController::class, 'pay'])->name('rentals.pay');
    Route::delete('rentals/{rental}', [PosRentalApiController::class, 'destroy'])->name('rentals.destroy');

    Route::get('finance/flow', [PosFinanceFlowApiController::class, 'index'])->name('finance.flow');

    // Notifications
    Route::get('notifications', [PosNotificationApiController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [PosNotificationApiController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('notifications/settings', [PosNotificationApiController::class, 'settingsShow'])->name('notifications.settings.show');
    Route::put('notifications/settings', [PosNotificationApiController::class, 'settingsUpdate'])->name('notifications.settings.update');
    Route::post('notifications/{id}/read', [PosNotificationApiController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/{id}/unread', [PosNotificationApiController::class, 'markUnread'])->name('notifications.unread');
    Route::delete('notifications/clear-all', [PosNotificationApiController::class, 'clearAll'])->name('notifications.clear-all');
    Route::delete('notifications/{id}', [PosNotificationApiController::class, 'destroy'])->whereNumber('id')->name('notifications.destroy');

    Route::get('properties', [PosPropertyApiController::class, 'index'])->name('properties.index');
    Route::post('properties', [PosPropertyApiController::class, 'store'])->name('properties.store');
    Route::delete('properties/{property}', [PosPropertyApiController::class, 'destroy'])->name('properties.destroy');

    Route::get('loans', [PosLoanApiController::class, 'index'])->name('loans.index');
    Route::post('loans', [PosLoanApiController::class, 'store'])->name('loans.store');
    Route::get('loans/{loan}', [PosLoanApiController::class, 'show'])->name('loans.show');
    Route::post('loans/{loan}/pay', [PosLoanApiController::class, 'pay'])->name('loans.pay');
    Route::delete('loans/{loan}', [PosLoanApiController::class, 'destroy'])->name('loans.destroy');
    Route::get('banks', [\Modules\Pos\Http\Controllers\Api\PosBankApiController::class, 'index'])->name('banks.index');
    Route::get('bank-types', [PosAccountApiController::class, 'bankTypes'])->name('bank-types.index');
    Route::get('accounts',  [PosAccountApiController::class, 'index'])->name('accounts.index');
    Route::post('accounts', [PosAccountApiController::class, 'store'])->name('accounts.store');

    Route::get('design-studio/designs',              [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'index'])->name('design-studio.designs.index');
    Route::post('design-studio/designs',             [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'store'])->name('design-studio.designs.store');
    Route::get('design-studio/designs/{design}',     [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'show'])->name('design-studio.designs.show');
    Route::patch('design-studio/designs/{design}',   [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'update'])->name('design-studio.designs.update');
    Route::delete('design-studio/designs/{design}',  [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'destroy'])->name('design-studio.designs.destroy');
    Route::get('design-studio/proposals',                         [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'proposals'])->name('design-studio.proposals.index');
    Route::post('design-studio/proposals',                        [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'storeProposal'])->name('design-studio.proposals.store');
    Route::post('design-studio/proposals/ai-content',             [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'aiProposalContent'])->name('design-studio.proposals.ai-content');
    Route::post('design-studio/proposals/{group}/ai-fill',        [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'aiProposalFill'])->name('design-studio.proposals.ai-fill');
    Route::post('design-studio/proposals/{group}/pages',          [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'addProposalPage'])->name('design-studio.proposals.add-page');
    Route::get('design-studio/proposals/{group}/pages',           [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'proposalPages'])->name('design-studio.proposals.pages');
    Route::delete('design-studio/proposals/{group}',              [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'destroyProposal'])->name('design-studio.proposals.destroy');
    Route::post('design-studio/proposals/{group}/link-invoice',   [\Modules\Pos\Http\Controllers\Api\PosDesignStudioApiController::class, 'linkProposalToInvoice'])->name('design-studio.proposals.link-invoice');
    Route::post('design-studio/ai-chat',                                  [\Modules\Pos\Http\Controllers\Api\DesignAiChatApiController::class, 'chat'])->name('design-studio.ai-chat');
    Route::post('design-studio/voice',                                    [\Modules\Pos\Http\Controllers\Api\DesignAiChatApiController::class, 'voice'])->name('design-studio.voice');
    Route::get ('design-studio/ai-image-job/{jobId}',                    [\Modules\Pos\Http\Controllers\Api\DesignAiChatApiController::class, 'imageJobStatus'])->name('design-studio.ai-image-job');

    Route::get  ('service/requests',                           [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'requests'])->name('service.requests.index');
    Route::post ('service/requests',                           [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'storeRequest'])->name('service.requests.store');
    Route::patch('service/requests/{serviceRequest}/status',   [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'updateRequestStatus'])->name('service.requests.status');
    Route::get ('service/catalog',                         [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'catalog'])->name('service.catalog.index');
    Route::post('service/catalog',                         [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'store'])->name('service.catalog.store');
    Route::get   ('service/catalog/{serviceItem}',          [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'show'])->name('service.catalog.show');
    Route::patch ('service/catalog/{serviceItem}',          [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'update'])->name('service.catalog.update');
    Route::delete('service/catalog/{serviceItem}',          [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'destroy'])->name('service.catalog.destroy');
    Route::put   ('service/catalog/{serviceItem}/employees',[\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'syncEmployees'])->name('service.catalog.employees.sync');
    Route::put   ('service/catalog/{serviceItem}/products', [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'syncProducts'])->name('service.catalog.products.sync');
    Route::get ('service/categories',                       [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'categories'])->name('service.categories.index');
    Route::post('service/categories',                       [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'storeCategory'])->name('service.categories.store');
    Route::delete('service/categories/{serviceCategory}',   [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'destroyCategory'])->name('service.categories.destroy');

    Route::get('stock-audits', [PosStockAuditApiController::class, 'index'])->name('stock-audits.index');
    Route::post('stock-audits', [PosStockAuditApiController::class, 'store'])->name('stock-audits.store');
    Route::get('stock-audits/{stockAudit}', [PosStockAuditApiController::class, 'show'])->name('stock-audits.show');
    Route::put('stock-audits/{stockAudit}/lines', [PosStockAuditApiController::class, 'saveLines'])->name('stock-audits.save-lines');
    Route::post('stock-audits/{stockAudit}/finalize', [PosStockAuditApiController::class, 'finalize'])->name('stock-audits.finalize');
    Route::delete('stock-audits/{stockAudit}', [PosStockAuditApiController::class, 'destroy'])->name('stock-audits.destroy');

    Route::get('stock-transfers', [PosStockTransferApiController::class, 'index'])->name('stock-transfers.index');
    Route::post('stock-transfers', [PosStockTransferApiController::class, 'store'])->name('stock-transfers.store');
    Route::get('stock-transfers/{stockTransfer}', [PosStockTransferApiController::class, 'show'])->name('stock-transfers.show');
    Route::post('stock-transfers/{stockTransfer}/receive', [PosStockTransferApiController::class, 'receive'])->name('stock-transfers.receive');
    Route::post('stock-transfers/{stockTransfer}/cancel', [PosStockTransferApiController::class, 'cancel'])->name('stock-transfers.cancel');

    // User Management
    Route::get   ('me',             [PosUserManagementApiController::class, 'me'])     ->name('users.me');
    Route::get   ('users',          [PosUserManagementApiController::class, 'index'])  ->name('users.index');
    Route::post  ('users',          [PosUserManagementApiController::class, 'store'])  ->name('users.store');
    Route::put   ('users/{member}', [PosUserManagementApiController::class, 'update']) ->name('users.update');
    Route::delete('users/{member}', [PosUserManagementApiController::class, 'destroy'])->name('users.destroy');

    // Role Management
    Route::get   ('roles',        [PosRoleManagementApiController::class, 'index'])  ->name('roles.index');
    Route::post  ('roles',        [PosRoleManagementApiController::class, 'store'])  ->name('roles.store');
    Route::put   ('roles/{role}', [PosRoleManagementApiController::class, 'update']) ->name('roles.update');
    Route::delete('roles/{role}', [PosRoleManagementApiController::class, 'destroy'])->name('roles.destroy');

    // Branch Management
    Route::get   ('branches',          [PosBranchManagementApiController::class, 'index'])  ->name('branches.index');
    Route::post  ('branches',          [PosBranchManagementApiController::class, 'store'])  ->name('branches.store');
    Route::put   ('branches/{branch}', [PosBranchManagementApiController::class, 'update']) ->name('branches.update');
    Route::delete('branches/{branch}', [PosBranchManagementApiController::class, 'destroy'])->name('branches.destroy');

    // Counters
    Route::post  ('verify-password',   [PosRegisterLockApiController::class, 'verifyPassword'])->name('verify-password');

    Route::get   ('counters',          [PosCounterApiController::class, 'index'])  ->name('counters.index');
    Route::post  ('counters',          [PosCounterApiController::class, 'store'])  ->name('counters.store');
    Route::patch ('counters/{counter}', [PosCounterApiController::class, 'update']) ->name('counters.update');
    Route::delete('counters/{counter}', [PosCounterApiController::class, 'destroy'])->name('counters.destroy');

    // Cashiers
    Route::get   ('cashiers',           [PosCashierApiController::class, 'index'])  ->name('cashiers.index');
    Route::post  ('cashiers',           [PosCashierApiController::class, 'store'])  ->name('cashiers.store');
    Route::patch ('cashiers/{cashier}', [PosCashierApiController::class, 'update']) ->name('cashiers.update');
    Route::delete('cashiers/{cashier}', [PosCashierApiController::class, 'destroy'])->name('cashiers.destroy');

    // Guide AI Chat
    Route::post('guide/chat',  [\Modules\Pos\Http\Controllers\Api\PosGuideChatApiController::class, 'chat'])->name('guide.chat');
    Route::post('guide/voice', [\Modules\Pos\Http\Controllers\Api\PosGuideChatApiController::class, 'voice'])->name('guide.voice');

    // CRM
    Route::get   ('crm/projects',                      [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'projects'])    ->name('crm.projects.index');
    Route::post  ('crm/projects',                      [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'createProject'])->name('crm.projects.store');
    Route::get   ('crm/projects/{project}/pipeline',   [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'pipeline'])    ->name('crm.projects.pipeline');
    Route::get   ('crm/projects/{project}/stages',     [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'stages'])       ->name('crm.projects.stages.index');
    Route::post  ('crm/projects/{project}/stages',     [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'createStage'])  ->name('crm.projects.stages.store');
    Route::post  ('crm/projects/{project}/stages/reorder', [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'reorderStages'])->name('crm.projects.stages.reorder');
    Route::put   ('crm/projects/{project}/stages/{stage}', [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'updateStage'])  ->name('crm.projects.stages.update');
    Route::delete('crm/projects/{project}/stages/{stage}', [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'deleteStage'])  ->name('crm.projects.stages.destroy');
    Route::get   ('crm/projects/{project}/stages/{stage}/automations',              [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'automations'])      ->name('crm.stages.automations.index');
    Route::post  ('crm/projects/{project}/stages/{stage}/automations',              [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'createAutomation']) ->name('crm.stages.automations.store');
    Route::put   ('crm/projects/{project}/stages/{stage}/automations/{automation}', [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'updateAutomation']) ->name('crm.stages.automations.update');
    Route::delete('crm/projects/{project}/stages/{stage}/automations/{automation}', [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'deleteAutomation']) ->name('crm.stages.automations.destroy');
    Route::post  ('crm/projects/{project}/leads',      [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'createLead'])  ->name('crm.leads.store');
    Route::put   ('crm/leads/{lead}',                  [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'updateLead'])  ->name('crm.leads.update');
    Route::post  ('crm/leads/{lead}/move-stage',       [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'moveLead'])    ->name('crm.leads.move-stage');
    Route::delete('crm/leads/{lead}',                  [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'deleteLead'])  ->name('crm.leads.destroy');
    Route::get   ('crm/contacts',                      [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'contacts'])    ->name('crm.contacts.index');
    Route::get   ('crm/tasks',                         [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'tasksList'])   ->name('crm.tasks.index');
    Route::post  ('crm/tasks',                         [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'createTask'])  ->name('crm.tasks.store');
    Route::post  ('crm/tasks/{task}/complete',         [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'completeTask'])->name('crm.tasks.complete');
    Route::post  ('crm/tasks/{task}/reopen',           [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'reopenTask'])  ->name('crm.tasks.reopen');
    Route::delete('crm/tasks/{task}',                  [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'deleteTask'])  ->name('crm.tasks.destroy');
    // CRM Forms
    Route::get   ('crm/projects/{project}/forms',                      [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'forms'])          ->name('crm.forms.index');
    Route::post  ('crm/projects/{project}/forms',                      [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'createForm'])      ->name('crm.forms.store');
    Route::get   ('crm/projects/{project}/forms/{form}',               [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'getForm'])         ->name('crm.forms.show');
    Route::put   ('crm/projects/{project}/forms/{form}',               [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'updateForm'])      ->name('crm.forms.update');
    Route::post  ('crm/projects/{project}/forms/{form}/publish',       [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'publishForm'])     ->name('crm.forms.publish');
    Route::post  ('crm/projects/{project}/forms/{form}/unpublish',     [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'unpublishForm'])   ->name('crm.forms.unpublish');
    Route::delete('crm/projects/{project}/forms/{form}',               [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'deleteForm'])      ->name('crm.forms.destroy');
    Route::get   ('crm/form-templates',                                [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'formTemplates'])   ->name('crm.form-templates.index');
    // CRM Custom Fields
    Route::get   ('crm/projects/{project}/custom-fields',              [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'customFieldsList'])->name('crm.custom-fields.index');
    Route::post  ('crm/projects/{project}/custom-fields',              [\Modules\Pos\Http\Controllers\Api\PosCrmApiController::class, 'createCustomField'])->name('crm.custom-fields.store');

    // Mail
    Route::post  ('mail/sync',                        [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'syncMailbox'])    ->name('mail.sync');
    Route::post  ('mail/test',                        [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'testMail'])        ->name('mail.test');
    Route::post  ('mail/verify-credentials',          [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'verifyCredentials'])->name('mail.verify-credentials');
    Route::get   ('mail/threads',                     [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'threads'])        ->name('mail.threads.index');
    Route::get   ('mail/thread',                      [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'thread'])         ->name('mail.thread.show');
    Route::get   ('mail/messages',                    [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'messages'])       ->name('mail.messages.index');
    Route::get   ('mail/messages/{message}',          [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'show'])           ->name('mail.messages.show');
    Route::post  ('mail/messages/{message}/read',     [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'markRead'])       ->name('mail.messages.read');
    Route::post  ('mail/send',                        [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'send'])           ->name('mail.send');
    Route::get   ('mail/templates',                   [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'templates'])      ->name('mail.templates.index');
    Route::post  ('mail/templates',                   [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'createTemplate']) ->name('mail.templates.store');
    Route::delete('mail/templates/{template}',        [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'deleteTemplate']) ->name('mail.templates.destroy');
    Route::get   ('mail/scheduled',                   [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'scheduled'])      ->name('mail.scheduled.index');
    Route::delete('mail/scheduled/{scheduledMail}',   [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'cancelScheduled'])->name('mail.scheduled.destroy');
    Route::get   ('mail/filters',                     [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'filters'])        ->name('mail.filters.index');
    Route::get   ('mail/settings',                    [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'settingsGet'])     ->name('mail.settings.show');
    Route::patch ('mail/settings',                    [\Modules\Pos\Http\Controllers\Api\PosMailApiController::class, 'settingsUpdate'])  ->name('mail.settings.update');

    // Developers
    Route::get   ('developers/keys',                           [\Modules\Developers\Http\Controllers\Api\DeveloperApiKeyApiController::class,  'index'])->name('developers.keys.index');
    Route::post  ('developers/keys',                           [\Modules\Developers\Http\Controllers\Api\DeveloperApiKeyApiController::class,  'store'])->name('developers.keys.store');
    Route::patch ('developers/keys/{id}/toggle',               [\Modules\Developers\Http\Controllers\Api\DeveloperApiKeyApiController::class,  'toggle'])->where('id', '[0-9]+')->name('developers.keys.toggle');
    Route::delete('developers/keys/{id}',                      [\Modules\Developers\Http\Controllers\Api\DeveloperApiKeyApiController::class,  'destroy'])->where('id', '[0-9]+')->name('developers.keys.destroy');
    Route::get   ('developers/webhooks',                       [\Modules\Developers\Http\Controllers\Api\DeveloperWebhookApiController::class, 'index'])->name('developers.webhooks.index');
    Route::post  ('developers/webhooks',                       [\Modules\Developers\Http\Controllers\Api\DeveloperWebhookApiController::class, 'store'])->name('developers.webhooks.store');
    Route::patch ('developers/webhooks/{id}',                  [\Modules\Developers\Http\Controllers\Api\DeveloperWebhookApiController::class, 'update'])->where('id', '[0-9]+')->name('developers.webhooks.update');
    Route::post  ('developers/webhooks/{id}/regenerate-secret',[\Modules\Developers\Http\Controllers\Api\DeveloperWebhookApiController::class, 'regenerateSecret'])->where('id', '[0-9]+')->name('developers.webhooks.regenerate-secret');
    Route::delete('developers/webhooks/{id}',                  [\Modules\Developers\Http\Controllers\Api\DeveloperWebhookApiController::class, 'destroy'])->where('id', '[0-9]+')->name('developers.webhooks.destroy');

    // Advertising Agency — Brand Management + Event Management
    // ── Brands ──────────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/brands',              [\Modules\AdvertisingAgency\Http\Controllers\Api\BrandApiController::class,    'index'])   ->name('brand-mgmt.brands.index');
    Route::post  ('brand-mgmt/brands',              [\Modules\AdvertisingAgency\Http\Controllers\Api\BrandApiController::class,    'store'])   ->name('brand-mgmt.brands.store');
    Route::post  ('brand-mgmt/brands/import',       [\Modules\AdvertisingAgency\Http\Controllers\Api\BrandApiController::class,    'import'])  ->name('brand-mgmt.brands.import');
    Route::put   ('brand-mgmt/brands/{brand}',      [\Modules\AdvertisingAgency\Http\Controllers\Api\BrandApiController::class,    'update'])  ->name('brand-mgmt.brands.update');
    Route::delete('brand-mgmt/brands/{brand}',      [\Modules\AdvertisingAgency\Http\Controllers\Api\BrandApiController::class,    'destroy']) ->name('brand-mgmt.brands.destroy');
    // ── Reporters ────────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/reporters',           [\Modules\AdvertisingAgency\Http\Controllers\Api\ReporterApiController::class, 'index'])   ->name('brand-mgmt.reporters.index');
    Route::post  ('brand-mgmt/reporters',           [\Modules\AdvertisingAgency\Http\Controllers\Api\ReporterApiController::class, 'store'])   ->name('brand-mgmt.reporters.store');
    Route::put   ('brand-mgmt/reporters/{reporter}',[\Modules\AdvertisingAgency\Http\Controllers\Api\ReporterApiController::class, 'update'])  ->name('brand-mgmt.reporters.update');
    Route::delete('brand-mgmt/reporters/{reporter}',[\Modules\AdvertisingAgency\Http\Controllers\Api\ReporterApiController::class, 'destroy']) ->name('brand-mgmt.reporters.destroy');
    // ── Officers ─────────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/officers',            [\Modules\AdvertisingAgency\Http\Controllers\Api\OfficerApiController::class,  'index'])   ->name('brand-mgmt.officers.index');
    Route::post  ('brand-mgmt/officers',            [\Modules\AdvertisingAgency\Http\Controllers\Api\OfficerApiController::class,  'store'])   ->name('brand-mgmt.officers.store');
    Route::put   ('brand-mgmt/officers/{officer}',  [\Modules\AdvertisingAgency\Http\Controllers\Api\OfficerApiController::class,  'update'])  ->name('brand-mgmt.officers.update');
    Route::delete('brand-mgmt/officers/{officer}',  [\Modules\AdvertisingAgency\Http\Controllers\Api\OfficerApiController::class,  'destroy']) ->name('brand-mgmt.officers.destroy');
    // ── Jobs ─────────────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/jobs',                [\Modules\AdvertisingAgency\Http\Controllers\Api\JobApiController::class,      'index'])   ->name('brand-mgmt.jobs.index');
    Route::post  ('brand-mgmt/jobs',                [\Modules\AdvertisingAgency\Http\Controllers\Api\JobApiController::class,      'store'])   ->name('brand-mgmt.jobs.store');
    Route::post  ('brand-mgmt/jobs/import',         [\Modules\AdvertisingAgency\Http\Controllers\Api\JobApiController::class,      'import'])  ->name('brand-mgmt.jobs.import');
    Route::put   ('brand-mgmt/jobs/{job}',          [\Modules\AdvertisingAgency\Http\Controllers\Api\JobApiController::class,      'update'])  ->name('brand-mgmt.jobs.update');
    Route::delete('brand-mgmt/jobs/{job}',          [\Modules\AdvertisingAgency\Http\Controllers\Api\JobApiController::class,      'destroy']) ->name('brand-mgmt.jobs.destroy');
    // ── Agencies ─────────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/agencies',            [\Modules\AdvertisingAgency\Http\Controllers\Api\AgencyApiController::class,   'index'])   ->name('brand-mgmt.agencies.index');
    Route::post  ('brand-mgmt/agencies',            [\Modules\AdvertisingAgency\Http\Controllers\Api\AgencyApiController::class,   'store'])   ->name('brand-mgmt.agencies.store');
    Route::put   ('brand-mgmt/agencies/{agency}',   [\Modules\AdvertisingAgency\Http\Controllers\Api\AgencyApiController::class,   'update'])  ->name('brand-mgmt.agencies.update');
    Route::delete('brand-mgmt/agencies/{agency}',   [\Modules\AdvertisingAgency\Http\Controllers\Api\AgencyApiController::class,   'destroy']) ->name('brand-mgmt.agencies.destroy');
    // ── Salary Sheets ─────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/salary-sheets/next-ref',       [\Modules\AdvertisingAgency\Http\Controllers\Api\SalarySheetApiController::class, 'nextRef'])  ->name('brand-mgmt.salary-sheets.next-ref');
    Route::get   ('brand-mgmt/salary-sheets',                [\Modules\AdvertisingAgency\Http\Controllers\Api\SalarySheetApiController::class, 'index'])    ->name('brand-mgmt.salary-sheets.index');
    Route::post  ('brand-mgmt/salary-sheets',                [\Modules\AdvertisingAgency\Http\Controllers\Api\SalarySheetApiController::class, 'store'])    ->name('brand-mgmt.salary-sheets.store');
    Route::get   ('brand-mgmt/salary-sheets/{id}',           [\Modules\AdvertisingAgency\Http\Controllers\Api\SalarySheetApiController::class, 'show'])     ->name('brand-mgmt.salary-sheets.show');
    Route::put   ('brand-mgmt/salary-sheets/{id}',           [\Modules\AdvertisingAgency\Http\Controllers\Api\SalarySheetApiController::class, 'update'])   ->name('brand-mgmt.salary-sheets.update');
    Route::delete('brand-mgmt/salary-sheets/{id}',           [\Modules\AdvertisingAgency\Http\Controllers\Api\SalarySheetApiController::class, 'destroy'])  ->name('brand-mgmt.salary-sheets.destroy');
    Route::post  ('brand-mgmt/salary-sheets/{id}/save-rows', [\Modules\AdvertisingAgency\Http\Controllers\Api\SalarySheetApiController::class, 'saveRows']) ->name('brand-mgmt.salary-sheets.save-rows');
    // ── Coordinators ─────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/coordinators',               [\Modules\AdvertisingAgency\Http\Controllers\Api\CoordinatorApiController::class, 'index'])   ->name('brand-mgmt.coordinators.index');
    Route::post  ('brand-mgmt/coordinators',               [\Modules\AdvertisingAgency\Http\Controllers\Api\CoordinatorApiController::class, 'store'])   ->name('brand-mgmt.coordinators.store');
    Route::put   ('brand-mgmt/coordinators/{coordinator}', [\Modules\AdvertisingAgency\Http\Controllers\Api\CoordinatorApiController::class, 'update'])  ->name('brand-mgmt.coordinators.update');
    Route::delete('brand-mgmt/coordinators/{coordinator}', [\Modules\AdvertisingAgency\Http\Controllers\Api\CoordinatorApiController::class, 'destroy']) ->name('brand-mgmt.coordinators.destroy');
    // ── Promoters ─────────────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/promoters',              [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterApiController::class, 'index'])   ->name('brand-mgmt.promoters.index');
    Route::post  ('brand-mgmt/promoters',              [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterApiController::class, 'store'])   ->name('brand-mgmt.promoters.store');
    Route::put   ('brand-mgmt/promoters/{promoter}',   [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterApiController::class, 'update'])  ->name('brand-mgmt.promoters.update');
    Route::delete('brand-mgmt/promoters/{promoter}',   [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterApiController::class, 'destroy']) ->name('brand-mgmt.promoters.destroy');
    // ── Promoter Positions ────────────────────────────────────────────────────
    Route::get   ('brand-mgmt/promoter-positions',       [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterPositionApiController::class, 'index'])   ->name('brand-mgmt.promoter-positions.index');
    Route::post  ('brand-mgmt/promoter-positions',       [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterPositionApiController::class, 'store'])   ->name('brand-mgmt.promoter-positions.store');
    Route::put   ('brand-mgmt/promoter-positions/{id}',  [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterPositionApiController::class, 'update'])  ->name('brand-mgmt.promoter-positions.update');
    Route::delete('brand-mgmt/promoter-positions/{id}',  [\Modules\AdvertisingAgency\Http\Controllers\Api\PromoterPositionApiController::class, 'destroy']) ->name('brand-mgmt.promoter-positions.destroy');
});
