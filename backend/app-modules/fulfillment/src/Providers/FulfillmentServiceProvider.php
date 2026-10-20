<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery;
use Modules\Fulfillment\Core\Services\ShippingRateTable;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Ordering\Contracts\ShippingQuoter;
use Modules\Ordering\Events\OrderPaid;

/**
 * Wires the contracts the ordering module defines and the fulfillment module implements, and
 * subscribes the fulfillment consumer to the event it reacts to.
 */
class FulfillmentServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ShippingQuoter::class => ShippingRateTable::class,
    ];

    public function boot(EventSubscriptions $subscriptions): void
    {
        $subscriptions->subscribe(OrderPaid::class, ScheduleOrderDelivery::class, 'fulfillment.schedule_order_delivery');
    }
}
