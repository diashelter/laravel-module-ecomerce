<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Admin\StockController as AdminStockController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
Route::prefix('api')->middleware('api')->group(function (): void {
    // Administration (guard `staff`): open to both roles.
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware('auth:staff')->group(function (): void {
            Route::get('stocks', [AdminStockController::class, 'index'])->name('stocks.index');
            Route::put('stocks/{stock}', [AdminStockController::class, 'update'])->name('stocks.update');
        });
    });
});
