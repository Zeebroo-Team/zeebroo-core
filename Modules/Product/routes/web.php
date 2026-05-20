<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductBrandController;
use Modules\Product\Http\Controllers\ProductCategoryController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\ProductImageController;
use Modules\Product\Http\Controllers\ProductUnitController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/products/categories', [ProductCategoryController::class, 'index'])->name('product.categories.index');
    Route::post('/products/categories', [ProductCategoryController::class, 'store'])->name('product.categories.store');
    Route::post('/products/categories/reorder', [ProductCategoryController::class, 'reorder'])->name('product.categories.reorder');
    Route::get('/products/categories/{category}/edit', [ProductCategoryController::class, 'edit'])->name('product.categories.edit');
    Route::put('/products/categories/{category}', [ProductCategoryController::class, 'update'])->name('product.categories.update');
    Route::delete('/products/categories/{category}', [ProductCategoryController::class, 'destroy'])->name('product.categories.destroy');

    Route::get('/products/brands', [ProductBrandController::class, 'index'])->name('product.brands.index');
    Route::post('/products/brands', [ProductBrandController::class, 'store'])->name('product.brands.store');
    Route::get('/products/brands/{brand}/edit', [ProductBrandController::class, 'edit'])->name('product.brands.edit');
    Route::put('/products/brands/{brand}', [ProductBrandController::class, 'update'])->name('product.brands.update');
    Route::delete('/products/brands/{brand}', [ProductBrandController::class, 'destroy'])->name('product.brands.destroy');

    Route::get('/products/units', [ProductUnitController::class, 'index'])->name('product.units.index');
    Route::post('/products/units', [ProductUnitController::class, 'store'])->name('product.units.store');
    Route::get('/products/units/{unit}/edit', [ProductUnitController::class, 'edit'])->name('product.units.edit');
    Route::put('/products/units/{unit}', [ProductUnitController::class, 'update'])->name('product.units.update');
    Route::delete('/products/units/{unit}', [ProductUnitController::class, 'destroy'])->name('product.units.destroy');

    Route::get('/products', [ProductController::class, 'index'])->name('product.index');
    Route::post('/products/sku/generate', [ProductController::class, 'generateSku'])->name('product.sku.generate');
    Route::get('/products/images/picker', [ProductImageController::class, 'picker'])->name('product.images.picker');
    Route::post('/products/images/upload', [ProductImageController::class, 'upload'])->name('product.images.upload');
    Route::post('/products/images/generate', [ProductImageController::class, 'generate'])->name('product.images.generate');
    Route::post('/products', [ProductController::class, 'store'])->name('product.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('product.show');
    Route::put('/products/{product}/stock-layers/{stockLayer}', [ProductController::class, 'updateStockLayer'])->name('product.stock-layers.update');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('product.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('product.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('product.destroy');
});
