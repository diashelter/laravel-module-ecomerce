<?php

declare(strict_types=1);

namespace Modules\Integration\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Integration\Payment\OrdersToPayFromOrdering;
use Modules\Integration\Payment\UnavailableOrdersToPay;
use Modules\Ordering\Contracts\PayableOrders;
use Modules\Payment\Contracts\OrdersToPay;

/**
 * Binds the port of each module to the contract of the module that serves it (see .design/module-isolation.md).
 * A module is installed when its contract can be loaded: a port whose module is missing answers 503, and the
 * port of a missing module is not bound at all.
 */
class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment asks the ordering module for the order it charges.
        if (interface_exists(OrdersToPay::class)) {
            $this->app->bind(OrdersToPay::class, interface_exists(PayableOrders::class)
                ? OrdersToPayFromOrdering::class
                : UnavailableOrdersToPay::class);
        }
    }
}
