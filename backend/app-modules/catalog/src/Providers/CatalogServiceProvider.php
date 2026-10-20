<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Catalog\Persistence\Queries\CatalogMetricsQuery;
use Modules\Catalog\Persistence\Queries\ProductCatalogQuery;

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
}
