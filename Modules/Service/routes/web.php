<?php

use Illuminate\Support\Facades\Route;
use Modules\Service\Http\Controllers\ServiceBundleController;
use Modules\Service\Http\Controllers\ServiceCategoryController;
use Modules\Service\Http\Controllers\ServiceDiscountController;
use Modules\Service\Http\Controllers\ServiceItemController;
use Modules\Service\Http\Controllers\ServiceRequestController;

Route::middleware(['web', 'auth'])->group(function () {
    // Service categories
    Route::get('/service/categories',                         [ServiceCategoryController::class, 'index'])      ->name('service.categories.index');
    Route::post('/service/categories',                        [ServiceCategoryController::class, 'store'])      ->name('service.categories.store');
    Route::post('/service/categories/quick-store',            [ServiceCategoryController::class, 'quickStore']) ->name('service.categories.quick-store');
    Route::post('/service/categories/reorder',               [ServiceCategoryController::class, 'reorder'])    ->name('service.categories.reorder');
    Route::get('/service/categories/{serviceCategory}/edit',  [ServiceCategoryController::class, 'edit'])       ->name('service.categories.edit');
    Route::put('/service/categories/{serviceCategory}',       [ServiceCategoryController::class, 'update'])     ->name('service.categories.update');
    Route::delete('/service/categories/{serviceCategory}',    [ServiceCategoryController::class, 'destroy'])    ->name('service.categories.destroy');

    // Service catalog
    Route::get('/service/catalog',                          [ServiceItemController::class, 'index'])   ->name('service.catalog.index');
    Route::post('/service/catalog',                         [ServiceItemController::class, 'store'])   ->name('service.catalog.store');
    Route::get('/service/catalog/{serviceItem}',            [ServiceItemController::class, 'show'])    ->name('service.catalog.show');
    Route::get('/service/catalog/{serviceItem}/edit',       [ServiceItemController::class, 'edit'])    ->name('service.catalog.edit');
    Route::put('/service/catalog/{serviceItem}',            [ServiceItemController::class, 'update'])  ->name('service.catalog.update');
    Route::delete('/service/catalog/{serviceItem}',         [ServiceItemController::class, 'destroy']) ->name('service.catalog.destroy');

    // Service bundles
    Route::get('/service/bundles',                       [ServiceBundleController::class, 'index'])   ->name('service.bundles.index');
    Route::post('/service/bundles',                      [ServiceBundleController::class, 'store'])   ->name('service.bundles.store');
    Route::get('/service/bundles/{serviceBundle}',       [ServiceBundleController::class, 'show'])    ->name('service.bundles.show');
    Route::get('/service/bundles/{serviceBundle}/edit',  [ServiceBundleController::class, 'edit'])    ->name('service.bundles.edit');
    Route::put('/service/bundles/{serviceBundle}',       [ServiceBundleController::class, 'update'])  ->name('service.bundles.update');
    Route::delete('/service/bundles/{serviceBundle}',    [ServiceBundleController::class, 'destroy']) ->name('service.bundles.destroy');

    // Service discounts
    Route::post('/service/catalog/{serviceItem}/discounts',                    [ServiceDiscountController::class, 'store'])   ->name('service.discounts.store');
    Route::delete('/service/catalog/{serviceItem}/discounts/{discount}',       [ServiceDiscountController::class, 'destroy']) ->name('service.discounts.destroy');

    // Service requests
    Route::get('/service/requests',                              [ServiceRequestController::class, 'index'])          ->name('service.requests.index');
    Route::post('/service/requests',                             [ServiceRequestController::class, 'store'])          ->name('service.requests.store');
    Route::get('/service/requests/{serviceRequest}',             [ServiceRequestController::class, 'show'])           ->name('service.requests.show');
    Route::get('/service/requests/{serviceRequest}/edit',        [ServiceRequestController::class, 'edit'])           ->name('service.requests.edit');
    Route::put('/service/requests/{serviceRequest}',             [ServiceRequestController::class, 'update'])         ->name('service.requests.update');
    Route::post('/service/requests/{serviceRequest}/in-progress',[ServiceRequestController::class, 'markInProgress']) ->name('service.requests.in-progress');
    Route::post('/service/requests/{serviceRequest}/complete',   [ServiceRequestController::class, 'markCompleted'])  ->name('service.requests.complete');
    Route::post('/service/requests/{serviceRequest}/cancel',     [ServiceRequestController::class, 'cancel'])         ->name('service.requests.cancel');
    Route::delete('/service/requests/{serviceRequest}',          [ServiceRequestController::class, 'destroy'])        ->name('service.requests.destroy');
});
