<?php

use App\Modules\Backoffice\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Modules\Catalog\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Customers\Http\Controllers\AccountController;
use App\Modules\Customers\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Modules\Customers\Http\Controllers\CustomerAddressController;
use App\Modules\Customers\Http\Controllers\ProfileController;
use App\Modules\Fulfillment\Http\Controllers\ShippingQuoteController;
use App\Modules\Identity\Http\Controllers\Admin\StaffAuthController;
use App\Modules\Identity\Http\Controllers\Admin\StaffMemberController as AdminStaffMemberController;
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

// Store authentication (guard `customer`)
Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:customer')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Public catalog
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']);
Route::post('cart/validate', [CartController::class, 'validate'])->middleware('throttle:60,1');
Route::get('shipping/quote', [ShippingQuoteController::class, 'show'])->middleware('throttle:60,1');

// Store, logged-in shopper (guard `customer`)
Route::middleware('auth:customer')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{payableOrder}/payment', [PaymentController::class, 'store'])->middleware('throttle:20,1');

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
    Route::post('auth/login', [StaffAuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');

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
