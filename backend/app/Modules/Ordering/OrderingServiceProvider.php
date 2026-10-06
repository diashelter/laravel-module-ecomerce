<?php

declare(strict_types=1);

namespace App\Modules\Ordering;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Ordering\Repositories\OrderRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts other modules define and the ordering module implements.
 */
class OrderingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProductOrderHistory::class => OrderRepository::class,
    ];
}
