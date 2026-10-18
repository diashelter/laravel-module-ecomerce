<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment;

use App\Modules\Fulfillment\Consumers\ScheduleOrderDelivery;
use App\Modules\Fulfillment\Services\ShippingRateTable;
use App\Modules\Ordering\Contracts\ShippingQuoter;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Shared\Messaging\EventSubscriptions;
use Illuminate\Support\ServiceProvider;

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
