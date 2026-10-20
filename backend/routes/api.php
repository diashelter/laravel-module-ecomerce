<?php

use Illuminate\Support\Facades\Route;
use Modules\Backoffice\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use Modules\Catalog\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Modules\Catalog\Http\Controllers\Admin\ProductController as AdminProductController;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\ProductController;
use Modules\Customers\Http\Controllers\AccountController;
use Modules\Customers\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use Modules\Customers\Http\Controllers\CustomerAddressController;
use Modules\Customers\Http\Controllers\ProfileController;
use Modules\Fulfillment\Http\Controllers\ShippingQuoteController;
use Modules\Identity\Http\Controllers\Admin\StaffAuthController;
use Modules\Identity\Http\Controllers\Admin\StaffMemberController as AdminStaffMemberController;
use Modules\Identity\Http\Controllers\AuthController;
use Modules\Inventory\Http\Controllers\Admin\StockController as AdminStockController;
use Modules\Ordering\Http\Controllers\Admin\OrderController as AdminOrderController;
use Modules\Ordering\Http\Controllers\CartController;
use Modules\Ordering\Http\Controllers\OrderController;
use Modules\Payment\Http\Controllers\PaymentController;

/*
| All routes below are prefixed with /api (see bootstrap/app.php).
| Authentication uses Sanctum SPA mode (session cookie), see /sanctum/csrf-cookie.
|
| Every `throttle:N,M` names its counter in the third parameter: without it the key is only the
| client (user id or IP), and all throttled routes would add to one counter (RateLimitTest).
*/

// Store authentication (guard `customer`)
Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1,registration');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,customer-login');

    Route::middleware('auth:customer')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Public catalog
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']);
Route::post('cart/validate', [CartController::class, 'validate'])->middleware('throttle:60,1,cart-validation');
Route::get('shipping/quote', [ShippingQuoteController::class, 'show'])->middleware('throttle:60,1,shipping-quote');

// Store, logged-in shopper (guard `customer`)
Route::middleware('auth:customer')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1,checkout');
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/payment', [PaymentController::class, 'store'])->middleware('throttle:20,1,payment');

    Route::get('account', [AccountController::class, 'show']);
    Route::put('account/profile', [ProfileController::class, 'update']);

    Route::get('account/addresses', [CustomerAddressController::class, 'index']);
    Route::post('account/addresses', [CustomerAddressController::class, 'store']);
    Route::put('account/addresses/{address}', [CustomerAddressController::class, 'update']);
    Route::delete('account/addresses/{address}', [CustomerAddressController::class, 'destroy']);
});

// Administration (guard `staff`). Reading and editing is open to both roles; every DELETE and
// the staff management sit in the `admin` group below, so a new DELETE route is born restricted.
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::post('auth/login', [StaffAuthController::class, 'login'])->middleware('throttle:10,1,staff-login')->name('auth.login');

    Route::middleware('auth:staff')->group(function (): void {
        Route::post('auth/logout', [StaffAuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [StaffAuthController::class, 'me'])->name('auth.me');

        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        Route::patch('products/{product}/status', [AdminProductController::class, 'updateStatus'])->name('products.status');
        Route::apiResource('products', AdminProductController::class)->except('destroy');
        Route::apiResource('categories', AdminCategoryController::class)->except('destroy');

        Route::get('stocks', [AdminStockController::class, 'index'])->name('stocks.index');
        Route::put('stocks/{stock}', [AdminStockController::class, 'update'])->name('stocks.update');

        Route::apiResource('customers', AdminCustomerController::class)->except('destroy');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

        // Only the `admin` role: removals and staff management.
        Route::middleware('admin')->group(function (): void {
            Route::apiResource('products', AdminProductController::class)->only('destroy');
            Route::apiResource('categories', AdminCategoryController::class)->only('destroy');
            Route::apiResource('users', AdminStaffMemberController::class);
        });
    });
});
