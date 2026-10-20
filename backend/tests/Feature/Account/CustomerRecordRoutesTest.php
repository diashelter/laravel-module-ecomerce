<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Persistence\Models\Order;

/** The 4 routes of the customer that point to a record: [method, uri, kind of record, expected status for the owner]. */
dataset('customer record routes', [
    'show order' => ['GET', 'orders/{id}', 'order', 200],
    'pay order' => ['POST', 'orders/{id}/payment', 'payable order', 202],
    'update address' => ['PUT', 'account/addresses/{id}', 'address', 200],
    'delete address' => ['DELETE', 'account/addresses/{id}', 'address', 204],
]);

/** A record of that kind that belongs to the customer. */
function recordOf(string $kind, CustomerAccount $owner): string
{
    return match ($kind) {
        'order' => Order::factory()->for($owner, 'customer')->create()->id,
        'payable order' => Order::factory()->for($owner, 'customer')->status(OrderStatus::AwaitingPayment)->create()->id,
        'address' => addressOf($owner)->id,
    };
}

function customerBodyOf(string $method, string $uri): array
{
    return match (true) {
        str_ends_with($uri, '/payment') => ['card_token' => 'fake_card_approved'],
        $method === 'PUT' => addressPayload(),
        default => [],
    };
}

function customerRecordUri(string $uri, string $id): string
{
    return '/api/'.str_replace('{id}', $id, $uri);
}

/** Everything the customer routes could change, as plain rows. */
function customerRecordRows(): array
{
    return collect(['orders', 'payments', 'customer_addresses'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])
        ->all();
}

it('answers the owner on the customer record routes', function (string $method, string $uri, string $kind, int $status) {
    $owner = customer();
    $id = recordOf($kind, $owner);

    $this->actingAs($owner)->json($method, customerRecordUri($uri, $id), customerBodyOf($method, $uri))->assertStatus($status);
})->with('customer record routes');

it('answers another customer record as an unknown id', function (string $method, string $uri, string $kind) {
    $gateway = spyPaymentGateway();
    $id = recordOf($kind, customer());
    $before = customerRecordRows();
    $body = customerBodyOf($method, $uri);
    $this->actingAs(customer());

    $foreign = $this->json($method, customerRecordUri($uri, $id), $body);
    $unknown = $this->json($method, customerRecordUri($uri, unknownId()), $body);

    assertApiError($foreign->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');
    expect(Arr::except($foreign->json(), 'request_id'))->toBe(Arr::except($unknown->json(), 'request_id'))
        ->and(customerRecordRows())->toBe($before)
        ->and($gateway->charges)->toBeEmpty()
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
})->with('customer record routes');

it('answers 404 before the status for another customer order', function (OrderStatus $status) {
    $order = Order::factory()->status($status)->create();
    $gateway = spyPaymentGateway();

    $response = $this->actingAs(customer())->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved']);

    assertApiError($response->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');
    expect($gateway->charges)->toBeEmpty()
        ->and(DB::table('payments')->count())->toBe(0);
})->with([OrderStatus::Placed, OrderStatus::PaymentApproved, OrderStatus::Delivered]);

it('answers 404 to the payment of an unknown order', function () {
    $gateway = spyPaymentGateway();

    $response = $this->actingAs(customer())->postJson('/api/orders/'.unknownId().'/payment', ['card_token' => 'fake_card_approved']);

    assertApiError($response->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');
    expect($gateway->charges)->toBeEmpty()
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(outboxMessages('payment.payment_approved'))->toBeEmpty();
});

it('validates the body before the owner in the customer routes', function (string $method, string $uri, string $kind, string $whose) {
    $owner = customer();
    $id = match ($whose) {
        'own' => recordOf($kind, $owner),
        'another customer' => recordOf($kind, customer()),
        'unknown' => unknownId(),
    };

    $this->actingAs($owner)->json($method, customerRecordUri($uri, $id), [])->assertUnprocessable();
})->with([
    ['POST', 'orders/{id}/payment', 'payable order'],
    ['PUT', 'account/addresses/{id}', 'address'],
])->with(['own', 'another customer', 'unknown']);

it('deletes an own address without reading it first', function () {
    $owner = customer();
    $address = addressOf($owner);
    $this->actingAs($owner);

    $response = null;
    $queries = queriesDuring(function () use (&$response, $address) {
        $response = $this->deleteJson("/api/account/addresses/{$address->id}");
    });

    $response->assertNoContent();

    expect($queries)->toHaveCount(1)
        ->and($queries[0])->toStartWith('delete from "customer_addresses"')
        ->and($queries[0])->toContain('"customer_id"');
});

it('keeps the query count of the owner reads and updates', function (string $route, int $expected) {
    $owner = customer();
    $this->actingAs($owner);
    [$method, $uri, $id, $body] = match ($route) {
        'show order' => ['GET', 'orders/{id}', recordOf('order', $owner), []],
        'pay order' => ['POST', 'orders/{id}/payment', recordOf('payable order', $owner), ['card_token' => 'fake_card_approved']],
        'update address' => ['PUT', 'account/addresses/{id}', recordOf('address', $owner), addressPayload(['number' => '2000'])],
    };

    $response = null;
    $queries = queriesDuring(function () use (&$response, $method, $uri, $id, $body) {
        $response = $this->json($method, customerRecordUri($uri, $id), $body);
    });

    expect($response->isSuccessful())->toBeTrue()
        ->and($queries)->toHaveCount($expected, implode("\n", $queries));

    // The approved payment records its PaymentApproved in the outbox, in the same transaction.
    if ($route === 'pay order') {
        expect($queries[3])->toStartWith('insert into "outbox_messages"');
    }
})->with([
    ['show order', 2],
    ['pay order', 4],
    ['update address', 2],
]);
