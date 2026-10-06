<?php

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Events\PaymentApproved;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake([PaymentApproved::class]));

it('approves the fake payment of an order awaiting payment', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/payment")
        ->assertAccepted()
        ->assertJsonPath('data.id', $order->id);

    Event::assertDispatched(PaymentApproved::class, fn (PaymentApproved $event) => $event->order->is($order));
});

it('rejects payment for orders that are not awaiting payment', function (OrderStatus $status) {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status($status)->create();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/payment")
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    Event::assertNotDispatched(PaymentApproved::class);
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);

it('forbids paying an order of another customer', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();

    $this->actingAs(customer())->postJson("/api/orders/{$order->id}/payment")->assertForbidden();

    Event::assertNotDispatched(PaymentApproved::class);
});
