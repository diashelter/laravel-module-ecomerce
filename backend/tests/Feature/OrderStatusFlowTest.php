<?php

use App\Modules\Fulfillment\Consumers\ScheduleOrderDelivery;
use App\Modules\Fulfillment\Events\DeliveryScheduled;
use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Fulfillment\Jobs\DeliverOrder;
use App\Modules\Ordering\Consumers\MarkOrderAsAwaitingPayment;
use App\Modules\Ordering\Consumers\MarkOrderAsDelivered;
use App\Modules\Ordering\Consumers\MarkOrderAsPaid;
use App\Modules\Ordering\Consumers\RecordEstimatedDelivery;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Shared\Messaging\DeliverEvent;
use App\Modules\Shared\Messaging\EventOutbox;
use App\Modules\Shared\Messaging\EventSubscriptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('subscribes exactly the five order consumers', function () {
    $pairs = [];

    foreach (app(EventSubscriptions::class)->all() as $name => $consumers) {
        foreach ($consumers as $consumer) {
            $pairs[] = "{$name} -> {$consumer}";
        }
    }

    expect($pairs)->toHaveCount(5)->toEqualCanonicalizing([
        'ordering.order_placed -> '.MarkOrderAsAwaitingPayment::class,
        'payment.payment_approved -> '.MarkOrderAsPaid::class,
        'fulfillment.delivery_scheduled -> '.RecordEstimatedDelivery::class,
        'fulfillment.order_delivered -> '.MarkOrderAsDelivered::class,
        'ordering.order_paid -> '.ScheduleOrderDelivery::class,
    ]);
});

