<?php

use Illuminate\Support\Facades\Route;
use Modules\Restaurant\Http\Controllers\MenuCategoryController;
use Modules\Restaurant\Http\Controllers\MenuItemController;
use Modules\Restaurant\Http\Controllers\OrderController;
use Modules\Restaurant\Http\Controllers\ReservationController;
use Modules\Restaurant\Http\Controllers\TableController;

Route::middleware(['web', 'auth'])->group(function (): void {
    // Menu categories
    Route::get('/restaurant/menu/categories',                          [MenuCategoryController::class, 'index'])   ->name('restaurant.menu.categories.index');
    Route::post('/restaurant/menu/categories',                         [MenuCategoryController::class, 'store'])   ->name('restaurant.menu.categories.store');
    Route::post('/restaurant/menu/categories/reorder',                 [MenuCategoryController::class, 'reorder']) ->name('restaurant.menu.categories.reorder');
    Route::put('/restaurant/menu/categories/{menuCategory}',           [MenuCategoryController::class, 'update'])  ->name('restaurant.menu.categories.update');
    Route::delete('/restaurant/menu/categories/{menuCategory}',        [MenuCategoryController::class, 'destroy']) ->name('restaurant.menu.categories.destroy');

    // Menu items
    Route::get('/restaurant/menu',                                     [MenuItemController::class, 'index'])   ->name('restaurant.menu.items.index');
    Route::post('/restaurant/menu',                                    [MenuItemController::class, 'store'])   ->name('restaurant.menu.items.store');
    Route::get('/restaurant/menu/{menuItem}',                          [MenuItemController::class, 'show'])    ->name('restaurant.menu.items.show');
    Route::get('/restaurant/menu/{menuItem}/edit',                     [MenuItemController::class, 'edit'])    ->name('restaurant.menu.items.edit');
    Route::put('/restaurant/menu/{menuItem}',                          [MenuItemController::class, 'update'])  ->name('restaurant.menu.items.update');
    Route::delete('/restaurant/menu/{menuItem}',                       [MenuItemController::class, 'destroy']) ->name('restaurant.menu.items.destroy');

    // Tables
    Route::get('/restaurant/tables',                                   [TableController::class, 'index'])         ->name('restaurant.tables.index');
    Route::get('/restaurant/tables/statuses',                          [TableController::class, 'statuses'])      ->name('restaurant.tables.statuses');
    Route::post('/restaurant/tables',                                  [TableController::class, 'store'])         ->name('restaurant.tables.store');
    Route::post('/restaurant/tables/positions',                        [TableController::class, 'savePositions']) ->name('restaurant.tables.positions');
    Route::put('/restaurant/tables/{restaurantTable}',                 [TableController::class, 'update'])        ->name('restaurant.tables.update');
    Route::delete('/restaurant/tables/{restaurantTable}',              [TableController::class, 'destroy'])       ->name('restaurant.tables.destroy');

    // Orders
    Route::get('/restaurant/orders',                                            [OrderController::class, 'index'])            ->name('restaurant.orders.index');
    Route::get('/restaurant/kitchen',                                           [OrderController::class, 'kitchen'])          ->name('restaurant.kitchen');
    Route::get('/restaurant/orders/create',                                     [OrderController::class, 'create'])           ->name('restaurant.orders.create');
    Route::get('/restaurant/orders/item-statuses',                              [OrderController::class, 'itemStatuses'])     ->name('restaurant.orders.item-statuses');
    Route::post('/restaurant/orders',                                           [OrderController::class, 'store'])            ->name('restaurant.orders.store');
    Route::get('/restaurant/orders/{order}',                                    [OrderController::class, 'show'])             ->name('restaurant.orders.show');
    Route::post('/restaurant/orders/{order}/transition',                        [OrderController::class, 'transition'])       ->name('restaurant.orders.transition');
    Route::patch('/restaurant/orders/{order}/items/{item}/status',              [OrderController::class, 'updateItemStatus']) ->name('restaurant.orders.items.status');
    Route::delete('/restaurant/orders/{order}/items/{item}',                    [OrderController::class, 'deleteItem'])        ->name('restaurant.orders.items.destroy');
    Route::post('/restaurant/orders/{order}/complete',                          [OrderController::class, 'completeOrder'])    ->name('restaurant.orders.complete');
    Route::post('/restaurant/orders/{order}/clear',                             [OrderController::class, 'clearOrder'])       ->name('restaurant.orders.clear');
    Route::delete('/restaurant/orders/{order}',                                 [OrderController::class, 'destroy'])          ->name('restaurant.orders.destroy');

    // Reservations
    Route::get('/restaurant/reservations',                                      [ReservationController::class, 'index'])       ->name('restaurant.reservations.index');
    Route::post('/restaurant/reservations',                                     [ReservationController::class, 'store'])       ->name('restaurant.reservations.store');
    Route::get('/restaurant/reservations/{reservation}/edit',                   [ReservationController::class, 'edit'])        ->name('restaurant.reservations.edit');
    Route::put('/restaurant/reservations/{reservation}',                        [ReservationController::class, 'update'])      ->name('restaurant.reservations.update');
    Route::post('/restaurant/reservations/{reservation}/quick-status',          [ReservationController::class, 'quickStatus']) ->name('restaurant.reservations.quickStatus');
    Route::delete('/restaurant/reservations/{reservation}',                     [ReservationController::class, 'destroy'])     ->name('restaurant.reservations.destroy');
});
