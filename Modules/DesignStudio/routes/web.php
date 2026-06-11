<?php

use Illuminate\Support\Facades\Route;
use Modules\DesignStudio\Http\Controllers\DesignEditorController;
use Modules\DesignStudio\Http\Controllers\DesignStudioController;
use Modules\DesignStudio\Http\Controllers\FacebookConnectionController;
use Modules\DesignStudio\Http\Controllers\GenerateCompanyProfileController;
use Modules\DesignStudio\Http\Controllers\GenerateLetterHeadController;

Route::middleware(['auth', 'verified'])->group(function (): void {

    Route::get('/design-studio', [DesignStudioController::class, 'index'])
        ->name('designstudio.index');

    Route::get('/design-studio/new', [DesignEditorController::class, 'create'])
        ->name('designstudio.editor.create');

    Route::get('/design-studio/editor/{design}', [DesignEditorController::class, 'edit'])
        ->name('designstudio.editor.edit');

    Route::post('/design-studio/designs', [DesignEditorController::class, 'store'])
        ->name('designstudio.designs.store');

    Route::put('/design-studio/designs/{design}', [DesignEditorController::class, 'update'])
        ->name('designstudio.designs.update');

    Route::delete('/design-studio/designs/{design}', [DesignEditorController::class, 'destroy'])
        ->name('designstudio.designs.destroy');

    Route::get('/design-studio/social-media', [DesignStudioController::class, 'socialMedia'])
        ->name('designstudio.social-media.index');

    // Facebook Page OAuth
    Route::get('/design-studio/facebook/connect',     [FacebookConnectionController::class, 'redirect'])
        ->name('designstudio.facebook.redirect');
    Route::get('/design-studio/facebook/callback',    [FacebookConnectionController::class, 'callback'])
        ->name('designstudio.facebook.callback');
    Route::post('/design-studio/facebook/connect-page', [FacebookConnectionController::class, 'connectPage'])
        ->name('designstudio.facebook.connect-page');
    Route::delete('/design-studio/facebook/disconnect/{connection}', [FacebookConnectionController::class, 'disconnect'])
        ->name('designstudio.facebook.disconnect');

    Route::get('/design-studio/letterhead-links', [DesignStudioController::class, 'letterheadLinks'])
        ->name('designstudio.letterhead.links');

    Route::post('/design-studio/letterhead-links/toggle', [DesignStudioController::class, 'toggleLetterheadLink'])
        ->name('designstudio.letterhead.links.toggle');

    Route::post('/design-studio/generate/company-profile', GenerateCompanyProfileController::class)
        ->name('designstudio.generate.company-profile');

    Route::post('/design-studio/generate/letterhead', GenerateLetterHeadController::class)
        ->name('designstudio.generate.letterhead');

});
