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
use Modules\Pos\Http\Controllers\Api\PosFinanceFlowApiController;
use Modules\Pos\Http\Controllers\Api\PosLoanApiController;
use Modules\Pos\Http\Controllers\Api\PosPropertyApiController;
use Modules\Pos\Http\Controllers\Api\PosRentalApiController;
use Modules\Pos\Http\Controllers\Api\PosExpenseModificationApiController;
use Modules\Pos\Http\Controllers\Api\PosEndOfDayApiController;
use Modules\Pos\Http\Controllers\Api\PosQuotationApiController;
use Modules\Pos\Http\Controllers\Api\PosInvoiceApiController;
use Modules\Pos\Http\Controllers\Api\PosSettingsApiController;
use Modules\Pos\Http\Controllers\Api\PosCustomerApiController;
use Modules\Pos\Http\Controllers\Api\PosSupplierApiController;
use Modules\Pos\Http\Controllers\Api\PosReturnReasonsApiController;
use Modules\Pos\Http\Controllers\Api\PosGoodsReceiveNoteApiController;
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
use Modules\Pos\Http\Controllers\Api\PosRoleManagementApiController;

Route::prefix('v1/pos')->group(function (): void {
    Route::post('auth/token',             [PosAuthApiController::class, 'token'])->name('auth.token');
    Route::post('auth/register',          [PosAuthApiController::class, 'register'])->name('auth.register');
    Route::get ('auth/business-categories',[PosAuthApiController::class, 'businessCategories'])->name('auth.business-categories');

    Route::get('docs', [PosApiDocsController::class, 'index'])->name('pos.docs');
    Route::get('docs/openapi.yaml', [PosApiDocsController::class, 'openapi'])->name('pos.docs.openapi');
    Route::get('docs/openapi.json', [PosApiDocsController::class, 'openapiJson'])->name('pos.docs.openapi.json');
    Route::get('docs/readme', [PosApiDocsController::class, 'readme'])->name('pos.docs.readme');
});

