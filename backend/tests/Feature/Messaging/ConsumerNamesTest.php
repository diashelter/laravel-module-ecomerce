<?php

use Modules\Catalog\Core\Consumers\ProjectCategoryCreated;
use Modules\Catalog\Core\Consumers\ProjectCategoryDeleted;
use Modules\Catalog\Core\Consumers\ProjectCategoryUpdated;
use Modules\Catalog\Core\Consumers\ProjectProductCreated;
use Modules\Catalog\Core\Consumers\ProjectProductDeleted;
use Modules\Catalog\Core\Consumers\ProjectProductStatusChanged;
use Modules\Catalog\Core\Consumers\ProjectProductUpdated;
use Modules\Catalog\Core\Consumers\ProjectStockChanged;
use Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Messaging\Core\EventSubscriptionRegistry;
use Modules\Messaging\Tests\Fixtures\OtherEvent;
use Modules\Messaging\Tests\Fixtures\OtherEventConsumer;
use Modules\Messaging\Tests\Fixtures\SampleEvent;
use Modules\Messaging\Tests\Fixtures\SpyConsumer;
use Modules\Ordering\Core\Consumers\MarkOrderAsAwaitingPayment;
use Modules\Ordering\Core\Consumers\MarkOrderAsDelivered;
use Modules\Ordering\Core\Consumers\MarkOrderAsPaid;
use Modules\Ordering\Core\Consumers\RecordEstimatedDelivery;
use Modules\Ordering\Core\InboundEvents\PaymentApproved as InboundPaymentApproved;

/** Everything the subscriptions hold for the consumers, to compare before and after a refused one. */
function subscriptionsSnapshot(EventSubscriptions $subscriptions): array
{
    return [
        $subscriptions->consumerClasses(),
        $subscriptions->all(),
        array_map(fn (string $name) => $subscriptions->eventNamesOf($name), array_keys($subscriptions->consumerClasses())),
    ];
}

it('subscribes the thirteen consumers under their stable names', function (string $name, string $consumer, string $event) {
    $subscriptions = app(EventSubscriptions::class);

    expect($subscriptions->consumerOf($name))->toBe($consumer)
        ->and($subscriptions->nameOf($consumer))->toBe($name)
        ->and($subscriptions->eventNamesOf($name))->toBe([$event]);
})->with([
    'awaiting payment' => ['ordering.mark_order_as_awaiting_payment', MarkOrderAsAwaitingPayment::class, 'ordering.order_placed'],
    'paid' => ['ordering.mark_order_as_paid', MarkOrderAsPaid::class, 'payment.payment_approved'],
    'estimated delivery' => ['ordering.record_estimated_delivery', RecordEstimatedDelivery::class, 'fulfillment.delivery_scheduled'],
    'delivered' => ['ordering.mark_order_as_delivered', MarkOrderAsDelivered::class, 'fulfillment.order_delivered'],
    'schedule delivery' => ['fulfillment.schedule_order_delivery', ScheduleOrderDelivery::class, 'ordering.order_paid'],
    'project product created' => ['catalog.project_product_created', ProjectProductCreated::class, 'catalog.product_created'],
    'project product updated' => ['catalog.project_product_updated', ProjectProductUpdated::class, 'catalog.product_updated'],
    'project product status changed' => ['catalog.project_product_status_changed', ProjectProductStatusChanged::class, 'catalog.product_status_changed'],
    'project product deleted' => ['catalog.project_product_deleted', ProjectProductDeleted::class, 'catalog.product_deleted'],
    'project category created' => ['catalog.project_category_created', ProjectCategoryCreated::class, 'catalog.category_created'],
    'project category updated' => ['catalog.project_category_updated', ProjectCategoryUpdated::class, 'catalog.category_updated'],
    'project category deleted' => ['catalog.project_category_deleted', ProjectCategoryDeleted::class, 'catalog.category_deleted'],
    'project stock changed' => ['catalog.project_stock_changed', ProjectStockChanged::class, 'inventory.stock_changed'],
]);

// The ordering module reads the approval of the payment module with a class of its own: the published
// format is the name, the version and the payload keys, never the producer's class.
it('reads the payment approval with the class of the ordering module', function () {
    expect(app(EventSubscriptionRegistry::class)->eventOf('payment.payment_approved'))->toBe(InboundPaymentApproved::class);
});

it('subscribes no consumer under any other name', function () {
    expect(array_keys(app(EventSubscriptions::class)->consumerClasses()))->toEqualCanonicalizing([
        'ordering.mark_order_as_awaiting_payment',
        'ordering.mark_order_as_paid',
        'ordering.record_estimated_delivery',
        'ordering.mark_order_as_delivered',
        'fulfillment.schedule_order_delivery',
        'catalog.project_product_created',
        'catalog.project_product_updated',
        'catalog.project_product_status_changed',
        'catalog.project_product_deleted',
        'catalog.project_category_created',
        'catalog.project_category_updated',
        'catalog.project_category_deleted',
        'catalog.project_stock_changed',
    ]);
});

it('refuses a consumer name out of the format', function (string $name) {
    $subscriptions = app(EventSubscriptions::class);
    $before = subscriptionsSnapshot($subscriptions);

    expect(fn () => $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class, $name))->toThrow(LogicException::class);

    expect(subscriptionsSnapshot($subscriptions))->toBe($before);
})->with(['Ordering.mark_paid', 'ordering', 'ordering.mark-paid', 'ordering.mark paid']);

it('refuses one consumer name for two consumers', function () {
    $subscriptions = app(EventSubscriptions::class);
    $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class, 'testing.spy_consumer');
    $before = subscriptionsSnapshot($subscriptions);

    expect(fn () => $subscriptions->subscribe(OtherEvent::class, OtherEventConsumer::class, 'testing.spy_consumer'))
        ->toThrow(LogicException::class);

    expect(subscriptionsSnapshot($subscriptions))->toBe($before);
});

it('refuses two names for one consumer', function () {
    $subscriptions = app(EventSubscriptions::class);
    $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class, 'testing.spy_consumer');
    $before = subscriptionsSnapshot($subscriptions);

    expect(fn () => $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class, 'testing.other_name'))
        ->toThrow(LogicException::class);

    expect(subscriptionsSnapshot($subscriptions))->toBe($before);
});
