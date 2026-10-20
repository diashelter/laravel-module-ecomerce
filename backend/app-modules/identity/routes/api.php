<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\Admin\StaffAuthController;
use Modules\Identity\Http\Controllers\Admin\StaffMemberController as AdminStaffMemberController;
use Modules\Identity\Http\Controllers\AuthController;

// The module library only requires this file: the /api prefix and the api middleware group are declared here.
// Every `throttle:N,M` names its counter in the third parameter: without it the key is only the client (user id
// or IP), and all throttled routes would add to one counter (RateLimitTest).
Route::prefix('api')->middleware('api')->group(function (): void {
    // Store authentication (guard `customer`)
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1,registration');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,customer-login');

        Route::middleware('auth:customer')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Administration (guard `staff`).
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::post('auth/login', [StaffAuthController::class, 'login'])->middleware('throttle:10,1,staff-login')->name('auth.login');

        Route::middleware('auth:staff')->group(function (): void {
            Route::post('auth/logout', [StaffAuthController::class, 'logout'])->name('auth.logout');
            Route::get('auth/me', [StaffAuthController::class, 'me'])->name('auth.me');

            // Only the `admin` role: staff management.
            Route::middleware('admin')->group(function (): void {
                Route::apiResource('users', AdminStaffMemberController::class);
            });
        });
    });
});
