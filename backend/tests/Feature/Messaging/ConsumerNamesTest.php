<?php

use Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Ordering\Core\Consumers\MarkOrderAsAwaitingPayment;
use Modules\Ordering\Core\Consumers\MarkOrderAsDelivered;
use Modules\Ordering\Core\Consumers\MarkOrderAsPaid;
use Modules\Ordering\Core\Consumers\RecordEstimatedDelivery;
use Tests\Feature\Outbox\Fixtures\OtherEvent;
use Tests\Feature\Outbox\Fixtures\OtherEventConsumer;
use Tests\Feature\Outbox\Fixtures\SampleEvent;
use Tests\Feature\Outbox\Fixtures\SpyConsumer;

/** Everything the subscriptions hold for the consumers, to compare before and after a refused one. */
function subscriptionsSnapshot(EventSubscriptions $subscriptions): array
{
    return [
        $subscriptions->consumerClasses(),
        $subscriptions->all(),
        array_map(fn (string $name) => $subscriptions->eventNamesOf($name), array_keys($subscriptions->consumerClasses())),
    ];
}

it('subscribes the five consumers under their stable names', function (string $name, string $consumer, string $event) {
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
]);

it('subscribes no consumer under any other name', function () {
    expect(array_keys(app(EventSubscriptions::class)->consumerClasses()))->toEqualCanonicalizing([
        'ordering.mark_order_as_awaiting_payment',
        'ordering.mark_order_as_paid',
        'ordering.record_estimated_delivery',
        'ordering.mark_order_as_delivered',
        'fulfillment.schedule_order_delivery',
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
