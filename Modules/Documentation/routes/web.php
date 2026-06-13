<?php

use Illuminate\Support\Facades\Route;
use Modules\Documentation\Http\Controllers\DocumentController;

Route::get('/privacy-policy', fn () => view('documentation::privacy-policy'))->name('privacy-policy');

Route::middleware('web')->group(function () {
    Route::get('/documentation',                                    [DocumentController::class, 'index'])   ->name('documentation.documents.index');
    Route::get('/documentation/category/{category:slug}',          [DocumentController::class, 'category'])->name('documentation.documents.category');
    Route::get('/documentation/{document}',                        [DocumentController::class, 'show'])    ->name('documentation.documents.show');
});
