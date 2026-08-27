<?php

use Illuminate\Support\Facades\Route;
use Modules\AppConnection\Http\Controllers\Admin\AppReleaseController;
use Modules\Auth\Http\Controllers\AdminUserController;
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

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    // App Releases
    Route::get('/releases', [AppReleaseController::class, 'index'])->name('releases.index');
    Route::post('/releases', [AppReleaseController::class, 'store'])->name('releases.store');
    Route::post('/releases/{release}/set-latest', [AppReleaseController::class, 'setLatest'])->name('releases.set-latest');
    Route::delete('/releases/{release}', [AppReleaseController::class, 'destroy'])->name('releases.destroy');
});
