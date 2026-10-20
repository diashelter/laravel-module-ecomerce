<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\ProductController;

// Deliberate violation: the routes file of another module names a Catalog controller. Never loaded.
Route::prefix('api')->middleware('api')->group(function (): void {
    Route::get('products', [ProductController::class, 'index']);
});
