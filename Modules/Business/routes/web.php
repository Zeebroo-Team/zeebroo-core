<?php

use Illuminate\Support\Facades\Route;
use Modules\Business\Http\Controllers\BranchController;
use Modules\Business\Http\Controllers\BusinessController;
use Modules\Business\Http\Controllers\BusinessGoogleBusinessProfileController;
use Modules\Business\Http\Controllers\BusinessLogoGenerationController;
use Modules\Business\Http\Controllers\BusinessUserController;

Route::middleware(['auth'])->group(function (): void {
    Route::get('/business/map', [BusinessController::class, 'map'])->name('business.map');
    Route::get('/business/profile', [BusinessController::class, 'profile'])->name('business.profile');
    Route::post('/business/profile/brand', [BusinessController::class, 'updateBrand'])->name('business.profile.brand.update');
    Route::get('/business/profile/google-business/locations', [BusinessGoogleBusinessProfileController::class, 'locations'])
        ->middleware('throttle:30,1')
        ->name('business.profile.google.locations');
    Route::post('/business/profile/google-business/link', [BusinessGoogleBusinessProfileController::class, 'link'])
        ->middleware('throttle:20,1')
        ->name('business.profile.google.link');
    Route::post('/business/profile/google-business/unlink', [BusinessGoogleBusinessProfileController::class, 'unlink'])
        ->middleware('throttle:30,1')
        ->name('business.profile.google.unlink');
    Route::post('/business/profile/google-business/import', [BusinessGoogleBusinessProfileController::class, 'importDescription'])
        ->middleware('throttle:15,1')
        ->name('business.profile.google.import');
    Route::post('/business/profile/brand/copy', [BusinessController::class, 'generateBrandCopy'])
        ->middleware('throttle:25,1')
        ->name('business.profile.brand.copy.generate');
    Route::post('/business/profile/logo', [BusinessController::class, 'updateLogo'])->name('business.profile.logo.store');
    Route::post('/business/profile/logo/creator', [BusinessController::class, 'storeCreatorLogo'])->name('business.profile.logo.creator');
    Route::delete('/business/profile/logo', [BusinessController::class, 'destroyLogo'])->name('business.profile.logo.destroy');

    Route::post('/business/profile/logo/generate', [BusinessLogoGenerationController::class, 'dispatch'])->name('business.profile.logo.generate');
    Route::get('/business/profile/logo/generation/{uuid}', [BusinessLogoGenerationController::class, 'status'])->name('business.profile.logo.generation.status');
    Route::post('/business/profile/logo/generation/apply', [BusinessLogoGenerationController::class, 'apply'])->name('business.profile.logo.generation.apply');

    Route::post('/business/onboarding', [BusinessController::class, 'storeOnboarding'])->name('business.onboarding.store');
    Route::post('/business/features', [BusinessController::class, 'updateFeatures'])->name('business.features.update');

    // User management
    Route::get('/business/users', [BusinessUserController::class, 'index'])->name('business.users.index');
    Route::post('/business/users', [BusinessUserController::class, 'store'])->name('business.users.store');
    Route::put('/business/users/{member}', [BusinessUserController::class, 'update'])->name('business.users.update');
    Route::delete('/business/users/{member}', [BusinessUserController::class, 'destroy'])->name('business.users.destroy');
    Route::post('/business/warehouse-intro', [BusinessController::class, 'acknowledgeWarehouseIntro'])
        ->name('business.warehouse-intro.store');

    Route::get('/business/setup-location', [BranchController::class, 'singleLocationSetup'])
        ->name('business.single-branch.setup');

    Route::get('/branches', [BranchController::class, 'index'])->name('business.branches.index');
    Route::post('/branches', [BranchController::class, 'store'])->name('business.branches.store');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('business.branches.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('business.branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('business.branches.destroy');
});
