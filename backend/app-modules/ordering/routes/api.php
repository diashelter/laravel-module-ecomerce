<?php

use Illuminate\Support\Facades\Route;
use Modules\Ordering\Http\Controllers\Admin\OrderController as AdminOrderController;
use Modules\Ordering\Http\Controllers\CartController;
use Modules\Ordering\Http\Controllers\OrderController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
Route::prefix('api')->middleware('api')->group(function (): void {
    Route::post('cart/validate', [CartController::class, 'validate'])->middleware('throttle:60,1,cart-validation');

    // Store, logged-in shopper (guard `customer`)
    Route::middleware('auth:customer')->group(function (): void {
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1,checkout');
        Route::get('orders/{order}', [OrderController::class, 'show']);
    });

    // Administration (guard `staff`): open to both roles.
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware('auth:staff')->group(function (): void {
            Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        });
    });
});
