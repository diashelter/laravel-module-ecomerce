<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery;
use Modules\Fulfillment\Core\Jobs\DeliverOrder;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Messaging\Core\EventTransport;
use Modules\Messaging\Tests\Fixtures\FailingOutbox;
use Modules\Ordering\Core\Consumers\MarkOrderAsPaid;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Events\OrderPaid;
use Modules\Ordering\Persistence\Models\Order;
use Modules\Payment\Events\PaymentApproved;

/*
 * The order events and the change they announce commit together, and the three windows of the
 * problem close: a transport that fails only delays the order.
 */

/** The outbox records through the real repository, then fails for that event name. */
function failOutboxFor(string $name): void
{
    app()->instance(EventOutbox::class, new FailingOutbox($name));
}

/** The real outbox again. */
function restoreOutbox(): void
{
    app()->forgetInstance(EventOutbox::class);
}

/** The queue transport again, after a test replaced it. */
function restoreTransport(): void
{
    app()->forgetInstance(EventTransport::class);
}

it('keeps neither the approval nor the message when the outbox fails', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create();
    failOutboxFor('payment.payment_approved');
    // As in production: the error envelope instead of the debug page.
    config(['app.debug' => false]);

    $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved']);

    assertApiError($response->assertStatus(500), 'SERVER_ERROR', 'Erro interno do servidor.');
    expect(DB::table('payments')->where('order_id', $order->id)->where('status', 'approved')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

it('keeps the order awaiting payment when recording order paid fails', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();
    failOutboxFor('ordering.order_paid');

    expect(fn () => (new MarkOrderAsPaid)->handle(new PaymentApproved($order->id)))
        ->toThrow(RuntimeException::class, 'outbox down');

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(outboxMessages('ordering.order_paid'))->toBeEmpty();

    // The retried delivery, with the outbox working again, does the transition and records it once.
    restoreOutbox();
    (new MarkOrderAsPaid)->handle(new PaymentApproved($order->id));

    expect($order->fresh()->status)->toBe(OrderStatus::PaymentApproved)
        ->and(outboxMessages('ordering.order_paid'))->toHaveCount(1);
});

it('pushes no delivery job when recording the schedule fails', function () {
    Bus::fake([DeliverOrder::class]);
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    failOutboxFor('fulfillment.delivery_scheduled');

    expect(fn () => (new ScheduleOrderDelivery)->handle(new OrderPaid($order->id, 2)))
        ->toThrow(RuntimeException::class, 'outbox down');

    Bus::assertNotDispatched(DeliverOrder::class);
    expect(DB::table('outbox_messages')->count())->toBe(0);
});

it('recovers a placed order after the transport comes back', function () {
    $user = customer();
    $product = productWithStock(3);
    $orderId = $this->actingAs($user)
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'address_id' => addressOf($user)->id])
        ->assertCreated()
        ->json('data.id');
    $message = outboxMessages('ordering.order_placed')->sole();

    // Window 1: the transport is down when the relay publishes OrderPlaced.
    spyTransport(failing: [$message->id]);

    expect(relayOnce())->toBe(1)
        ->and(Order::find($orderId)->status)->toBe(OrderStatus::Placed)
        ->and($message->fresh()->published_at)->toBeNull();

    restoreTransport();

    expect(relayOnce())->toBe(0)
        ->and(Order::find($orderId)->status)->toBe(OrderStatus::AwaitingPayment);
});

it('recovers an approved payment after the transport comes back', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create();
    $this->actingAs($user);
    $this->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])->assertAccepted();
    $message = outboxMessages('payment.payment_approved')->sole();

    // Window 2: the transport is down when the relay publishes PaymentApproved.
    spyTransport(failing: [$message->id]);

    expect(relayOnce())->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);

    // The charge is approved: a new attempt is still refused while the order waits.
    $this->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])->assertConflict();

    restoreTransport();

    expect(relayOnce())->toBe(0)
        ->and($order->fresh()->status)->toBeIn([OrderStatus::PaymentApproved, OrderStatus::Delivered]);
});
