<?php

use Modules\Integration\Payment\OrdersToPayFromOrdering;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Persistence\Models\Order;
use Modules\Payment\Contracts\OrdersToPay;
use Modules\Payment\Core\ValueObjects\OrderToPay;

/*
 * The order port of the payment module, served by the ordering module through the integration package.
 */

it('resolves the order port to the integration adapter', function () {
    expect(app(OrdersToPay::class))->toBeInstanceOf(OrdersToPayFromOrdering::class);
});

it('translates each order status into awaiting payment', function (OrderStatus $status, bool $awaitingPayment) {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status($status)->create(['total_cents' => 15990]);

    expect(app(OrdersToPay::class)->findForPayment($user->id, $order->id))
        ->toEqual(new OrderToPay($order->id, $user->id, 15990, $awaitingPayment));
})->with([
    'placed' => [OrderStatus::Placed, false],
    'awaiting payment' => [OrderStatus::AwaitingPayment, true],
    'payment approved' => [OrderStatus::PaymentApproved, false],
    'delivered' => [OrderStatus::Delivered, false],
]);

it('answers null for an order the customer cannot pay', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create();

    expect(app(OrdersToPay::class)->findForPayment(customer()->id, $order->id))->toBeNull()
        ->and(app(OrdersToPay::class)->findForPayment($user->id, unknownId()))->toBeNull();
});
