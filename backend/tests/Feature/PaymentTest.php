<?php

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResult;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Payment\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Event::fake([PaymentApproved::class]));

function orderAwaitingPayment(CustomerAccount $customer, int $totalCents = 12345): Order
{
    return Order::factory()->for($customer, 'customer')
        ->status(OrderStatus::AwaitingPayment)
        ->create(['total_cents' => $totalCents]);
}

function payOrder(Order $order, array $body): TestResponse
{
    return test()->postJson("/api/orders/{$order->id}/payment", $body);
}

function paymentRows(Order $order): array
{
    return DB::table('payments')->where('order_id', $order->id)->orderBy('id')->get()->all();
}

it('approves the payment of an order awaiting payment', function () {
    $user = customer();
    $order = orderAwaitingPayment($user);

    $this->actingAs($user);
    payOrder($order, ['card_token' => 'fake_card_approved'])
        ->assertAccepted()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('message', 'Pagamento aprovado. O pedido será atualizado em instantes.');
});

it('records the approved payment and announces it once', function () {
    $user = customer();
    $order = orderAwaitingPayment($user);

    $this->actingAs($user);
    payOrder($order, ['card_token' => 'fake_card_approved'])->assertAccepted();

    $rows = paymentRows($order);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->status)->toBe('approved')
        ->and($rows[0]->amount_cents)->toBe(12345)
        ->and($rows[0]->decline_reason)->toBeNull()
        ->and($rows[0]->card_token)->toBe('fake_card_approved')
        ->and($rows[0]->gateway)->toBe('fake')
        ->and($rows[0]->gateway_transaction_id)->toStartWith('fake_');

    Event::assertDispatchedTimes(PaymentApproved::class, 1);
    Event::assertDispatched(PaymentApproved::class, fn (PaymentApproved $event) => $event->orderId === $order->id);
});

it('declines the payment and keeps the order awaiting payment', function (string $cardToken, string $reason, string $message) {
    $user = customer();
    $order = orderAwaitingPayment($user);

    $this->actingAs($user);
    $response = payOrder($order, ['card_token' => $cardToken])->assertStatus(402);

    assertApiError($response, 'PAYMENT_DECLINED', $message);

    $rows = paymentRows($order);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->status)->toBe('declined')
        ->and($rows[0]->decline_reason)->toBe($reason)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);

    Event::assertNotDispatched(PaymentApproved::class);
})->with([
    'insufficient funds' => ['fake_card_insufficient_funds', 'insufficient_funds', 'Pagamento recusado: saldo insuficiente.'],
    'declined by the issuer' => ['fake_card_declined', 'card_declined', 'Pagamento recusado pelo emissor do cartão.'],
    'unknown card' => ['tok_unknown', 'invalid_card', 'Cartão inválido.'],
]);

it('approves a new attempt after a declined one', function () {
    $user = customer();
    $order = orderAwaitingPayment($user);

    $this->actingAs($user);
    payOrder($order, ['card_token' => 'fake_card_declined'])->assertStatus(402);
    payOrder($order, ['card_token' => 'fake_card_approved'])->assertAccepted();

    $rows = paymentRows($order);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->status)->toBe('declined')
        ->and($rows[1]->status)->toBe('approved');
});

it('rejects an invalid card token without charging', function (array $body) {
    $user = customer();
    $order = orderAwaitingPayment($user);
    $gateway = spyPaymentGateway();

    $this->actingAs($user);
    payOrder($order, $body)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors('card_token');

    expect(paymentRows($order))->toBeEmpty()
        ->and($gateway->charges)->toBeEmpty();
})->with([
    'missing' => [[]],
    'not a string' => [['card_token' => 123]],
    '65 characters' => [['card_token' => str_repeat('a', 65)]],
]);

it('accepts a card token of exactly 64 characters', function () {
    $user = customer();
    $order = orderAwaitingPayment($user);

    $this->actingAs($user);
    payOrder($order, ['card_token' => str_repeat('a', 64)])
        ->assertStatus(402)
        ->assertJsonPath('code', 'PAYMENT_DECLINED');
});

