<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\AccountController;
use Modules\Customers\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use Modules\Customers\Http\Controllers\CustomerAddressController;
use Modules\Customers\Http\Controllers\ProfileController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
Route::prefix('api')->middleware('api')->group(function (): void {
    // Store, logged-in shopper (guard `customer`)
    Route::middleware('auth:customer')->group(function (): void {
        Route::get('account', [AccountController::class, 'show']);
        Route::put('account/profile', [ProfileController::class, 'update']);

        Route::get('account/addresses', [CustomerAddressController::class, 'index']);
        Route::post('account/addresses', [CustomerAddressController::class, 'store']);
        Route::put('account/addresses/{address}', [CustomerAddressController::class, 'update']);
        Route::delete('account/addresses/{address}', [CustomerAddressController::class, 'destroy']);
    });

    // Administration (guard `staff`): open to both roles.
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware('auth:staff')->group(function (): void {
            Route::apiResource('customers', AdminCustomerController::class)->except('destroy');
        });
    });
});
