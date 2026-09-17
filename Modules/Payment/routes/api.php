<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;

// Stripe webhook — unauthenticated, verified via the Stripe-Signature header instead.
Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
