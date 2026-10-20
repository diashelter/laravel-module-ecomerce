<?php

declare(strict_types=1);

namespace Modules\Inventory\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Contracts\StockInitializer;
use Modules\Inventory\Contracts\StockLevels;
use Modules\Inventory\Contracts\StockMetrics;
use Modules\Inventory\Contracts\StockReservation;
use Modules\Inventory\Persistence\Queries\StockLevelsQuery;
use Modules\Inventory\Persistence\Queries\StockMetricsQuery;
use Modules\Inventory\Persistence\Repositories\StockRepository;
use Modules\Shared\Support\RecordId;

/**
 * Wires the contracts the inventory publishes to the other modules: a contract that only reads is
 * answered by a query, one that writes or locks by the repository.
 */
class InventoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        StockInitializer::class => StockRepository::class,
        StockLevels::class => StockLevelsQuery::class,
        StockMetrics::class => StockMetricsQuery::class,
        StockReservation::class => StockRepository::class,
    ];

    public function register(): void
    {
        // The route parameters this module owns are canonical UUIDs (see CatalogServiceProvider::register()).
        foreach (['stock'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }
}
