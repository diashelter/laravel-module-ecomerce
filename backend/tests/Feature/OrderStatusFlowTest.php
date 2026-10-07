<?php

use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Fulfillment\Jobs\DeliverOrder;
use App\Modules\Fulfillment\Listeners\ScheduleOrderDelivery;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Listeners\MarkOrderAsAwaitingPayment;
use App\Modules\Ordering\Listeners\MarkOrderAsDelivered;
use App\Modules\Ordering\Listeners\MarkOrderAsPaid;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Events\PaymentApproved;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('registers the listeners for the order events', function () {
    Event::fake();

    Event::assertListening(OrderPlaced::class, MarkOrderAsAwaitingPayment::class);
    Event::assertListening(PaymentApproved::class, MarkOrderAsPaid::class);
    Event::assertListening(OrderPaid::class, ScheduleOrderDelivery::class);
    Event::assertListening(OrderDelivered::class, MarkOrderAsDelivered::class);
});

it('processes OrderPlaced through the queue', function () {
    Queue::fake();
    $order = Order::factory()->create();

    OrderPlaced::dispatch($order);

    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === MarkOrderAsAwaitingPayment::class);
    expect($order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('moves a placed order to awaiting payment', function () {
    $order = Order::factory()->status(OrderStatus::Placed)->create();

    (new MarkOrderAsAwaitingPayment)->handle(new OrderPlaced($order));

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

it('marks the order as paid and announces it, without scheduling the delivery itself', function () {
    Event::fake([OrderPaid::class]);
    Bus::fake([DeliverOrder::class]);
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();

    (new MarkOrderAsPaid)->handle(new PaymentApproved($order));

    expect($order->fresh()->status)->toBe(OrderStatus::PaymentApproved);
    Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->order->is($order));
    Bus::assertNotDispatched(DeliverOrder::class);
});

it('schedules the delivery job with a delay when the order is paid', function () {
    Bus::fake([DeliverOrder::class]);
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new ScheduleOrderDelivery)->handle(new OrderPaid($order));

    Bus::assertDispatched(DeliverOrder::class, fn (DeliverOrder $job) => $job->order->is($order) && $job->delay !== null);
});

it('announces the delivery without changing the order status itself', function () {
    Event::fake([OrderDelivered::class]);
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new DeliverOrder($order))->handle();

    Event::assertDispatched(OrderDelivered::class, fn (OrderDelivered $event) => $event->order->is($order));
    expect($order->fresh()->status)->toBe(OrderStatus::PaymentApproved);
});

it('marks a paid order as delivered', function () {
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();

    (new MarkOrderAsDelivered)->handle(new OrderDelivered($order));

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('ignores out of order or duplicated executions (idempotency)', function () {
    Event::fake([OrderPaid::class]);
    $order = Order::factory()->status(OrderStatus::Placed)->create();

    // Payment approval or delivery of an order that is not in the expected status does nothing.
    (new MarkOrderAsPaid)->handle(new PaymentApproved($order));
    (new MarkOrderAsDelivered)->handle(new OrderDelivered($order));

    expect($order->fresh()->status)->toBe(OrderStatus::Placed);
    Event::assertNotDispatched(OrderPaid::class);
});

it('announces OrderPaid only once for a duplicated payment approval', function () {
    Event::fake([OrderPaid::class]);
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();

    (new MarkOrderAsPaid)->handle(new PaymentApproved($order));
    (new MarkOrderAsPaid)->handle(new PaymentApproved($order));

    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('runs the whole lifecycle with a synchronous queue', function () {
    $user = customer();
    $product = productWithStock(3);

    $orderId = $this->actingAs($user)
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
        ->assertCreated()
        ->json('data.id');

    // QUEUE_CONNECTION=sync in tests: the listener already ran.
    expect(Order::find($orderId)->status)->toBe(OrderStatus::AwaitingPayment);

    $this->postJson("/api/orders/{$orderId}/payment", ['card_token' => 'fake_card_approved'])->assertAccepted();

    // PaymentApproved -> OrderPaid -> DeliverOrder -> OrderDelivered, all synchronous
    // (the sync queue ignores the delivery delay).
    expect(Order::find($orderId)->status)->toBe(OrderStatus::Delivered);
});
