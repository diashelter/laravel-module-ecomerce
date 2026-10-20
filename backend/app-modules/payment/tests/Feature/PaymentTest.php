<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Messaging\Tests\Fixtures\FailingOutbox;
use Modules\Payment\Core\DTOs\ChargeRequest;
use Modules\Payment\Core\DTOs\ChargeResult;
use Modules\Payment\Core\Enums\PaymentStatus;
use Modules\Payment\Core\ValueObjects\OrderToPay;
use Modules\Payment\Persistence\Models\Payment;
use Modules\Shared\Support\RecordId;

/*
 * The payment of an order, with the order port of the payment module answered by a double: the order
 * itself belongs to the ordering module, and the flows with the real one live in the composition suite
 * (backend/tests/Feature/PaymentFlowTest.php).
 */

/** An order of the signed-in customer awaiting payment, served by the order port double. */
function orderAwaitingPayment(int $totalCents = 12345): OrderToPay
{
    $order = orderToPay(signInAsCustomer(), $totalCents);
    fakeOrdersToPay($order);

    return $order;
}

function payOrder(OrderToPay $order, array $body): TestResponse
{
    return test()->postJson("/api/orders/{$order->id}/payment", $body);
}

function paymentRows(string $orderId): array
{
    return DB::table('payments')->where('order_id', $orderId)->orderBy('id')->get()->all();
}

it('approves the payment of an order awaiting payment', function () {
    $order = orderAwaitingPayment();

    $response = payOrder($order, ['card_token' => 'fake_card_approved'])
        ->assertAccepted()
        ->assertJsonPath('data.order_id', $order->id)
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.amount_cents', 12345)
        ->assertJsonPath('message', 'Pagamento aprovado. O pedido será atualizado em instantes.');

    expect($response->json('data.id'))->toBe(paymentRows($order->id)[0]->id);
});

it('answers the payment attempt without card data', function () {
    $order = orderAwaitingPayment();

    $data = payOrder($order, ['card_token' => 'fake_card_approved'])->assertAccepted()->json('data');

    expect(array_keys($data))->toEqualCanonicalizing(['id', 'order_id', 'status', 'amount_cents']);
});

it('answers 404 to the payment of an unknown order', function () {
    $gateway = spyPaymentGateway();
    signInAsCustomer();
    fakeOrdersToPay();

    $this->postJson('/api/orders/'.unknownId().'/payment', ['card_token' => 'fake_card_approved'])
        ->assertNotFound();

    expect(DB::table('payments')->count())->toBe(0)
        ->and($gateway->charges)->toBeEmpty();
});

it('records the approved payment and announces it once', function () {
    $order = orderAwaitingPayment();

    payOrder($order, ['card_token' => 'fake_card_approved'])->assertAccepted();

    $rows = paymentRows($order->id);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->status)->toBe('approved')
        ->and($rows[0]->amount_cents)->toBe(12345)
        ->and($rows[0]->decline_reason)->toBeNull()
        ->and($rows[0]->card_token)->toBe('fake_card_approved')
        ->and($rows[0]->gateway)->toBe('fake')
        ->and($rows[0]->gateway_transaction_id)->toStartWith('fake_');

    $messages = outboxMessages('payment.payment_approved');
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->payload)->toBe(['order_id' => $order->id]);
});

