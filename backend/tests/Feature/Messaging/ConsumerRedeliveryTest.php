<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery;
use Modules\Fulfillment\Core\Jobs\DeliverOrder;
use Modules\Fulfillment\Events\DeliveryScheduled;
use Modules\Fulfillment\Events\OrderDelivered;
use Modules\Fulfillment\Persistence\Models\Delivery;
use Modules\Ordering\Core\Consumers\MarkOrderAsAwaitingPayment;
use Modules\Ordering\Core\Consumers\MarkOrderAsDelivered;
use Modules\Ordering\Core\Consumers\MarkOrderAsPaid;
use Modules\Ordering\Core\Consumers\RecordEstimatedDelivery;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Events\OrderPaid;
use Modules\Ordering\Events\OrderPlaced;
use Modules\Ordering\Persistence\Models\Order;
use Modules\Payment\Events\PaymentApproved;

/*
 * The delivery is at least once, so every consumer has to take the same message twice: the second
 * delivery changes nothing in the database and records nothing in the outbox. Each test delivers
 * one envelope twice through EventDelivery, the class both transports call, never the handle().
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'UTC'));
});

it('redelivers OrderPlaced to MarkOrderAsAwaitingPayment', function () {
    $order = Order::factory()->status(OrderStatus::Placed)->create();
    $envelope = envelopeOf(new OrderPlaced($order->id));
    $this->travel(1)->minutes();

    deliverEnvelope($envelope, MarkOrderAsAwaitingPayment::class);
    $afterFirst = $order->fresh();
    $messages = DB::table('outbox_messages')->count();
    $this->travel(1)->minutes();
    deliverEnvelope($envelope, MarkOrderAsAwaitingPayment::class);
    $afterSecond = $order->fresh();

    expect($afterFirst->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($afterFirst->updated_at->equalTo(now()->subMinute()))->toBeTrue()
        ->and($afterSecond->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($afterSecond->updated_at->equalTo($afterFirst->updated_at))->toBeTrue()
        ->and(DB::table('outbox_messages')->count())->toBe($messages);
});

it('redelivers PaymentApproved to MarkOrderAsPaid', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();
    $envelope = envelopeOf(new PaymentApproved($order->id));

    deliverEnvelope($envelope, MarkOrderAsPaid::class);
    deliverEnvelope($envelope, MarkOrderAsPaid::class);

    expect($order->fresh()->status)->toBe(OrderStatus::PaymentApproved)
        ->and(outboxMessages('ordering.order_paid'))->toHaveCount(1)
        ->and(DB::table('outbox_messages')->count())->toBe(1);
});

it('redelivers DeliveryScheduled to RecordEstimatedDelivery', function () {
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    // The payload carries the São Paulo day, so the dates are built there.
    $first = envelopeOf(new DeliveryScheduled($order->id, CarbonImmutable::parse('2026-10-09', 'America/Sao_Paulo')));
    // The same message id, so the same message, but a payload with another date: the first one stays.
    $again = envelopeOf(new DeliveryScheduled($order->id, CarbonImmutable::parse('2026-10-12', 'America/Sao_Paulo')), ['id' => $first->id]);

    deliverEnvelope($first, RecordEstimatedDelivery::class);
    deliverEnvelope($first, RecordEstimatedDelivery::class);
    deliverEnvelope($again, RecordEstimatedDelivery::class);

    $stored = $order->fresh();

    expect($stored->estimated_delivery_on->toDateString())->toBe('2026-10-09')
        ->and($stored->status)->toBe(OrderStatus::PaymentApproved)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

it('redelivers OrderDelivered to MarkOrderAsDelivered', function () {
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    $envelope = envelopeOf(new OrderDelivered($order->id));
    $this->travel(1)->minutes();

    deliverEnvelope($envelope, MarkOrderAsDelivered::class);
    $afterFirst = $order->fresh();
    $this->travel(1)->minutes();
    deliverEnvelope($envelope, MarkOrderAsDelivered::class);
    $afterSecond = $order->fresh();

    expect($afterFirst->status)->toBe(OrderStatus::Delivered)
        ->and($afterFirst->updated_at->equalTo(now()->subMinute()))->toBeTrue()
        ->and($afterSecond->status)->toBe(OrderStatus::Delivered)
        ->and($afterSecond->updated_at->equalTo($afterFirst->updated_at))->toBeTrue();
});

it('redelivers OrderPaid to ScheduleOrderDelivery', function () {
    // The job is only counted here (DeliverOrder has its own tests): the redelivery of an order not
    // yet delivered queues it again, and the guard of the job makes the second one a no-op.
    Queue::fake();
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    $envelope = envelopeOf(new OrderPaid($order->id, 2));

    deliverEnvelope($envelope, ScheduleOrderDelivery::class);
    deliverEnvelope($envelope, ScheduleOrderDelivery::class);

    expect(Delivery::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(DB::table('outbox_messages')->count())->toBe(1);
    Queue::assertPushedTimes(DeliverOrder::class, 2);
});
