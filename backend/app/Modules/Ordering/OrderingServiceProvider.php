<?php

declare(strict_types=1);

namespace App\Modules\Ordering;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;
use App\Modules\Ordering\Contracts\PayableOrders;
use App\Modules\Ordering\Repositories\OrderRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts the ordering module publishes, and those other modules define and the
 * ordering module implements.
 */
class OrderingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CustomerOrderHistory::class => OrderRepository::class,
        PayableOrders::class => OrderRepository::class,
        ProductOrderHistory::class => OrderRepository::class,
    ];
}
