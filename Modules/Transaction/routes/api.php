<?php

use Illuminate\Support\Facades\Route;

/*
| REST API for transactions can be added here (e.g. Route::middleware(['auth:sanctum'])->prefix('v1')...).
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    //
});
