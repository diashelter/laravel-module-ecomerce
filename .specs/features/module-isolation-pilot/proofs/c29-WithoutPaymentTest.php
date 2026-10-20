<?php

use Illuminate\Support\Facades\DB;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Events\OrderPlaced;
use Modules\Ordering\Persistence\Models\Order;

/*
 * Proof C29 and C39, run only inside a removal copy with no payment module (c29-without-payment.sh copies it
 * into the shared module's tests there): the payment route is gone with its module, and nothing moves an order
 * awaiting payment.
 */
it('answers 404 to the payment route without the payment module installed', function () {
    expect(interface_exists('Modules\Payment\Contracts\OrdersToPay'))->toBeFalse();

    signInAsCustomer();

    $this->postJson('/api/orders/'.unknownId().'/payment', ['card_token' => 'fake_card_approved'])->assertNotFound();
});

it('keeps an order awaiting payment without the payment module installed', function () {
    $user = CustomerAccount::factory()->create();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::Placed)->create();

    // The order goes through its own consumers, as in production: ordering.order_placed, delivered by the
    // relay, moves it to awaiting_payment, so a consumer that moved it further would show below.
    DB::transaction(fn () => app(EventOutbox::class)->record(new OrderPlaced($order->id)));
    relayUntilIdle();
    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);

    $this->actingAs($user, 'customer')
        ->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])
        ->assertNotFound();
    relayUntilIdle();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(DB::table('outbox_messages')->where('name', 'payment.payment_approved')->count())->toBe(0);
});
