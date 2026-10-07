<?php

use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Gateways\FakePaymentGateway;

function fakeCharge(string $cardToken, int $amountCents = 1000): ChargeRequest
{
    return new ChargeRequest(orderId: 1, amountCents: $amountCents, cardToken: $cardToken);
}

it('approves the fake approved card', function () {
    $result = (new FakePaymentGateway)->charge(fakeCharge('fake_card_approved'));

    expect($result->status)->toBe(PaymentStatus::Approved)
        ->and($result->declineReason)->toBeNull()
        ->and($result->gateway)->toBe('fake')
        ->and($result->transactionId)->toStartWith('fake_');
});

it('declines each fake card with its reason', function (string $cardToken, DeclineReason $reason) {
    $result = (new FakePaymentGateway)->charge(fakeCharge($cardToken));

    expect($result->status)->toBe(PaymentStatus::Declined)
        ->and($result->declineReason)->toBe($reason);
})->with([
    'insufficient funds' => ['fake_card_insufficient_funds', DeclineReason::InsufficientFunds],
    'declined by the issuer' => ['fake_card_declined', DeclineReason::CardDeclined],
    'unknown token' => ['tok_unknown', DeclineReason::InvalidCard],
    'empty token' => ['', DeclineReason::InvalidCard],
]);

it('ignores the amount and issues a new transaction id per charge', function (string $cardToken) {
    $gateway = new FakePaymentGateway;

    $zero = $gateway->charge(fakeCharge($cardToken, 0));
    $huge = $gateway->charge(fakeCharge($cardToken, 9999999999));

    expect($zero->status)->toBe($huge->status)
        ->and($zero->transactionId)->not->toBe($huge->transactionId);
})->with(['fake_card_approved', 'fake_card_insufficient_funds']);
