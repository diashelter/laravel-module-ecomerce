<?php

use Illuminate\Support\Facades\Route;
use Modules\Backoffice\Http\Controllers\Admin\DashboardController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
Route::prefix('api')->middleware('api')->group(function (): void {
    // Administration (guard `staff`): the dashboard is open to both roles.
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware('auth:staff')->group(function (): void {
            Route::get('dashboard', DashboardController::class)->name('dashboard');
        });
    });
});
