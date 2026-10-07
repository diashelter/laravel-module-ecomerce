<?php

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

it('accepts orders awaiting payment', function () {
    (new PaymentService)->ensureCanBePaid(new Order(['status' => OrderStatus::AwaitingPayment]), hasApprovedPayment: false);
})->throwsNoExceptions();

it('rejects orders that are not awaiting payment', function (OrderStatus $status) {
    expect(fn () => (new PaymentService)->ensureCanBePaid(new Order(['status' => $status]), hasApprovedPayment: false))
        ->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);

it('decides whether an order can be paid', function (OrderStatus $status, bool $hasApprovedPayment, bool $payable) {
    $check = fn () => (new PaymentService)->ensureCanBePaid(new Order(['status' => $status]), $hasApprovedPayment);

    $payable
        ? expect($check)->not->toThrow(BusinessRuleException::class)
        : expect($check)->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');
})->with([
    'awaiting payment, nothing approved' => [OrderStatus::AwaitingPayment, false, true],
    'awaiting payment, already approved' => [OrderStatus::AwaitingPayment, true, false],
    'placed' => [OrderStatus::Placed, false, false],
    'payment approved' => [OrderStatus::PaymentApproved, false, false],
    'delivered' => [OrderStatus::Delivered, false, false],
]);
