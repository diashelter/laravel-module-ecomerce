<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Shared\Support\RecordId;
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

        // Route parameters are canonical UUIDs: "/api/products/abc" returns 404 before any query,
        // instead of a database error (PostgreSQL refuses a non-UUID compared with a `uuid` column).
        foreach (['product', 'category', 'stock', 'user', 'customer', 'order', 'payableOrder', 'address'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }
}
