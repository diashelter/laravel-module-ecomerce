<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment;

use App\Modules\Fulfillment\Services\ShippingRateTable;
use App\Modules\Ordering\Contracts\ShippingQuoter;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts the ordering module defines and the fulfillment module implements.
 */
class FulfillmentServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ShippingQuoter::class => ShippingRateTable::class,
    ];
}
