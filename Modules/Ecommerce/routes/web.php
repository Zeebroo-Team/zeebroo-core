<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\StorefrontController;

// Public online store managed from the desktop E-Commerce tab.
Route::get ('shop/{slug}',          [StorefrontController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')->name('ecommerce.storefront');
Route::post('shop/{slug}/checkout', [StorefrontController::class, 'checkout'])
    ->where('slug', '[a-z0-9-]+')->middleware('throttle:10,1')->name('ecommerce.storefront.checkout');
// Order confirmation / payment return page (Stripe & PayHere return here). The token is a private 48-char secret.
Route::get ('shop/{slug}/order/{token}', [StorefrontController::class, 'order'])
    ->where(['slug' => '[a-z0-9-]+', 'token' => '[A-Za-z0-9]{48}'])->name('ecommerce.storefront.order');
