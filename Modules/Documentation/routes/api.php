<?php

use Illuminate\Support\Facades\Route;
use Modules\Documentation\Http\Controllers\Api\DocumentApiController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/documentation',            [DocumentApiController::class, 'index'])->name('documentation.api.documents.index');
    Route::get('/documentation/{document}', [DocumentApiController::class, 'show']) ->name('documentation.api.documents.show');
});
