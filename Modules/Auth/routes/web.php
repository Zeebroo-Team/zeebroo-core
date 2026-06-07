<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\EmployeeVerifyController;
use Modules\Auth\Http\Controllers\GoogleAuthController;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::get('/register/employee-verify', [EmployeeVerifyController::class, 'showEmailConfirm'])->name('register.employee-verify');
    Route::post('/register/employee-verify', [EmployeeVerifyController::class, 'submitEmail'])->name('register.employee-verify.submit');
    Route::get('/register/employee-verify/otp', [EmployeeVerifyController::class, 'showOtp'])->name('register.employee-verify.otp');
    Route::post('/register/employee-verify/otp', [EmployeeVerifyController::class, 'submitOtp'])->name('register.employee-verify.otp.submit');
    Route::get('/register/employee-verify/password', [EmployeeVerifyController::class, 'showPassword'])->name('register.employee-verify.password');
    Route::post('/register/employee-verify/password', [EmployeeVerifyController::class, 'submitPassword'])->name('register.employee-verify.password.submit');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