it('rejects payment for orders that are not awaiting payment', function (OrderStatus $status) {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status($status)->create();
    $gateway = spyPaymentGateway();

    $this->actingAs($user)->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    expect(paymentRows($order))->toBeEmpty()
        ->and($gateway->charges)->toBeEmpty();
    Event::assertNotDispatched(PaymentApproved::class);
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);

it('rejects a second payment while the queue has not moved the order', function () {
    $user = customer();
    $order = orderAwaitingPayment($user);
    Payment::factory()->for($order)->approved()->create();
    $gateway = spyPaymentGateway();

    $this->actingAs($user);
    payOrder($order, ['card_token' => 'fake_card_approved'])
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    expect(paymentRows($order))->toHaveCount(1)
        ->and($gateway->charges)->toBeEmpty();
});

it('loses the race to a concurrent approval with 409', function () {
    $user = customer();
    $order = orderAwaitingPayment($user);

    // The concurrent request that won records its approval while this one is being charged.
    spyPaymentGateway(function (ChargeRequest $request) use ($order): ChargeResult {
        Payment::factory()->for($order)->approved()->create();

        return new ChargeResult(PaymentStatus::Approved, null, 'fake_loser', 'fake');
    });

    $this->actingAs($user);
    payOrder($order, ['card_token' => 'fake_card_approved'])
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    expect(collect(paymentRows($order))->where('status', 'approved'))->toHaveCount(1);
    Event::assertNotDispatched(PaymentApproved::class);
});

it('charges the order total and ignores amounts in the request', function () {
    $user = customer();
    $order = orderAwaitingPayment($user, 12345);
    $gateway = spyPaymentGateway();

    $this->actingAs($user);
    payOrder($order, ['card_token' => 'fake_card_approved', 'amount_cents' => 1, 'total_cents' => 1])
        ->assertAccepted();

    expect($gateway->charges)->toHaveCount(1)
        ->and($gateway->charges[0]->amountCents)->toBe(12345)
        ->and(paymentRows($order)[0]->amount_cents)->toBe(12345);
});

it('forbids paying an order of another customer', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();
    $gateway = spyPaymentGateway();

    $this->actingAs(customer())->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])
        ->assertForbidden();

    expect(paymentRows($order))->toBeEmpty()
        ->and($gateway->charges)->toBeEmpty();
    Event::assertNotDispatched(PaymentApproved::class);
});

it('allows only one approved payment per order in the database', function () {
    $order = Order::factory()->status(OrderStatus::AwaitingPayment)->create();
    Payment::factory()->for($order)->approved()->create();

    expect(fn () => DB::transaction(fn () => Payment::factory()->for($order)->approved()->create()))
        ->toThrow(QueryException::class);

    Payment::factory()->for($order)->declined()->create();

    expect(paymentRows($order))->toHaveCount(2);
});

it('rejects inconsistent payment rows in the database', function (array $attributes) {
    $order = Order::factory()->create();

    expect(fn () => DB::table('payments')->insert([
        'order_id' => $order->id,
        'amount_cents' => 1000,
        'card_token' => 'fake_card_approved',
        'gateway' => 'fake',
        'gateway_transaction_id' => 'fake_1',
        ...$attributes,
    ]))->toThrow(QueryException::class);
})->with([
    'declined without a reason' => [['status' => 'declined', 'decline_reason' => null]],
    'approved with a reason' => [['status' => 'approved', 'decline_reason' => 'card_declined']],
    'negative amount' => [['status' => 'approved', 'decline_reason' => null, 'amount_cents' => -1]],
]);

it('ties payments to existing orders and keeps orders that have payments', function () {
    $paid = Order::factory()->create();
    Payment::factory()->for($paid)->declined()->create();
    $unpaid = Order::factory()->create();

    expect(fn () => DB::transaction(fn () => Payment::factory()->create(['order_id' => 999999])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('orders')->where('id', $paid->id)->delete()))
        ->toThrow(QueryException::class);

    DB::table('orders')->where('id', $unpaid->id)->delete();

    expect(Order::query()->whereKey($unpaid->id)->exists())->toBeFalse()
        ->and(Order::query()->whereKey($paid->id)->exists())->toBeTrue();
});

it('logs each payment attempt without the card token', function (string $cardToken) {
    $user = customer();
    $order = orderAwaitingPayment($user);
    Log::spy();

    $this->actingAs($user);
    payOrder($order, ['card_token' => $cardToken]);

    $row = paymentRows($order)[0];
    Log::shouldHaveReceived('info')
        ->with('Payment attempt recorded.', Mockery::on(function (array $context) use ($row, $cardToken): bool {
            $keys = array_keys($context);
            sort($keys);

            return $keys === ['decline_reason', 'order_id', 'payment_id', 'status']
                && $context['order_id'] === $row->order_id
                && $context['payment_id'] === $row->id
                && $context['status'] === $row->status
                && $context['decline_reason'] === $row->decline_reason
                && ! str_contains(json_encode($context), $cardToken);
        }))
        ->once();
})->with(['fake_card_declined', 'fake_card_approved']);

it('throttles payment attempts after 20 per minute', function () {
    $user = customer();
    $order = orderAwaitingPayment($user);

    $this->actingAs($user);
    foreach (range(1, 20) as $attempt) {
        expect(payOrder($order, ['card_token' => 'fake_card_declined'])->status())->not->toBe(429);
    }

    payOrder($order, ['card_token' => 'fake_card_declined'])->assertTooManyRequests();
});
