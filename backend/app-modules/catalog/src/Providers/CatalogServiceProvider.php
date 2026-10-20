<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Catalog\Persistence\Queries\CatalogMetricsQuery;
use Modules\Catalog\Persistence\Queries\ProductCatalogQuery;
use Modules\Shared\Support\RecordId;

/**
 * Wires the contracts the catalog publishes to the other modules. Both only read, so a query of
 * the catalog answers them.
 */
class CatalogServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CatalogMetrics::class => CatalogMetricsQuery::class,
        ProductCatalog::class => ProductCatalogQuery::class,
    ];

    public function register(): void
    {
        // Route parameters are canonical UUIDs: "/api/products/abc" returns 404 before any query, instead of a database
        // error (PostgreSQL refuses a non-UUID compared with a `uuid` column). The pattern is set here and not in boot():
        // a route copies the global patterns when it is created, and the module library creates the routes of a module
        // before the boot() of the providers. Each module sets the patterns of the parameters it owns.
        foreach (['product', 'category'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }
}
