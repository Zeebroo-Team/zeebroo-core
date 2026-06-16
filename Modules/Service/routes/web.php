<?php

use Illuminate\Support\Facades\Route;
use Modules\Service\Http\Controllers\ServiceItemController;
use Modules\Service\Http\Controllers\ServiceRequestController;

Route::middleware(['web', 'auth'])->group(function () {
    // Service catalog
    Route::get('/service/catalog',                          [ServiceItemController::class, 'index'])   ->name('service.catalog.index');
    Route::post('/service/catalog',                         [ServiceItemController::class, 'store'])   ->name('service.catalog.store');
    Route::get('/service/catalog/{serviceItem}',            [ServiceItemController::class, 'show'])    ->name('service.catalog.show');
    Route::get('/service/catalog/{serviceItem}/edit',       [ServiceItemController::class, 'edit'])    ->name('service.catalog.edit');
    Route::put('/service/catalog/{serviceItem}',            [ServiceItemController::class, 'update'])  ->name('service.catalog.update');
    Route::delete('/service/catalog/{serviceItem}',         [ServiceItemController::class, 'destroy']) ->name('service.catalog.destroy');

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
