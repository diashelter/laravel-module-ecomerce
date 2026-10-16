<?php

use App\Modules\Fulfillment\Events\DeliveryScheduled;
use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Fulfillment\Jobs\DeliverOrder;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Shared\Messaging\DomainEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/*
 * The order events and the delivery job cross module boundaries (and the events are stored in the
 * outbox), so they carry ids and values, never an Eloquent model.
 */
it('carries no model in the order events and the delivery job', function (string $class) {
    $properties = (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC);

    expect($properties)->not->toBeEmpty();

    foreach ($properties as $property) {
        $types = $property->getType() instanceof ReflectionUnionType
            ? $property->getType()->getTypes()
            : [$property->getType()];

        foreach ($types as $type) {
            $name = $type instanceof ReflectionNamedType ? $type->getName() : null;

            expect($name !== null && is_a($name, Model::class, true))
                ->toBeFalse("{$class}::\${$property->getName()} carries the model {$name}");
        }
    }
})->with([
    OrderPlaced::class,
    OrderPaid::class,
    PaymentApproved::class,
    DeliveryScheduled::class,
    OrderDelivered::class,
    DeliverOrder::class,
]);

/*
 * The published format of the order events (door 2): a stable name and version, and a payload of
 * scalars under snake_case keys, read back by fromPayload().
 */
it('declares the name and version of every order event', function (string $event, string $name) {
    expect($event::eventName())->toBe($name)
        ->and($event::eventName())->toMatch('/^[a-z]+\.[a-z_]+$/')
        ->and($event::eventVersion())->toBe(1);
})->with([
    [OrderPlaced::class, 'ordering.order_placed'],
    [PaymentApproved::class, 'payment.payment_approved'],
    [OrderPaid::class, 'ordering.order_paid'],
    [DeliveryScheduled::class, 'fulfillment.delivery_scheduled'],
    [OrderDelivered::class, 'fulfillment.order_delivered'],
]);

const PAYLOAD_ORDER_ID = '0199f0a0-0000-7000-8000-0000000000aa';

it('maps every order event payload and rebuilds it', function (Closure $make, array $payload) {
    /** @var DomainEvent $event */
    $event = $make();

    expect($event->toPayload())->toBe($payload);

    $rebuilt = $event::fromPayload($event->toPayload());

    expect($rebuilt)->toBeInstanceOf($event::class)
        ->and($rebuilt->orderId)->toBe(PAYLOAD_ORDER_ID)
        ->and($rebuilt->toPayload())->toBe($payload);

    if ($event instanceof OrderPaid) {
        expect($rebuilt->deliveryBusinessDays)->toBe(3);
    }

    if ($event instanceof DeliveryScheduled) {
        expect($rebuilt->estimatedDeliveryOn->setTimezone('America/Sao_Paulo')->format('Y-m-d'))->toBe('2026-10-09');
    }
})->with([
    'OrderPlaced' => [fn () => new OrderPlaced(PAYLOAD_ORDER_ID), ['order_id' => PAYLOAD_ORDER_ID]],
    'PaymentApproved' => [fn () => new PaymentApproved(PAYLOAD_ORDER_ID), ['order_id' => PAYLOAD_ORDER_ID]],
    'OrderPaid' => [fn () => new OrderPaid(PAYLOAD_ORDER_ID, 3), ['order_id' => PAYLOAD_ORDER_ID, 'delivery_business_days' => 3]],
    // 2026-10-09 00:00 in São Paulo is 03:00 in UTC: the payload carries the São Paulo day.
    'DeliveryScheduled' => [
        fn () => new DeliveryScheduled(PAYLOAD_ORDER_ID, CarbonImmutable::parse('2026-10-09 03:00:00', 'UTC')),
        ['order_id' => PAYLOAD_ORDER_ID, 'estimated_delivery_on' => '2026-10-09'],
    ],
    'OrderDelivered' => [fn () => new OrderDelivered(PAYLOAD_ORDER_ID), ['order_id' => PAYLOAD_ORDER_ID]],
]);
