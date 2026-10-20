<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Support\RecordId;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Route parameters are canonical UUIDs: "/api/products/abc" returns 404 before any query,
        // instead of a database error (PostgreSQL refuses a non-UUID compared with a `uuid` column).
        // They are set here and not in boot(): a route copies the global patterns when it is created, and the
        // routes of a module are created while the module library boots, before the boot() of this provider.
        foreach (['product', 'category', 'stock', 'user', 'customer', 'order', 'address'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }

    public function boot(): void
    {
        // Fails fast in development when a relationship is lazy loaded (N+1 queries).
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