Route::middleware(['auth:sanctum'])->prefix('v1/pos')->name('pos.')->group(function (): void {
    Route::get ('auth/me',     [PosAuthApiController::class, 'me'])->name('auth.me');
    Route::post('auth/revoke', [PosAuthApiController::class, 'revoke'])->name('auth.revoke');
    Route::get ('businesses', [PosBusinessesApiController::class, 'index'])->name('businesses.index');
    Route::post('businesses', [PosBusinessesApiController::class, 'store'])->name('businesses.store');
    Route::get('online/bootstrap', PosOnlineBootstrapApiController::class)->name('online.bootstrap');
    Route::get('online/branches', PosBranchesApiController::class)->name('online.branches');

    Route::get('online/categories', [PosCatalogApiController::class, 'categories'])->name('online.categories');
    Route::get('online/products', [PosCatalogApiController::class, 'products'])->name('online.products');
    Route::get('online/products/{id}', [PosCatalogApiController::class, 'show'])->where('id', '[0-9]+')->name('online.products.show');
    Route::get('online/products/{id}/sales-chart', \Modules\Pos\Http\Controllers\Api\PosProductSalesChartApiController::class)->where('id', '[0-9]+')->name('online.products.sales-chart');
    Route::get('online/products/sku/{sku}', [PosCatalogApiController::class, 'productBySku'])->name('online.products.sku');

    Route::post('online/products', [PosProductApiController::class, 'store'])->name('online.products.store');
    Route::patch('online/products/{product}', [PosProductApiController::class, 'update'])->name('online.products.update');
    Route::delete('online/products/{product}', [PosProductApiController::class, 'destroy'])->name('online.products.destroy');
    Route::get('online/file-manager', [PosFileManagerApiController::class, 'browse'])->name('online.file-manager.browse');
    Route::post('online/file-manager/upload', [PosFileManagerApiController::class, 'upload'])->name('online.file-manager.upload');
    Route::post('online/checkout', [PosCheckoutApiController::class, 'store'])->name('online.checkout');

    Route::get('online/features', [PosSettingsApiController::class, 'features'])->name('online.features');
    Route::put('online/features', [PosSettingsApiController::class, 'updateFeatures'])->name('online.features.update');
    Route::get('online/sync-status', [PosSettingsApiController::class, 'syncStatus'])->name('online.sync-status');
    Route::get('online/settings', [PosSettingsApiController::class, 'show'])->name('online.settings.show');
    Route::put('online/settings', [PosSettingsApiController::class, 'update'])->name('online.settings.update');
    Route::patch('online/settings', [PosSettingsApiController::class, 'update']);

    // Invoices
    Route::get   ('invoices',                          [PosInvoiceApiController::class, 'index']          )->name('invoices.index');
    Route::post  ('invoices',                          [PosInvoiceApiController::class, 'store']          )->name('invoices.store');
    Route::get   ('invoices/{invoice}',                [PosInvoiceApiController::class, 'show']           )->name('invoices.show');
    Route::patch ('invoices/{invoice}',                [PosInvoiceApiController::class, 'update']         )->name('invoices.update');
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

    Route::get ('eod',            [PosEndOfDayApiController::class,    'status'])->name('eod.status');
    Route::post('eod/settle',     [PosEndOfDayApiController::class,    'settle'])->name('eod.settle');
    Route::get ('today-summary',      [PosTodaySummaryApiController::class, 'show'])->name('today-summary');
    Route::get ('expenses/overview',  [PosExpensesOverviewApiController::class, 'show'])->name('expenses.overview');
    Route::get ('profit-report',      [PosProfitReportApiController::class,     'show'])->name('profit-report');
    Route::get ('payroll-overview',   [PosPayrollOverviewApiController::class,  'show'])->name('payroll-overview');

    Route::get('sales',         [PosSaleApiController::class, 'index']  )->name('sales.index');
    Route::get('sales/history', [PosSaleApiController::class, 'history'])->name('sales.history');
    Route::get('sales/{sale}',  [PosSaleApiController::class, 'show']   )->name('sales.show');
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
    Route::get('categories', [PosProductCategoryApiController::class, 'index'])->name('categories.index');
    Route::get('categories/parent-options', [PosProductCategoryApiController::class, 'parentOptions'])->name('categories.parent-options');
    Route::post('categories', [PosProductCategoryApiController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [PosProductCategoryApiController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [PosProductCategoryApiController::class, 'destroy'])->name('categories.destroy');

    Route::get('cheques', [PosGrnChequeApiController::class, 'index'])->name('cheques.index');
    Route::post('cheques/{cheque}/clear', [PosGrnChequeApiController::class, 'clear'])->name('cheques.clear');

    Route::get('grns', [PosGoodsReceiveNoteApiController::class, 'index'])->name('grns.index');
    Route::get('grns/{grn}', [PosGoodsReceiveNoteApiController::class, 'show'])->name('grns.show');
    Route::post('grns/{grn}/pay', [PosGoodsReceiveNoteApiController::class, 'pay'])->name('grns.pay');
    Route::get('purchase-orders/{purchase}/grn-form', [PosGoodsReceiveNoteApiController::class, 'createForm'])->name('grns.create-form');
    Route::post('purchase-orders/{purchase}/grns', [PosGoodsReceiveNoteApiController::class, 'store'])->name('grns.store');

    Route::get('customers', [PosCustomerApiController::class, 'index'])->name('customers.index');
    Route::post('customers', [PosCustomerApiController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [PosCustomerApiController::class, 'show'])->name('customers.show');
    Route::patch('customers/{customer}', [PosCustomerApiController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customer}', [PosCustomerApiController::class, 'destroy'])->name('customers.destroy');

    Route::get('suppliers', [PosSupplierApiController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [PosSupplierApiController::class, 'store'])->name('suppliers.store');
    Route::get('suppliers/{supplier}', [PosSupplierApiController::class, 'show'])->name('suppliers.show');
    Route::patch('suppliers/{supplier}', [PosSupplierApiController::class, 'update'])->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [PosSupplierApiController::class, 'destroy'])->name('suppliers.destroy');

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
    Route::get('hr/allowance-types', [\Modules\Pos\Http\Controllers\Api\PosHrAllowanceTypeApiController::class, 'index'])->name('hr.allowance-types.index');
    Route::post('hr/allowance-types', [\Modules\Pos\Http\Controllers\Api\PosHrAllowanceTypeApiController::class, 'store'])->name('hr.allowance-types.store');
    Route::delete('hr/allowance-types/{allowanceType}', [\Modules\Pos\Http\Controllers\Api\PosHrAllowanceTypeApiController::class, 'destroy'])->name('hr.allowance-types.destroy');

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

    Route::get ('service/requests',                        [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'requests'])->name('service.requests.index');
    Route::patch('service/requests/{serviceRequest}/status', [\Modules\Pos\Http\Controllers\Api\PosServiceApiController::class, 'updateRequestStatus'])->name('service.requests.status');
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
});
