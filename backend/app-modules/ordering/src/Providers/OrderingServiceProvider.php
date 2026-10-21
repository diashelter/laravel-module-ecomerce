<?php

declare(strict_types=1);

namespace Modules\Ordering\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Contracts\ProductOrderHistory;
use Modules\Fulfillment\Events\DeliveryScheduled;
use Modules\Fulfillment\Events\OrderDelivered;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Ordering\Contracts\CustomerOrderHistory;
use Modules\Ordering\Contracts\OrderMetrics;
use Modules\Ordering\Contracts\PayableOrders;
use Modules\Ordering\Core\Consumers\MarkOrderAsAwaitingPayment;
use Modules\Ordering\Core\Consumers\MarkOrderAsDelivered;
use Modules\Ordering\Core\Consumers\MarkOrderAsPaid;
use Modules\Ordering\Core\Consumers\RecordEstimatedDelivery;
use Modules\Ordering\Core\InboundEvents\PaymentApproved;
use Modules\Ordering\Events\OrderPlaced;
use Modules\Ordering\Persistence\Queries\OrderMetricsQuery;
use Modules\Ordering\Persistence\Repositories\OrderRepository;
use Modules\Shared\Support\RecordId;

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

    public function register(): void
    {
        // The route parameters this module owns are canonical UUIDs (see CatalogServiceProvider::register()).
        foreach (['order'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }

    public function boot(EventSubscriptions $subscriptions): void
    {
        $subscriptions->subscribe(OrderPlaced::class, MarkOrderAsAwaitingPayment::class, 'ordering.mark_order_as_awaiting_payment');
        $subscriptions->subscribe(PaymentApproved::class, MarkOrderAsPaid::class, 'ordering.mark_order_as_paid');
        $subscriptions->subscribe(DeliveryScheduled::class, RecordEstimatedDelivery::class, 'ordering.record_estimated_delivery');
        $subscriptions->subscribe(OrderDelivered::class, MarkOrderAsDelivered::class, 'ordering.mark_order_as_delivered');
    }
}
