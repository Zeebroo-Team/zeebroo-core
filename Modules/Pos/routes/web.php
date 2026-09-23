<?php

use Illuminate\Support\Facades\Route;
use Modules\Pos\Http\Controllers\BrandMgmtAgencyController;
use Modules\Pos\Http\Controllers\BrandMgmtBrandController;
use Modules\Pos\Http\Controllers\BrandMgmtCoordinatorController;
use Modules\Pos\Http\Controllers\BrandMgmtJobController;
use Modules\Pos\Http\Controllers\BrandMgmtOfficerController;
use Modules\Pos\Http\Controllers\BrandMgmtPromoterController;
use Modules\Pos\Http\Controllers\BrandMgmtPromoterPositionController;
use Modules\Pos\Http\Controllers\BrandMgmtReporterController;
use Modules\Pos\Http\Controllers\BrandMgmtSalarySheetController;
use Modules\Pos\Http\Controllers\CustomerController;
use Modules\Pos\Http\Controllers\EndOfDayController;
use Modules\Pos\Http\Controllers\PosController;
use Modules\Pos\Http\Controllers\PosProductController;
use Modules\Pos\Http\Controllers\SaleController;
use Modules\Pos\Http\Controllers\StockAuditController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/online', [PosController::class, 'online'])->name('pos.online');
    Route::get('/pos/register', [PosController::class, 'register'])->name('pos.register');
    Route::post('/pos/walking-customer', [PosController::class, 'toggleWalkingCustomer'])->name('pos.walking-customer.toggle');
    Route::post('/pos/settings', [PosController::class, 'saveSettings'])->name('pos.settings.save');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/products', [PosProductController::class, 'store'])->name('pos.products.store');

    Route::get('/pos/customers/search', [CustomerController::class, 'search'])->name('pos.customers.search');
    Route::get('/pos/customers', [CustomerController::class, 'index'])->name('pos.customers.index');
    Route::post('/pos/customers', [CustomerController::class, 'store'])->name('pos.customers.store');
    Route::put('/pos/customers/{customer}', [CustomerController::class, 'update'])->name('pos.customers.update');
    Route::delete('/pos/customers/{customer}', [CustomerController::class, 'destroy'])->name('pos.customers.destroy');

    Route::get('/pos/sale-lookup', [SaleController::class, 'saleLookup'])->name('pos.sale-lookup');
    Route::post('/pos/online/modal-return-open', [SaleController::class, 'onlineModalReturnOpen'])->name('pos.online.modal-return-open');
    Route::post('/pos/online/modal-return/{sale}', [SaleController::class, 'onlineModalReturn'])->name('pos.online.modal-return');
    Route::get('/pos/returns', [SaleController::class, 'returnsIndex'])->name('pos.returns.index');
    Route::get('/pos/returns/create', [SaleController::class, 'createReturn'])->name('pos.returns.create');
    Route::post('/pos/returns', [SaleController::class, 'storeOpenReturn'])->name('pos.returns.store-open');
    Route::get('/pos/sales', [SaleController::class, 'index'])->name('pos.sales.index');
    Route::get('/pos/sales/{sale}', [SaleController::class, 'show'])->name('pos.sales.show');
    Route::post('/pos/sales/{sale}/void', [SaleController::class, 'void'])->name('pos.sales.void');
    Route::post('/pos/sales/{sale}/returns', [SaleController::class, 'storeReturn'])->name('pos.sales.returns.store');

    Route::get('/pos/end-of-day', [EndOfDayController::class, 'index'])->name('pos.end-of-day');
    Route::post('/pos/end-of-day/settle', [EndOfDayController::class, 'settle'])->name('pos.end-of-day.settle');

    Route::get('/pos/stock-audits', [StockAuditController::class, 'index'])->name('pos.stock-audits.index');
    Route::get('/pos/stock-audits/create', [StockAuditController::class, 'create'])->name('pos.stock-audits.create');
    Route::post('/pos/stock-audits', [StockAuditController::class, 'store'])->name('pos.stock-audits.store');
    Route::get('/pos/stock-audits/{stockAudit}', [StockAuditController::class, 'show'])->name('pos.stock-audits.show');
    Route::put('/pos/stock-audits/{stockAudit}/lines', [StockAuditController::class, 'saveLines'])->name('pos.stock-audits.save-lines');
    Route::post('/pos/stock-audits/{stockAudit}/finalize', [StockAuditController::class, 'finalize'])->name('pos.stock-audits.finalize');
    Route::delete('/pos/stock-audits/{stockAudit}', [StockAuditController::class, 'destroy'])->name('pos.stock-audits.destroy');

    // Event / Staffing Management (Brands, Reporters, Officers, Coordinators, Promoters,
    // Promoter Positions, Jobs, Agencies, Salary Sheets) — web layer over the
    // Modules\AdvertisingAgency services already used by the desktop app's JSON API.
    Route::get('/pos/brand-mgmt/brands', [BrandMgmtBrandController::class, 'index'])->name('pos.brand-mgmt.brands.index');
    Route::post('/pos/brand-mgmt/brands', [BrandMgmtBrandController::class, 'store'])->name('pos.brand-mgmt.brands.store');
    Route::post('/pos/brand-mgmt/brands/import', [BrandMgmtBrandController::class, 'import'])->name('pos.brand-mgmt.brands.import');
    Route::put('/pos/brand-mgmt/brands/{brand}', [BrandMgmtBrandController::class, 'update'])->name('pos.brand-mgmt.brands.update');
    Route::delete('/pos/brand-mgmt/brands/{brand}', [BrandMgmtBrandController::class, 'destroy'])->name('pos.brand-mgmt.brands.destroy');

    Route::get('/pos/brand-mgmt/reporters', [BrandMgmtReporterController::class, 'index'])->name('pos.brand-mgmt.reporters.index');
    Route::post('/pos/brand-mgmt/reporters', [BrandMgmtReporterController::class, 'store'])->name('pos.brand-mgmt.reporters.store');
    Route::put('/pos/brand-mgmt/reporters/{reporter}', [BrandMgmtReporterController::class, 'update'])->name('pos.brand-mgmt.reporters.update');
    Route::delete('/pos/brand-mgmt/reporters/{reporter}', [BrandMgmtReporterController::class, 'destroy'])->name('pos.brand-mgmt.reporters.destroy');

    Route::get('/pos/brand-mgmt/officers', [BrandMgmtOfficerController::class, 'index'])->name('pos.brand-mgmt.officers.index');
    Route::post('/pos/brand-mgmt/officers', [BrandMgmtOfficerController::class, 'store'])->name('pos.brand-mgmt.officers.store');
    Route::put('/pos/brand-mgmt/officers/{officer}', [BrandMgmtOfficerController::class, 'update'])->name('pos.brand-mgmt.officers.update');
    Route::delete('/pos/brand-mgmt/officers/{officer}', [BrandMgmtOfficerController::class, 'destroy'])->name('pos.brand-mgmt.officers.destroy');

    Route::get('/pos/brand-mgmt/coordinators', [BrandMgmtCoordinatorController::class, 'index'])->name('pos.brand-mgmt.coordinators.index');
    Route::post('/pos/brand-mgmt/coordinators', [BrandMgmtCoordinatorController::class, 'store'])->name('pos.brand-mgmt.coordinators.store');
    Route::put('/pos/brand-mgmt/coordinators/{coordinator}', [BrandMgmtCoordinatorController::class, 'update'])->name('pos.brand-mgmt.coordinators.update');
    Route::delete('/pos/brand-mgmt/coordinators/{coordinator}', [BrandMgmtCoordinatorController::class, 'destroy'])->name('pos.brand-mgmt.coordinators.destroy');

    Route::get('/pos/brand-mgmt/promoters', [BrandMgmtPromoterController::class, 'index'])->name('pos.brand-mgmt.promoters.index');
    Route::post('/pos/brand-mgmt/promoters', [BrandMgmtPromoterController::class, 'store'])->name('pos.brand-mgmt.promoters.store');
    Route::put('/pos/brand-mgmt/promoters/{promoter}', [BrandMgmtPromoterController::class, 'update'])->name('pos.brand-mgmt.promoters.update');
    Route::delete('/pos/brand-mgmt/promoters/{promoter}', [BrandMgmtPromoterController::class, 'destroy'])->name('pos.brand-mgmt.promoters.destroy');

    Route::get('/pos/brand-mgmt/promoter-positions', [BrandMgmtPromoterPositionController::class, 'index'])->name('pos.brand-mgmt.promoter-positions.index');
    Route::post('/pos/brand-mgmt/promoter-positions', [BrandMgmtPromoterPositionController::class, 'store'])->name('pos.brand-mgmt.promoter-positions.store');
    Route::post('/pos/brand-mgmt/promoter-positions/quick-store', [BrandMgmtPromoterPositionController::class, 'quickStore'])->name('pos.brand-mgmt.promoter-positions.quick-store');
    Route::put('/pos/brand-mgmt/promoter-positions/{position}', [BrandMgmtPromoterPositionController::class, 'update'])->name('pos.brand-mgmt.promoter-positions.update');
    Route::delete('/pos/brand-mgmt/promoter-positions/{position}', [BrandMgmtPromoterPositionController::class, 'destroy'])->name('pos.brand-mgmt.promoter-positions.destroy');

    Route::get('/pos/brand-mgmt/jobs', [BrandMgmtJobController::class, 'index'])->name('pos.brand-mgmt.jobs.index');
    Route::post('/pos/brand-mgmt/jobs', [BrandMgmtJobController::class, 'store'])->name('pos.brand-mgmt.jobs.store');
    Route::post('/pos/brand-mgmt/jobs/import', [BrandMgmtJobController::class, 'import'])->name('pos.brand-mgmt.jobs.import');
    Route::put('/pos/brand-mgmt/jobs/{job}', [BrandMgmtJobController::class, 'update'])->name('pos.brand-mgmt.jobs.update');
    Route::delete('/pos/brand-mgmt/jobs/{job}', [BrandMgmtJobController::class, 'destroy'])->name('pos.brand-mgmt.jobs.destroy');

    Route::get('/pos/brand-mgmt/agencies', [BrandMgmtAgencyController::class, 'index'])->name('pos.brand-mgmt.agencies.index');
    Route::post('/pos/brand-mgmt/agencies', [BrandMgmtAgencyController::class, 'store'])->name('pos.brand-mgmt.agencies.store');
    Route::put('/pos/brand-mgmt/agencies/{agency}', [BrandMgmtAgencyController::class, 'update'])->name('pos.brand-mgmt.agencies.update');
    Route::delete('/pos/brand-mgmt/agencies/{agency}', [BrandMgmtAgencyController::class, 'destroy'])->name('pos.brand-mgmt.agencies.destroy');

    Route::get('/pos/brand-mgmt/salary-sheets', [BrandMgmtSalarySheetController::class, 'index'])->name('pos.brand-mgmt.salary-sheets.index');
    Route::get('/pos/brand-mgmt/salary-sheets/create', [BrandMgmtSalarySheetController::class, 'create'])->name('pos.brand-mgmt.salary-sheets.create');
    Route::post('/pos/brand-mgmt/salary-sheets', [BrandMgmtSalarySheetController::class, 'store'])->name('pos.brand-mgmt.salary-sheets.store');
    Route::get('/pos/brand-mgmt/salary-sheets/{salarySheet}', [BrandMgmtSalarySheetController::class, 'show'])->name('pos.brand-mgmt.salary-sheets.show');
    Route::put('/pos/brand-mgmt/salary-sheets/{salarySheet}', [BrandMgmtSalarySheetController::class, 'update'])->name('pos.brand-mgmt.salary-sheets.update');
    Route::put('/pos/brand-mgmt/salary-sheets/{salarySheet}/rows', [BrandMgmtSalarySheetController::class, 'saveRows'])->name('pos.brand-mgmt.salary-sheets.save-rows');
    Route::post('/pos/brand-mgmt/salary-sheets/{salarySheet}/status', [BrandMgmtSalarySheetController::class, 'transitionStatus'])->name('pos.brand-mgmt.salary-sheets.transition');
    Route::delete('/pos/brand-mgmt/salary-sheets/{salarySheet}', [BrandMgmtSalarySheetController::class, 'destroy'])->name('pos.brand-mgmt.salary-sheets.destroy');
});