it('keeps no approved payment when the event cannot be recorded', function () {
    $order = orderAwaitingPayment();
    app()->instance(EventOutbox::class, new FailingOutbox('payment.payment_approved'));
    // As in production: the error envelope instead of the debug page.
    config(['app.debug' => false]);

    assertApiError(payOrder($order, ['card_token' => 'fake_card_approved'])->assertStatus(500), 'SERVER_ERROR', 'Erro interno do servidor.');

    expect(DB::table('payments')->where('order_id', $order->id)->where('status', 'approved')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

// The order itself stays awaiting payment because nothing announced an approval; the order status with
// the real ordering module is asserted in PaymentFlowTest.
it('declines the payment and keeps the order awaiting payment', function (string $cardToken, string $reason, string $message) {
    $order = orderAwaitingPayment();

    $response = payOrder($order, ['card_token' => $cardToken])->assertStatus(402);

    assertApiError($response, 'PAYMENT_DECLINED', $message);

    $rows = paymentRows($order->id);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->status)->toBe('declined')
        ->and($rows[0]->decline_reason)->toBe($reason)
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
})->with([
    'insufficient funds' => ['fake_card_insufficient_funds', 'insufficient_funds', 'Pagamento recusado: saldo insuficiente.'],
    'declined by the issuer' => ['fake_card_declined', 'card_declined', 'Pagamento recusado pelo emissor do cartão.'],
    'unknown card' => ['tok_unknown', 'invalid_card', 'Cartão inválido.'],
]);

it('approves a new attempt after a declined one', function () {
    $order = orderAwaitingPayment();

    payOrder($order, ['card_token' => 'fake_card_declined'])->assertStatus(402);
    payOrder($order, ['card_token' => 'fake_card_approved'])->assertAccepted();

    $rows = paymentRows($order->id);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->status)->toBe('declined')
        ->and($rows[1]->status)->toBe('approved');
});

it('rejects an invalid card token without charging', function (array $body) {
    $order = orderAwaitingPayment();
    $gateway = spyPaymentGateway();

    payOrder($order, $body)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors('card_token');

    expect(paymentRows($order->id))->toBeEmpty()
        ->and($gateway->charges)->toBeEmpty();
})->with([
    'missing' => [[]],
    'not a string' => [['card_token' => 123]],
    '65 characters' => [['card_token' => str_repeat('a', 65)]],
]);

it('accepts a card token of exactly 64 characters', function () {
    $order = orderAwaitingPayment();

    payOrder($order, ['card_token' => str_repeat('a', 64)])
        ->assertStatus(402)
        ->assertJsonPath('code', 'PAYMENT_DECLINED');
});

// Which statuses of the ordering module are "awaiting payment" is the adapter's translation
// (OrdersToPayAdapterTest); each real status is paid through the route in PaymentFlowTest.
it('rejects payment for orders that are not awaiting payment', function () {
    $order = orderToPay(signInAsCustomer(), awaitingPayment: false);
    fakeOrdersToPay($order);
    $gateway = spyPaymentGateway();

    payOrder($order, ['card_token' => 'fake_card_approved'])
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    expect(paymentRows($order->id))->toBeEmpty()
        ->and($gateway->charges)->toBeEmpty()
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
});

it('rejects a second payment while the queue has not moved the order', function () {
    $order = orderAwaitingPayment();
    Payment::factory()->approved()->create(['order_id' => $order->id]);
    $gateway = spyPaymentGateway();

    payOrder($order, ['card_token' => 'fake_card_approved'])
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    expect(paymentRows($order->id))->toHaveCount(1)
        ->and($gateway->charges)->toBeEmpty();
});

it('loses the race to a concurrent approval with 409', function () {
    $order = orderAwaitingPayment();

    // The concurrent request that won records its approval while this one is being charged.
    spyPaymentGateway(function (ChargeRequest $request) use ($order): ChargeResult {
        Payment::factory()->approved()->create(['order_id' => $order->id]);

        return new ChargeResult(PaymentStatus::Approved, null, 'fake_loser', 'fake');
    });

    payOrder($order, ['card_token' => 'fake_card_approved'])
        ->assertConflict()
        ->assertJsonPath('message', 'Este pedido não está aguardando pagamento.');

    expect(collect(paymentRows($order->id))->where('status', 'approved'))->toHaveCount(1)
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
});

it('charges the order total and ignores amounts in the request', function () {
    $order = orderAwaitingPayment(12345);
    $gateway = spyPaymentGateway();

    payOrder($order, ['card_token' => 'fake_card_approved', 'amount_cents' => 1, 'total_cents' => 1])
        ->assertAccepted();

    expect($gateway->charges)->toHaveCount(1)
        ->and($gateway->charges[0]->amountCents)->toBe(12345)
        ->and(paymentRows($order->id)[0]->amount_cents)->toBe(12345);
});

// The port answers an order only to its owner, as the lookup by owner of the ordering module does
// (CustomerRecordRoutesTest proves the real one): another customer's order is an unknown id, never 409.
it('forbids paying an order of another customer', function () {
    $order = orderToPay(unknownId());
    $placed = orderToPay(unknownId(), awaitingPayment: false);
    fakeOrdersToPay($order, $placed);
    $gateway = spyPaymentGateway();
    signInAsCustomer();

    assertApiError($this->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');
    assertApiError($this->postJson("/api/orders/{$placed->id}/payment", ['card_token' => 'fake_card_approved'])->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');

    expect(paymentRows($order->id))->toBeEmpty()
        ->and(paymentRows($placed->id))->toBeEmpty()
        ->and($gateway->charges)->toBeEmpty()
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
});

it('asks the order port with the signed-in customer', function () {
    $order = orderAwaitingPayment();
    $port = fakeOrdersToPay($order);

    payOrder($order, ['card_token' => 'fake_card_approved'])->assertAccepted();

    expect($port->asked)->toBe([[auth('customer')->id(), $order->id]]);
});

it('validates the payment body before checking the order owner', function () {
    $order = orderToPay(unknownId());
    $port = fakeOrdersToPay($order);
    signInAsCustomer();

    $this->postJson("/api/orders/{$order->id}/payment", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('card_token');

    expect(paymentRows($order->id))->toBeEmpty()
        ->and($port->asked)->toBe([]);
});

it('validates the body before an unavailable order port', function () {
    $port = unavailableOrdersToPay();
    signInAsCustomer();

    $this->postJson('/api/orders/'.unknownId().'/payment', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('card_token');

    expect($port->calls)->toBe(0);
});

it('answers 503 when the order port is unavailable', function () {
    $port = unavailableOrdersToPay();
    $gateway = spyPaymentGateway();
    signInAsCustomer();

    $response = $this->postJson('/api/orders/'.unknownId().'/payment', ['card_token' => 'fake_card_approved']);

    assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.');
    expect($port->calls)->toBe(1)
        ->and($gateway->charges)->toBeEmpty()
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

it('requires a signed-in customer to pay', function () {
    $order = orderToPay(unknownId());
    $port = fakeOrdersToPay($order);
    $gateway = spyPaymentGateway();

    assertApiError($this->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])->assertUnauthorized(), 'UNAUTHENTICATED', 'Não autenticado.');

    expect($port->asked)->toBe([])
        ->and($gateway->charges)->toBeEmpty();
});

it('allows only one approved payment per order in the database', function () {
    $orderId = unknownId();
    Payment::factory()->approved()->create(['order_id' => $orderId]);

    expect(fn () => DB::transaction(fn () => Payment::factory()->approved()->create(['order_id' => $orderId])))
        ->toThrow(QueryException::class);

    Payment::factory()->declined()->create(['order_id' => $orderId]);

    expect(paymentRows($orderId))->toHaveCount(2);
});

it('rejects inconsistent payment rows in the database', function (array $attributes) {
    expect(fn () => DB::table('payments')->insert([
        'order_id' => unknownId(),
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

// The orders table belongs to the ordering module: without it installed the table does not exist.
it('creates a payment without creating an order', function () {
    $payment = Payment::factory()->create();

    expect(DB::table('payments')->count())->toBe(1)
        ->and(Schema::hasTable('orders') ? DB::table('orders')->count() : 0)->toBe(0)
        ->and(RecordId::isCanonical($payment->order_id))->toBeTrue()
        ->and($payment->order_id[14])->toBe('7');
});

it('logs each payment attempt without the card token', function (string $cardToken) {
    $order = orderAwaitingPayment();
    Log::spy();

    payOrder($order, ['card_token' => $cardToken]);

    $row = paymentRows($order->id)[0];
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
    $order = orderAwaitingPayment();

    foreach (range(1, 20) as $attempt) {
        expect(payOrder($order, ['card_token' => 'fake_card_declined'])->status())->not->toBe(429);
    }

    payOrder($order, ['card_token' => 'fake_card_declined'])->assertTooManyRequests();
});
