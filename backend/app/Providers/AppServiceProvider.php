<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fails fast in development when a relationship is lazy loaded (N+1 queries).
        Model::preventLazyLoading(! $this->app->isProduction());

        // Route parameters are numeric ids: "/api/products/abc" returns 404 instead of a database error.
        Route::pattern('product', '[0-9]+');
        Route::pattern('category', '[0-9]+');
        Route::pattern('stock', '[0-9]+');
        Route::pattern('user', '[0-9]+');
        Route::pattern('customer', '[0-9]+');
        Route::pattern('order', '[0-9]+');
        Route::pattern('address', '[0-9]+');
    }
}
