<?php

use Illuminate\Support\Facades\Route;
use Modules\DesignStudio\Http\Controllers\DesignEditorController;
use Modules\DesignStudio\Http\Controllers\DesignStudioController;
use Modules\DesignStudio\Http\Controllers\FacebookConnectionController;
use Modules\DesignStudio\Http\Controllers\GenerateCompanyProfileController;
use Modules\DesignStudio\Http\Controllers\GenerateLetterHeadController;
use Modules\DesignStudio\Http\Controllers\ProposalController;

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

    Route::get('/design-studio/letterhead', [DesignStudioController::class, 'letterhead'])
        ->name('designstudio.letterhead.index');

    Route::get('/design-studio/company-profile', [DesignStudioController::class, 'companyProfile'])
        ->name('designstudio.company-profile.index');

    Route::get('/design-studio/type/{type}', [DesignStudioController::class, 'typeIndex'])
        ->name('designstudio.type.index');

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

    // Sales proposal builder
    Route::get('/design-studio/proposals', [ProposalController::class, 'index'])
        ->name('designstudio.proposals.index');
    Route::post('/design-studio/proposals', [ProposalController::class, 'store'])
        ->name('designstudio.proposals.store');
    Route::get('/design-studio/proposals/{group}', [ProposalController::class, 'show'])
        ->name('designstudio.proposals.show');
    Route::post('/design-studio/proposals/{group}/ai-fill', [ProposalController::class, 'aiFill'])
        ->name('designstudio.proposals.ai-fill');
    Route::post('/design-studio/proposals/{group}/pages', [ProposalController::class, 'addPage'])
        ->name('designstudio.proposals.add-page');
    Route::post('/design-studio/proposals/{group}/link-invoice', [ProposalController::class, 'linkInvoice'])
        ->name('designstudio.proposals.link-invoice');
    Route::delete('/design-studio/proposals/{group}', [ProposalController::class, 'destroy'])
        ->name('designstudio.proposals.destroy');

});
