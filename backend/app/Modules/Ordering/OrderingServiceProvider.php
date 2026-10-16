<?php

declare(strict_types=1);

namespace App\Modules\Ordering;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Fulfillment\Events\DeliveryScheduled;
use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Ordering\Consumers\MarkOrderAsAwaitingPayment;
use App\Modules\Ordering\Consumers\MarkOrderAsDelivered;
use App\Modules\Ordering\Consumers\MarkOrderAsPaid;
use App\Modules\Ordering\Consumers\RecordEstimatedDelivery;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;
use App\Modules\Ordering\Contracts\OrderMetrics;
use App\Modules\Ordering\Contracts\PayableOrders;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Queries\OrderMetricsQuery;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Shared\Messaging\EventSubscriptions;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts the ordering module publishes, and those other modules define and the
 * ordering module implements, and subscribes the ordering consumers to the events they react to.
 */
class OrderingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CustomerOrderHistory::class => OrderRepository::class,
        OrderMetrics::class => OrderMetricsQuery::class,
        PayableOrders::class => OrderRepository::class,
        ProductOrderHistory::class => OrderRepository::class,
    ];

    public function boot(EventSubscriptions $subscriptions): void
    {
        $subscriptions->subscribe(OrderPlaced::class, MarkOrderAsAwaitingPayment::class);
        $subscriptions->subscribe(PaymentApproved::class, MarkOrderAsPaid::class);
        $subscriptions->subscribe(DeliveryScheduled::class, RecordEstimatedDelivery::class);
        $subscriptions->subscribe(OrderDelivered::class, MarkOrderAsDelivered::class);
    }
}