it('delivers OrderPlaced through the queue', function () {
    Queue::fake();
    $order = Order::factory()->create();
    DB::transaction(fn () => app(EventOutbox::class)->record(new OrderPlaced($order->id)));

    expect(relayOnce())->toBe(0);

    Queue::assertPushed(DeliverEvent::class, 1);
    Queue::assertPushed(DeliverEvent::class, fn (DeliverEvent $job) => $job->consumer === MarkOrderAsAwaitingPayment::class
        && $job->envelope->name === 'ordering.order_placed'
        && $job->envelope->payload === ['order_id' => $order->id]);
    expect($order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('moves a placed order to awaiting payment', function () {
    $order = Order::factory()->status(OrderStatus::Placed)->create();

    (new MarkOrderAsAwaitingPayment)->handle(new OrderPlaced($order->id));

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

it('marks the order as paid and announces it, without scheduling the delivery itself', function () {
    Bus::fake([DeliverOrder::class]);
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create(['delivery_business_days' => 4]);

    (new MarkOrderAsPaid)->handle(new PaymentApproved($order->id));

    $payload = outboxMessages('ordering.order_paid')->sole()->payload;

    expect($order->fresh()->status)->toBe(OrderStatus::PaymentApproved)
        ->and($payload)->toEqual(['order_id' => $order->id, 'delivery_business_days' => 4])
        ->and($payload['delivery_business_days'])->toBe(4);
    Bus::assertNotDispatched(DeliverOrder::class);
});

it('schedules the delivery job with a delay when the order is paid', function () {
    Bus::fake([DeliverOrder::class]);
    config(['shop.delivery_delay_seconds' => 90]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Sao_Paulo'));
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new ScheduleOrderDelivery)->handle(new OrderPaid($order->id, $order->delivery_business_days));

    Bus::assertDispatchedTimes(DeliverOrder::class, 1);
    Bus::assertDispatched(DeliverOrder::class, fn (DeliverOrder $job) => $job->orderId === $order->id
        && CarbonImmutable::instance($job->delay)->equalTo(now()->addSeconds(90)));
});

it('announces the delivery estimate when the order is paid', function () {
    Bus::fake([DeliverOrder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Sao_Paulo'));
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create(['delivery_business_days' => 2]);

    (new ScheduleOrderDelivery)->handle(new OrderPaid($order->id, $order->delivery_business_days));

    expect(outboxMessages('fulfillment.delivery_scheduled')->sole()->payload)
        ->toEqual(['order_id' => $order->id, 'estimated_delivery_on' => '2026-10-09']);
    Bus::assertDispatchedTimes(DeliverOrder::class, 1);
});

it('schedules the delivery from the business days in OrderPaid', function () {
    Bus::fake([DeliverOrder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00', 'America/Sao_Paulo'));
    // The stored value differs on purpose: the delivery is scheduled from the event alone.
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create(['delivery_business_days' => 9]);

    (new ScheduleOrderDelivery)->handle(new OrderPaid($order->id, 3));

    expect(outboxMessages('fulfillment.delivery_scheduled')->sole()->payload)
        ->toEqual(['order_id' => $order->id, 'estimated_delivery_on' => '2026-10-08']);
    Bus::assertDispatchedTimes(DeliverOrder::class, 1);
    Bus::assertDispatched(DeliverOrder::class, fn (DeliverOrder $job) => $job->orderId === $order->id);
});

it('logs the scheduled delivery without the address', function () {
    Bus::fake([DeliverOrder::class]);
    Log::spy();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Sao_Paulo'));
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create(['delivery_business_days' => 2]);

    (new ScheduleOrderDelivery)->handle(new OrderPaid($order->id, $order->delivery_business_days));

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'Delivery scheduled.'
        && $context === ['order_id' => $order->id, 'estimated_delivery_on' => '2026-10-09'])->once();
});

it('records the delivery estimate without changing the status', function () {
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new RecordEstimatedDelivery)->handle(new DeliveryScheduled($order->id, CarbonImmutable::parse('2026-10-09')));

    $stored = $order->fresh();
    expect($stored->estimated_delivery_on->toDateString())->toBe('2026-10-09')
        ->and($stored->status)->toBe(OrderStatus::PaymentApproved);
});

it('keeps the first delivery estimate', function () {
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new RecordEstimatedDelivery)->handle(new DeliveryScheduled($order->id, CarbonImmutable::parse('2026-10-09')));
    (new RecordEstimatedDelivery)->handle(new DeliveryScheduled($order->id, CarbonImmutable::parse('2026-10-12')));

    expect($order->fresh()->estimated_delivery_on->toDateString())->toBe('2026-10-09');
});

it('records the estimate of an order already delivered', function () {
    $order = Order::factory()->status(OrderStatus::Delivered)->create();

    (new RecordEstimatedDelivery)->handle(new DeliveryScheduled($order->id, CarbonImmutable::parse('2026-10-09')));

    $stored = $order->fresh();
    expect($stored->estimated_delivery_on->toDateString())->toBe('2026-10-09')
        ->and($stored->status)->toBe(OrderStatus::Delivered);
});

it('announces the delivery without changing the order status itself', function () {
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new DeliverOrder($order->id))->handle();

    expect(outboxMessages('fulfillment.order_delivered')->sole()->payload)->toEqual(['order_id' => $order->id])
        ->and($order->fresh()->status)->toBe(OrderStatus::PaymentApproved);
});

it('marks a paid order as delivered', function () {
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new MarkOrderAsDelivered)->handle(new OrderDelivered($order->id));

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('ignores out of order or duplicated executions (idempotency)', function () {
    $order = Order::factory()->status(OrderStatus::Placed)->create();

    // Payment approval or delivery of an order that is not in the expected status does nothing.
    (new MarkOrderAsPaid)->handle(new PaymentApproved($order->id));
    (new MarkOrderAsDelivered)->handle(new OrderDelivered($order->id));

    expect($order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and(outboxMessages('ordering.order_paid'))->toBeEmpty();

    // A late OrderPlaced does not move an order that already left "placed".
    $paid = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    (new MarkOrderAsAwaitingPayment)->handle(new OrderPlaced($paid->id));
    expect($paid->fresh()->status)->toBe(OrderStatus::PaymentApproved);

    // A second OrderDelivered for an order already delivered changes nothing.
    $delivered = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    (new MarkOrderAsDelivered)->handle(new OrderDelivered($delivered->id));
    $afterFirst = $delivered->fresh();
    $this->travel(1)->minutes();
    (new MarkOrderAsDelivered)->handle(new OrderDelivered($delivered->id));
    $afterSecond = $delivered->fresh();

    expect($afterFirst->status)->toBe(OrderStatus::Delivered)
        ->and($afterSecond->status)->toBe(OrderStatus::Delivered)
        ->and($afterSecond->updated_at->equalTo($afterFirst->updated_at))->toBeTrue();
});

it('announces OrderPaid only once for a duplicated payment approval', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();

    (new MarkOrderAsPaid)->handle(new PaymentApproved($order->id));
    (new MarkOrderAsPaid)->handle(new PaymentApproved($order->id));

    expect(outboxMessages('ordering.order_paid'))->toHaveCount(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::PaymentApproved);
});

it('runs the whole lifecycle with a synchronous queue', function () {
    $user = customer();
    $product = productWithStock(3);

    $orderId = $this->actingAs($user)
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'address_id' => addressOf($user)->id])
        ->assertCreated()
        ->json('data.id');

    // QUEUE_CONNECTION=sync in tests: the consumer runs inside the relay cycle.
    expect(relayOnce())->toBe(0)
        ->and(Order::find($orderId)->status)->toBe(OrderStatus::AwaitingPayment);

    $this->withHeader('X-Request-ID', 'trace-payment-0001')
        ->postJson("/api/orders/{$orderId}/payment", ['card_token' => 'fake_card_approved'])
        ->assertAccepted();

    // PaymentApproved -> OrderPaid -> DeliveryScheduled and DeliverOrder -> OrderDelivered, each
    // recorded by a consumer and published by the next relay cycle (the sync queue ignores the delay).
    relayUntilIdle();

    expect(Order::find($orderId)->status)->toBe(OrderStatus::Delivered)
        ->and(Order::find($orderId)->estimated_delivery_on)->not->toBeNull();

    foreach (['payment.payment_approved', 'ordering.order_paid', 'fulfillment.delivery_scheduled', 'fulfillment.order_delivered'] as $name) {
        expect(outboxMessages($name))->toHaveCount(1)
            ->and(outboxMessages($name)->sole()->request_id)->toBe('trace-payment-0001');
    }
});
