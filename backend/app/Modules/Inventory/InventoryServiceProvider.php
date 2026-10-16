<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Contracts\StockInitializer;
use App\Modules\Inventory\Contracts\StockLevels;
use App\Modules\Inventory\Contracts\StockMetrics;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Inventory\Queries\StockLevelsQuery;
use App\Modules\Inventory\Queries\StockMetricsQuery;
use App\Modules\Inventory\Repositories\StockRepository;
use Illuminate\Support\ServiceProvider;

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
}
