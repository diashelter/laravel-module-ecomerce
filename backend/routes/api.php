<?php

use App\Modules\Backoffice\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Modules\Catalog\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Customers\Http\Controllers\AccountController;
use App\Modules\Customers\Http\Controllers\Admin\UserController as AdminUserController;
use App\Modules\Customers\Http\Controllers\ProfileController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Inventory\Http\Controllers\Admin\StockController as AdminStockController;
use App\Modules\Ordering\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Modules\Ordering\Http\Controllers\CartController;
use App\Modules\Ordering\Http\Controllers\OrderController;
use App\Modules\Payment\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
| All routes below are prefixed with /api (see bootstrap/app.php).
| Authentication uses Sanctum SPA mode (session cookie), see /sanctum/csrf-cookie.
*/

// Authentication
Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Public catalog
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']);
Route::post('cart/validate', [CartController::class, 'validate'])->middleware('throttle:60,1');

// Authenticated users
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/payment', [PaymentController::class, 'store']);

    Route::get('account', [AccountController::class, 'show']);
    Route::put('account/profile', [ProfileController::class, 'update']);

    // Administration (auth + admin role)
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        Route::patch('products/{product}/status', [AdminProductController::class, 'updateStatus'])->name('products.status');
        Route::apiResource('products', AdminProductController::class);
        Route::apiResource('categories', AdminCategoryController::class);

        Route::get('stocks', [AdminStockController::class, 'index'])->name('stocks.index');
        Route::put('stocks/{stock}', [AdminStockController::class, 'update'])->name('stocks.update');

        Route::apiResource('users', AdminUserController::class)->except('destroy');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    });
});
