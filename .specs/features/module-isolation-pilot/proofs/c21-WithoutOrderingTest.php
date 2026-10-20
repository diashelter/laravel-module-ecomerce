<?php

use Illuminate\Support\Facades\DB;
use Modules\Integration\Payment\UnavailableOrdersToPay;
use Modules\Payment\Contracts\OrdersToPay;

/*
 * Proof C21, run only inside a removal copy with no ordering module (c21-payment-without-ordering.sh copies it
 * into the payment module's tests there): the container gives the unavailable port and the route answers 503.
 */
it('answers 503 for the payment without the ordering module installed', function () {
    expect(interface_exists('Modules\Ordering\Contracts\PayableOrders'))->toBeFalse()
        ->and(app(OrdersToPay::class))->toBeInstanceOf(UnavailableOrdersToPay::class);

    $gateway = spyPaymentGateway();
    signInAsCustomer();

    $response = $this->postJson('/api/orders/'.unknownId().'/payment', ['card_token' => 'fake_card_approved']);

    assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.');
    expect($gateway->charges)->toBeEmpty()
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});
