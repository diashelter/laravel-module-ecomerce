<?php

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\DTOs\ChargeResult;
use App\Modules\Payment\DTOs\PayOrderDTO;
use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Payment\Exceptions\PaymentDeclinedException;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\UseCases\PayOrderUseCase;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake([PaymentApproved::class]));

it('dispatches PaymentApproved for an order awaiting payment', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();

    $result = app(PayOrderUseCase::class)->execute($order, new PayOrderDTO('fake_card_approved'));

    expect($result->relationLoaded('items'))->toBeTrue();
    Event::assertDispatched(PaymentApproved::class, fn (PaymentApproved $event) => $event->order->is($order));
});

it('does not dispatch PaymentApproved for orders that are not awaiting payment', function (OrderStatus $status) {
    $order = Order::factory()->status($status)->create();

    expect(fn () => app(PayOrderUseCase::class)->execute($order, new PayOrderDTO('fake_card_approved')))
        ->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');

    Event::assertNotDispatched(PaymentApproved::class);
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);

it('keeps the declined payment after raising the decline', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create(['total_cents' => 5000]);
    spyPaymentGateway(fn () => new ChargeResult(PaymentStatus::Declined, DeclineReason::CardDeclined, 'fake_declined', 'fake'));

    $declined = null;
    try {
        app(PayOrderUseCase::class)->execute($order, new PayOrderDTO('fake_card_declined'));
    } catch (PaymentDeclinedException $e) {
        $declined = $e;
    }

    expect($declined)->not->toBeNull()
        ->and($declined->render()->getStatusCode())->toBe(402);

    $rows = DB::table('payments')->where('order_id', $order->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->status)->toBe('declined')
        ->and($rows[0]->decline_reason)->toBe('card_declined');
    Event::assertNotDispatched(PaymentApproved::class);
});

it('raises a conflict when a concurrent approval wins the race', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create(['total_cents' => 5000]);
    spyPaymentGateway(function () use ($order): ChargeResult {
        Payment::factory()->for($order)->approved()->create();

        return new ChargeResult(PaymentStatus::Approved, null, 'fake_loser', 'fake');
    });

    expect(fn () => app(PayOrderUseCase::class)->execute($order, new PayOrderDTO('fake_card_approved')))
        ->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.');

    expect(DB::table('payments')->where('order_id', $order->id)->where('status', 'approved')->count())->toBe(1);
    Event::assertNotDispatched(PaymentApproved::class);
});
