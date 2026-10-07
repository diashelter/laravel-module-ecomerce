<?php

use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Models\Payment;
use Illuminate\Support\Facades\Event;

const DELIVERY_ADDRESS_KEYS = ['recipient_name', 'postal_code', 'street', 'number', 'complement', 'district', 'city', 'state'];

/** A customer with an RJ address, a product of R$ 100,00 and the body that buys 2 units of it. */
function rioOrderSetup(): array
{
    $user = customer();
    $address = addressOf($user, [
        'recipient_name' => 'Ana Souza',
        'postal_code' => '20040020',
        'street' => 'Rua da Assembleia',
        'number' => '10',
        'complement' => 'Sala 5',
        'district' => 'Centro',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
    ]);
    $product = productWithStock(10, ['price_cents' => 10000]);

    return [$user, $address, $product, ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'address_id' => $address->id]];
}

it('places an order with the delivery address and the shipping of its state', function () {
    Event::fake([OrderPlaced::class]);
    [$user, $address, , $body] = rioOrderSetup();

    $response = $this->actingAs($user)->postJson('/api/orders', $body)
        ->assertCreated()
        ->assertJsonPath('data.items_total_cents', 20000)
        ->assertJsonPath('data.shipping_cents', 2200)
        ->assertJsonPath('data.total_cents', 22200)
        ->assertJsonPath('data.delivery.business_days', 4)
        ->assertJsonPath('data.delivery.estimated_on', null);

    $delivered = $response->json('data.delivery.address');
    expect(array_keys($delivered))->toBe(DELIVERY_ADDRESS_KEYS)
        ->and($delivered)->toBe([
            'recipient_name' => 'Ana Souza',
            'postal_code' => '20040020',
            'street' => 'Rua da Assembleia',
            'number' => '10',
            'complement' => 'Sala 5',
            'district' => 'Centro',
            'city' => 'Rio de Janeiro',
            'state' => 'RJ',
        ]);
});

it('stores the delivery copy and the shipping on the order row', function () {
    Event::fake([OrderPlaced::class]);
    [$user, $address, , $body] = rioOrderSetup();

    $this->actingAs($user)->postJson('/api/orders', $body)->assertCreated();

    $order = Order::query()->sole();
    expect($order->shipping_cents)->toBe(2200)
        ->and($order->delivery_business_days)->toBe(4)
        ->and($order->total_cents)->toBe(22200)
        ->and($order->estimated_delivery_on)->toBeNull()
        ->and($order->delivery_recipient_name)->toBe($address->recipient_name)
        ->and($order->delivery_postal_code)->toBe($address->postal_code)
        ->and($order->delivery_street)->toBe($address->street)
        ->and($order->delivery_number)->toBe($address->number)
        ->and($order->delivery_complement)->toBe($address->complement)
        ->and($order->delivery_district)->toBe($address->district)
        ->and($order->delivery_city)->toBe($address->city)
        ->and($order->delivery_state->value)->toBe('RJ');
});

it('charges the order total with the shipping', function () {
    [$user, , , $body] = rioOrderSetup();
    $this->actingAs($user);

    $orderId = $this->postJson('/api/orders', $body)->assertCreated()->json('data.id');
    $this->postJson("/api/orders/{$orderId}/payment", ['card_token' => 'fake_card_approved'])->assertAccepted();

    expect(Payment::query()->sole()->amount_cents)->toBe(22200);
});

it('requires a delivery address to place an order', function () {
    Event::fake([OrderPlaced::class]);
    $product = productWithStock(10);

    $response = $this->actingAs(customer())
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
        ->assertUnprocessable();

    expect($response->json('errors.address_id'))->toBe(['Escolha um endereço de entrega.'])
        ->and(Order::query()->count())->toBe(0)
        ->and($product->stock->fresh()->quantity)->toBe(10);
});

it('refuses a delivery address that is not in the customer address book', function (string $kind) {
    Event::fake([OrderPlaced::class]);
    $product = productWithStock(10);
    $addressId = $kind === 'another customer' ? addressOf(customer())->id : 999999;

    $response = $this->actingAs(customer())
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'address_id' => $addressId])
        ->assertUnprocessable();

    expect($response->json('errors.address_id'))->toBe(['Endereço de entrega não encontrado.'])
        ->and(Order::query()->count())->toBe(0)
        ->and($product->stock->fresh()->quantity)->toBe(10);
})->with(['another customer', 'unknown id']);

it('ignores shipping and totals sent by the client', function () {
    Event::fake([OrderPlaced::class]);
    [$user, , , $body] = rioOrderSetup();

    $this->actingAs($user)->postJson('/api/orders', [...$body, 'shipping_cents' => 0, 'total_cents' => 1, 'delivery_state' => 'SP'])
        ->assertCreated();

    $order = Order::query()->sole();
    expect($order->shipping_cents)->toBe(2200)
        ->and($order->delivery_state->value)->toBe('RJ')
        ->and($order->total_cents)->toBe(22200);
});

it('keeps the delivery copy when the address changes later', function (string $change) {
    Event::fake([OrderPlaced::class]);
    [$user, $address, , $body] = rioOrderSetup();
    $this->actingAs($user);
    $orderId = $this->postJson('/api/orders', $body)->assertCreated()->json('data.id');
    $original = $this->getJson("/api/orders/{$orderId}")->json('data.delivery.address');

    $change === 'edit'
        ? $this->putJson("/api/account/addresses/{$address->id}", addressPayload(['state' => 'SP', 'city' => 'São Paulo']))->assertOk()
        : $this->deleteJson("/api/account/addresses/{$address->id}")->assertNoContent();

    $this->getJson("/api/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.delivery.address', $original)
        ->assertJsonPath('data.shipping_cents', 2200)
        ->assertJsonPath('data.delivery.business_days', 4);
    expect($original['state'])->toBe('RJ');
})->with(['edit', 'delete']);

it('throttles order placement after 20 requests per minute', function () {
    Event::fake([OrderPlaced::class]);
    $user = customer();
    $address = addressOf($user);
    $product = productWithStock(100);
    $body = ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'address_id' => $address->id];
    $this->actingAs($user);

    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/orders', $body)->assertCreated();
    }

    $this->postJson('/api/orders', $body)->assertStatus(429);
});

it('answers 404 to an unknown order', function (string $area, string $uri) {
    Order::factory()->create();
    $this->actingAs($area === 'customer' ? customer() : admin());

    $this->getJson($uri)->assertNotFound();
})->with([
    'store' => ['customer', '/api/orders/999999'],
    'admin' => ['staff', '/api/admin/orders/999999'],
]);
