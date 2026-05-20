<?php

use Illuminate\Support\Facades\Route;
use Modules\FileManager\Http\Controllers\FileManagerController;
use Modules\FileManager\Http\Controllers\FileManagerFileController;
use Modules\FileManager\Http\Controllers\FileManagerFolderController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/files', [FileManagerController::class, 'index'])->name('filemanager.index');
    Route::post('/files/upload', [FileManagerFileController::class, 'store'])->name('filemanager.files.store');
    Route::get('/files/{file}/download', [FileManagerFileController::class, 'download'])->name('filemanager.files.download');
    Route::delete('/files/{file}', [FileManagerFileController::class, 'destroy'])->name('filemanager.files.destroy');
    Route::post('/files/folders', [FileManagerFolderController::class, 'store'])->name('filemanager.folders.store');
    Route::delete('/files/folders/{folder}', [FileManagerFolderController::class, 'destroy'])->name('filemanager.folders.destroy');
});
