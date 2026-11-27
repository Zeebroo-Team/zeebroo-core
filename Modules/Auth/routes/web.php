<?php

use Illuminate\Support\Facades\Route;
use Modules\AppConnection\Http\Controllers\Admin\AppReleaseController;
use Modules\Auth\Http\Controllers\AdminAffiliateController;
use Modules\Auth\Http\Controllers\AdminLogController;
use Modules\Auth\Http\Controllers\AffiliateController;
use Modules\Auth\Http\Controllers\AdminAffiliateWithdrawalController;
use Modules\Auth\Http\Controllers\AffiliateSettingsController;
use Modules\Auth\Http\Controllers\AffiliateWithdrawalController;
use Modules\Auth\Models\AffiliateDocument;
use Modules\Auth\Http\Controllers\AdminUserController;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\EmailVerificationController;
use Modules\Auth\Http\Controllers\EmployeeVerifyController;
use Modules\Auth\Http\Controllers\GoogleAuthController;
use Modules\Auth\Http\Controllers\ImpersonationController;
use Modules\Auth\Http\Controllers\PasswordResetController;
use Modules\Business\Http\Controllers\Admin\IndustryController;
use Modules\Package\Http\Controllers\Admin\BusinessPackageController;
use Modules\Package\Http\Controllers\Admin\PackageController;

// Outside `guest`: the desktop app's Google sign-in runs in the system browser,
// which may already be signed in to the website. The web flow's start route
// (/auth/google) stays guest-only, so a signed-in browser never reaches the
// callback through it. Same callback URL, so nothing changes in Google Cloud.
Route::get('/auth/google/desktop', [GoogleAuthController::class, 'desktopRedirect'])->middleware('throttle:20,1')->name('auth.google.desktop');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/register/employee-verify',[EmployeeVerifyController::class, 'showEmailConfirm'])->name('register.employee-verify');
    Route::post('/register/employee-verify', [EmployeeVerifyController::class, 'submitEmail'])->name('register.employee-verify.submit');
    Route::get('/register/employee-verify/otp', [EmployeeVerifyController::class, 'showOtp'])->name('register.employee-verify.otp');
    Route::post('/register/employee-verify/otp', [EmployeeVerifyController::class, 'submitOtp'])->name('register.employee-verify.otp.submit');
    Route::get('/register/employee-verify/password', [EmployeeVerifyController::class, 'showPassword'])->name('register.employee-verify.password');
    Route::post('/register/employee-verify/password', [EmployeeVerifyController::class, 'submitPassword'])->name('register.employee-verify.password.submit');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/signup/cancel', [AuthController::class, 'cancelSignup'])->name('signup.cancel');

    // Outside the admin group: while impersonating, the signed-in user is not an admin.
    Route::post('/impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave');

    // Sign-up email verification (opened from the "Verify your email" banner)
    Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('verification.verify');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:5,1')->name('verification.resend');
});

