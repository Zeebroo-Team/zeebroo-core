<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\Api\EcommerceApiController;
use Modules\Ecommerce\Http\Controllers\StorefrontController;

// Desktop (Electron) E-Commerce tab — manage the business's online store.
Route::middleware(['auth:sanctum'])->prefix('v1/pos')->name('pos.')->group(function () {

    $c = EcommerceApiController::class;

    // Dashboard
    Route::get   ('ecom/dashboard',              [$c, 'dashboard'])        ->name('ecom.dashboard');

    // Website / store settings
    Route::get   ('ecom/store',                  [$c, 'storeShow'])        ->name('ecom.store.show');
    Route::patch ('ecom/store',                  [$c, 'storeUpdate'])      ->name('ecom.store.update');

    // Online products (catalog products published to the website)
    Route::get   ('ecom/listings',               [$c, 'listingIndex'])     ->name('ecom.listings.index');
    Route::get   ('ecom/listings/candidates',    [$c, 'listingCandidates'])->name('ecom.listings.candidates');
    Route::post  ('ecom/listings',               [$c, 'listingStore'])     ->name('ecom.listings.store');
    Route::patch ('ecom/listings/{id}',          [$c, 'listingUpdate'])    ->where('id', '[0-9]+')->name('ecom.listings.update');
    Route::delete('ecom/listings/{id}',          [$c, 'listingDestroy'])   ->where('id', '[0-9]+')->name('ecom.listings.destroy');

    // Web orders
    Route::get   ('ecom/orders',                 [$c, 'orderIndex'])       ->name('ecom.orders.index');
    Route::get   ('ecom/orders/{id}',            [$c, 'orderShow'])        ->where('id', '[0-9]+')->name('ecom.orders.show');
    Route::patch ('ecom/orders/{id}',            [$c, 'orderUpdate'])      ->where('id', '[0-9]+')->name('ecom.orders.update');

    // Payment gateways (Stripe, PayHere) — secrets are write-only
    Route::get   ('ecom/payment-gateways',           [$c, 'gatewayIndex'])  ->name('ecom.gateways.index');
    Route::patch ('ecom/payment-gateways/{gateway}', [$c, 'gatewayUpdate']) ->where('gateway', '[a-z]+')->name('ecom.gateways.update');

    // Website templates (selection is saved via PATCH ecom/store { template })
    Route::get   ('ecom/templates',              [$c, 'templateIndex'])    ->name('ecom.templates.index');

    // Homepage slider
    Route::get   ('ecom/slides',                 [$c, 'slideIndex'])       ->name('ecom.slides.index');
    Route::post  ('ecom/slides',                 [$c, 'slideStore'])       ->name('ecom.slides.store');
    Route::post  ('ecom/slides/reorder',         [$c, 'slideReorder'])     ->name('ecom.slides.reorder');
    Route::patch ('ecom/slides/{id}',            [$c, 'slideUpdate'])      ->where('id', '[0-9]+')->name('ecom.slides.update');
    Route::delete('ecom/slides/{id}',            [$c, 'slideDestroy'])     ->where('id', '[0-9]+')->name('ecom.slides.destroy');
});

// Public, unauthenticated gateway callbacks (no CSRF on the api group).
Route::post('shop/{slug}/payhere/notify', [StorefrontController::class, 'payhereNotify'])
    ->where('slug', '[a-z0-9-]+')->middleware('throttle:60,1')->name('ecommerce.payhere.notify');
