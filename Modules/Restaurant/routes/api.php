<?php

use Illuminate\Support\Facades\Route;
use Modules\Restaurant\Http\Controllers\Api\IngredientApiController;
use Modules\Restaurant\Http\Controllers\Api\IngredientPurchaseApiController;
use Modules\Restaurant\Http\Controllers\Api\MenuCategoryApiController;
use Modules\Restaurant\Http\Controllers\Api\MenuItemApiController;
use Modules\Restaurant\Http\Controllers\Api\OrderApiController;
use Modules\Restaurant\Http\Controllers\Api\TableApiController;

Route::middleware(['api', 'auth:sanctum'])->prefix('restaurant')->group(function (): void {

    // Orders — literal routes BEFORE wildcard
    Route::get('/orders/bootstrap',                              [OrderApiController::class, 'bootstrap'])      ->name('api.restaurant.orders.bootstrap');
    Route::get('/orders/item-statuses',                          [OrderApiController::class, 'itemStatuses'])   ->name('api.restaurant.orders.item-statuses');
    Route::get('/orders',                                        [OrderApiController::class, 'index'])          ->name('api.restaurant.orders.index');
    Route::post('/orders',                                       [OrderApiController::class, 'store'])          ->name('api.restaurant.orders.store');
    Route::get('/orders/{order}',                                [OrderApiController::class, 'show'])           ->name('api.restaurant.orders.show');
    Route::patch('/orders/{order}/status',                       [OrderApiController::class, 'transition'])     ->name('api.restaurant.orders.transition');
    Route::post('/orders/{order}/items',                         [OrderApiController::class, 'addItems'])       ->name('api.restaurant.orders.add-items');
    Route::patch('/orders/{order}/items/{item}/status',          [OrderApiController::class, 'updateItemStatus'])->name('api.restaurant.orders.item-status');
    Route::delete('/orders/{order}/items/{item}',                [OrderApiController::class, 'deleteItem'])     ->name('api.restaurant.orders.delete-item');
    Route::post('/orders/{order}/complete',                      [OrderApiController::class, 'completeOrder'])  ->name('api.restaurant.orders.complete');
    Route::delete('/orders/{order}',                             [OrderApiController::class, 'clearOrder'])     ->name('api.restaurant.orders.clear');

    // Menu Items (toggle before {menuItem} wildcard)
    Route::get('/menu-items',                              [MenuItemApiController::class, 'index'])             ->name('api.restaurant.menu-items.index');
    Route::post('/menu-items',                             [MenuItemApiController::class, 'store'])             ->name('api.restaurant.menu-items.store');
    Route::get('/menu-items/{menuItem}',                   [MenuItemApiController::class, 'show'])              ->name('api.restaurant.menu-items.show');
    Route::put('/menu-items/{menuItem}',                   [MenuItemApiController::class, 'update'])            ->name('api.restaurant.menu-items.update');
    Route::delete('/menu-items/{menuItem}',                [MenuItemApiController::class, 'destroy'])           ->name('api.restaurant.menu-items.destroy');
    Route::patch('/menu-items/{menuItem}/toggle',          [MenuItemApiController::class, 'toggleAvailability'])->name('api.restaurant.menu-items.toggle');
    Route::get('/menu-items/{menuItem}/ingredients',       [MenuItemApiController::class, 'getIngredients'])    ->name('api.restaurant.menu-items.ingredients.index');
    Route::put('/menu-items/{menuItem}/ingredients',       [MenuItemApiController::class, 'syncIngredients'])   ->name('api.restaurant.menu-items.ingredients.sync');

    // Menu Categories (reorder before {menuCategory} wildcard)
    Route::post('/menu-categories/reorder',          [MenuCategoryApiController::class, 'reorder']) ->name('api.restaurant.menu-cats.reorder');
    Route::get('/menu-categories',                   [MenuCategoryApiController::class, 'index'])   ->name('api.restaurant.menu-cats.index');
    Route::post('/menu-categories',                  [MenuCategoryApiController::class, 'store'])   ->name('api.restaurant.menu-cats.store');
    Route::put('/menu-categories/{menuCategory}',    [MenuCategoryApiController::class, 'update'])  ->name('api.restaurant.menu-cats.update');
    Route::delete('/menu-categories/{menuCategory}', [MenuCategoryApiController::class, 'destroy']) ->name('api.restaurant.menu-cats.destroy');

    // Tables (positions before {table} wildcard)
    Route::post('/tables/positions',        [TableApiController::class, 'savePositions'])->name('api.restaurant.tables.positions');
    Route::get('/tables',                   [TableApiController::class, 'index'])        ->name('api.restaurant.tables.index');
    Route::post('/tables',                  [TableApiController::class, 'store'])        ->name('api.restaurant.tables.store');
    Route::put('/tables/{restaurantTable}', [TableApiController::class, 'update'])       ->name('api.restaurant.tables.update');
    Route::delete('/tables/{restaurantTable}', [TableApiController::class, 'destroy'])   ->name('api.restaurant.tables.destroy');

    // Ingredient Purchase Orders — literals before {po} wildcard
    Route::get('/purchase-orders',                                [IngredientPurchaseApiController::class, 'index'])      ->name('api.restaurant.purchase-orders.index');
    Route::post('/purchase-orders',                               [IngredientPurchaseApiController::class, 'store'])      ->name('api.restaurant.purchase-orders.store');
    Route::get('/purchase-orders/{ingredientPurchaseOrder}',      [IngredientPurchaseApiController::class, 'show'])       ->name('api.restaurant.purchase-orders.show');
    Route::put('/purchase-orders/{ingredientPurchaseOrder}',      [IngredientPurchaseApiController::class, 'update'])     ->name('api.restaurant.purchase-orders.update');
    Route::post('/purchase-orders/{ingredientPurchaseOrder}/place-order', [IngredientPurchaseApiController::class, 'placeOrder']) ->name('api.restaurant.purchase-orders.place-order');
    Route::post('/purchase-orders/{ingredientPurchaseOrder}/cancel',      [IngredientPurchaseApiController::class, 'cancel'])     ->name('api.restaurant.purchase-orders.cancel');
    Route::delete('/purchase-orders/{ingredientPurchaseOrder}',   [IngredientPurchaseApiController::class, 'destroy'])    ->name('api.restaurant.purchase-orders.destroy');
    Route::post('/purchase-orders/{ingredientPurchaseOrder}/grn', [IngredientPurchaseApiController::class, 'createGrn'])  ->name('api.restaurant.purchase-orders.grn');

    // Ingredients
    Route::get('/ingredients',                          [IngredientApiController::class, 'index'])        ->name('api.restaurant.ingredients.index');
    Route::post('/ingredients',                         [IngredientApiController::class, 'store'])        ->name('api.restaurant.ingredients.store');
    Route::put('/ingredients/{ingredient}',             [IngredientApiController::class, 'update'])       ->name('api.restaurant.ingredients.update');
    Route::delete('/ingredients/{ingredient}',          [IngredientApiController::class, 'destroy'])      ->name('api.restaurant.ingredients.destroy');
    Route::post('/ingredients/{ingredient}/stock-in',   [IngredientApiController::class, 'stockIn'])      ->name('api.restaurant.ingredients.stock-in');
    Route::get('/ingredients/{ingredient}/transactions', [IngredientApiController::class, 'transactions'])->name('api.restaurant.ingredients.transactions');

});
