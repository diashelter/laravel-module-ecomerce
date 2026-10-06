<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Contracts\StockInitializer;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Inventory\Repositories\StockRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts the inventory publishes to the other modules.
 */
class InventoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        StockInitializer::class => StockRepository::class,
        StockReservation::class => StockRepository::class,
    ];
}
