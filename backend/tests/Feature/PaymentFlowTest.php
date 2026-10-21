<?php

use Illuminate\Support\Facades\DB;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Persistence\Models\Order;

/*
 * The payment with the real ordering module behind the order port of the payment module (wired by the
 * integration package). The payment rules themselves are proven in the payment module's own tests.
 */

it('keeps the order awaiting payment after a declined payment', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_declined'])
        ->assertStatus(402);

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
});

it('rejects payment for orders that are not awaiting payment', function (OrderStatus $status) {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status($status)->create();
    $gateway = spyPaymentGateway();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    expect(DB::table('payments')->where('order_id', $order->id)->count())->toBe(0)
        ->and($gateway->charges)->toBeEmpty()
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);
