<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\GoogleAuthController;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
