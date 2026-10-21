<?php

use Modules\Payment\Core\Services\PaymentService;
use Modules\Payment\Core\ValueObjects\OrderToPay;
use Modules\Shared\Exceptions\BusinessRuleException;

it('accepts orders awaiting payment', function () {
    (new PaymentService)->ensureCanBePaid(new OrderToPay('1', '1', 1000, awaitingPayment: true), hasApprovedPayment: false);
})->throwsNoExceptions();

it('rejects orders that are not awaiting payment', function () {
    expect(fn () => (new PaymentService)->ensureCanBePaid(new OrderToPay('1', '1', 1000, awaitingPayment: false), hasApprovedPayment: false))
        ->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');
});

it('decides whether an order can be paid', function (bool $awaitingPayment, bool $hasApprovedPayment, bool $payable) {
    $check = fn () => (new PaymentService)->ensureCanBePaid(new OrderToPay('1', '1', 1000, $awaitingPayment), $hasApprovedPayment);

    $payable
        ? expect($check)->not->toThrow(BusinessRuleException::class)
        : expect($check)->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');
})->with([
    'awaiting payment, nothing approved' => [true, false, true],
    'awaiting payment, already approved' => [true, true, false],
    'not awaiting payment, nothing approved' => [false, false, false],
    'not awaiting payment, already approved' => [false, true, false],
]);
