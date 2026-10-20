<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Modules\Catalog\Http\Controllers\Admin\ProductController as AdminProductController;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\ProductController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
Route::prefix('api')->middleware('api')->group(function (): void {
    // Public catalog
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);

    // Administration (guard `staff`). Reading and editing is open to both roles; every DELETE sits in the
    // `admin` group, so a new DELETE route is born restricted.
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware('auth:staff')->group(function (): void {
            Route::patch('products/{product}/status', [AdminProductController::class, 'updateStatus'])->name('products.status');
            Route::apiResource('products', AdminProductController::class)->except('destroy');
            Route::apiResource('categories', AdminCategoryController::class)->except('destroy');

            // Only the `admin` role: removals.
            Route::middleware('admin')->group(function (): void {
                Route::apiResource('products', AdminProductController::class)->only('destroy');
                Route::apiResource('categories', AdminCategoryController::class)->only('destroy');
            });
        });
    });
});
