<?php

/*
 * Proof C29, run only inside a removal copy with no payment module (c29-without-payment.sh copies it into the
 * shared module's tests there): the payment route is gone with its module.
 */
it('answers 404 to the payment route without the payment module installed', function () {
    expect(interface_exists('Modules\Payment\Contracts\OrdersToPay'))->toBeFalse();

    signInAsCustomer();

    $this->postJson('/api/orders/'.unknownId().'/payment', ['card_token' => 'fake_card_approved'])->assertNotFound();
});
