<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
Route::prefix('api')->middleware('api')->group(function (): void {
    // Store, logged-in shopper (guard `customer`)
    Route::middleware('auth:customer')->group(function (): void {
        Route::post('orders/{order}/payment', [PaymentController::class, 'store'])->middleware('throttle:20,1,payment');
    });
});
