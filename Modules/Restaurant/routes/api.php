<?php

use Illuminate\Support\Facades\Route;
use Modules\Restaurant\Http\Controllers\Api\IngredientApiController;

Route::middleware(['api', 'auth:sanctum'])->prefix('restaurant')->group(function (): void {

    // Ingredients
    Route::get('/ingredients',                         [IngredientApiController::class, 'index'])   ->name('api.restaurant.ingredients.index');
    Route::post('/ingredients',                        [IngredientApiController::class, 'store'])   ->name('api.restaurant.ingredients.store');
    Route::put('/ingredients/{ingredient}',            [IngredientApiController::class, 'update'])  ->name('api.restaurant.ingredients.update');
    Route::delete('/ingredients/{ingredient}',         [IngredientApiController::class, 'destroy']) ->name('api.restaurant.ingredients.destroy');
    Route::post('/ingredients/{ingredient}/stock-in',  [IngredientApiController::class, 'stockIn']) ->name('api.restaurant.ingredients.stock-in');
    Route::get('/ingredients/{ingredient}/transactions',[IngredientApiController::class, 'transactions'])->name('api.restaurant.ingredients.transactions');

});
