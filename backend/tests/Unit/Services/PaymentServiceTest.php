<?php

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

it('accepts orders awaiting payment', function () {
    (new PaymentService)->ensureCanBePaid(new Order(['status' => OrderStatus::AwaitingPayment]));
})->throwsNoExceptions();

it('rejects orders that are not awaiting payment', function (OrderStatus $status) {
    expect(fn () => (new PaymentService)->ensureCanBePaid(new Order(['status' => $status])))
        ->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);
