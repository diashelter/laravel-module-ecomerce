<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\CatalogMetrics;
use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\Queries\CatalogMetricsQuery;
use App\Modules\Catalog\Queries\ProductCatalogQuery;
use Illuminate\Support\ServiceProvider;

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
