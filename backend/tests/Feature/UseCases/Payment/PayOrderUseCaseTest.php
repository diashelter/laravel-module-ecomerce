<?php

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Payment\UseCases\PayOrderUseCase;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake([PaymentApproved::class]));

it('dispatches PaymentApproved for an order awaiting payment', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();

    $result = app(PayOrderUseCase::class)->execute($order);

    expect($result->relationLoaded('items'))->toBeTrue();
    Event::assertDispatched(PaymentApproved::class, fn (PaymentApproved $event) => $event->order->is($order));
});

it('does not dispatch PaymentApproved for orders that are not awaiting payment', function (OrderStatus $status) {
    $order = Order::factory()->status($status)->create();

    expect(fn () => app(PayOrderUseCase::class)->execute($order))
        ->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');

    Event::assertNotDispatched(PaymentApproved::class);
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);