// Affiliate marketer panel: their referral codes and the accounts that signed up with them.
Route::middleware(['auth', 'role:affiliate'])->prefix('affiliate')->name('affiliate.')->group(function (): void {
    Route::get('/', [AffiliateController::class, 'index'])->name('dashboard');
    Route::get('/payments', [AffiliateController::class, 'payments'])->name('payments');

    // Commission withdrawals
    Route::get('/withdrawals', [AffiliateWithdrawalController::class, 'index'])->name('withdrawals');
    Route::post('/withdrawals', [AffiliateWithdrawalController::class, 'store'])->middleware('throttle:10,1')->name('withdrawals.store');
    Route::patch('/withdrawals/{withdrawal}/cancel', [AffiliateWithdrawalController::class, 'cancel'])->name('withdrawals.cancel');

    // Settings: profile, contact & address, payout bank, ID card / passport copies
    Route::get('/settings', [AffiliateSettingsController::class, 'show'])->name('settings');
    Route::put('/settings/profile', [AffiliateSettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/contact', [AffiliateSettingsController::class, 'updateContact'])->name('settings.contact');
    Route::put('/settings/payout', [AffiliateSettingsController::class, 'updatePayout'])->name('settings.payout');
    Route::post('/settings/identity', [AffiliateSettingsController::class, 'updateIdentity'])->name('settings.identity');
    Route::get('/settings/documents/{type}', [AffiliateSettingsController::class, 'showDocument'])
        ->whereIn('type', AffiliateDocument::types())->name('settings.documents.show');
    Route::delete('/settings/documents/{type}', [AffiliateSettingsController::class, 'destroyDocument'])
        ->whereIn('type', AffiliateDocument::types())->name('settings.documents.destroy');
    Route::post('/codes', [AffiliateController::class, 'storeCode'])->name('codes.store');
    Route::patch('/codes/{affiliateCode}/toggle', [AffiliateController::class, 'toggleCode'])->name('codes.toggle');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    // Affiliate marketers (role "affiliate")
    Route::get('/affiliates', [AdminAffiliateController::class, 'index'])->name('affiliates.index');
    Route::post('/affiliates', [AdminAffiliateController::class, 'store'])->name('affiliates.store');
    Route::get('/affiliates/{user}', [AdminAffiliateController::class, 'show'])->name('affiliates.show');
    Route::get('/affiliates/{user}/payments', [AdminAffiliateController::class, 'payments'])->name('affiliates.payments');
    Route::get('/affiliates/{user}/documents/{type}', [AdminAffiliateController::class, 'document'])
        ->whereIn('type', AffiliateDocument::types())->name('affiliates.documents.show');
    Route::post('/affiliates/{user}/codes', [AdminAffiliateController::class, 'storeCode'])->name('affiliates.codes.store');
    Route::patch('/affiliate-codes/{affiliateCode}/toggle', [AdminAffiliateController::class, 'toggleCode'])->name('affiliates.codes.toggle');

    // Affiliate commission withdrawal requests
    Route::get('/affiliate-withdrawals', [AdminAffiliateWithdrawalController::class, 'index'])->name('affiliate-withdrawals.index');
    Route::patch('/affiliate-withdrawals/{withdrawal}/approve', [AdminAffiliateWithdrawalController::class, 'approve'])->name('affiliate-withdrawals.approve');
    Route::patch('/affiliate-withdrawals/{withdrawal}/reject', [AdminAffiliateWithdrawalController::class, 'reject'])->name('affiliate-withdrawals.reject');
    Route::patch('/affiliate-withdrawals/{withdrawal}/paid', [AdminAffiliateWithdrawalController::class, 'markPaid'])->name('affiliate-withdrawals.paid');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

    // App Releases
    Route::get('/releases', [AppReleaseController::class, 'index'])->name('releases.index');
    Route::post('/releases', [AppReleaseController::class, 'store'])->name('releases.store');
    Route::put('/releases/{release}', [AppReleaseController::class, 'update'])->name('releases.update');
    Route::post('/releases/{release}/set-latest', [AppReleaseController::class, 'setLatest'])->name('releases.set-latest');
    Route::delete('/releases/{release}', [AppReleaseController::class, 'destroy'])->name('releases.destroy');

    // Packages
    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
    Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');

    // Per-business package assignment & feature overrides
    Route::put('/businesses/{business}/package', [BusinessPackageController::class, 'update'])->name('businesses.package.update');

    // Error log preview
    Route::get('/logs', [AdminLogController::class, 'index'])->name('logs.index');

    // Industries (business categories)
    Route::get('/industries', [IndustryController::class, 'index'])->name('industries.index');
    Route::post('/industries', [IndustryController::class, 'store'])->name('industries.store');
    Route::put('/industries/{industry}', [IndustryController::class, 'update'])->name('industries.update');
    Route::delete('/industries/{industry}', [IndustryController::class, 'destroy'])->name('industries.destroy');
});
