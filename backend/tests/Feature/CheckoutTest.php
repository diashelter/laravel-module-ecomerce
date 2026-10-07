<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => Event::fake([OrderPlaced::class]));

function checkout(array $items)
{
    // The delivery address is one of the signed-in customer's own (a guest has none to send).
    $account = auth('customer')->user();

    return test()->postJson('/api/orders', ['items' => $items, 'address_id' => $account === null ? null : addressOf($account)->id]);
}

it('places an order with every value in cents, decrements stock and stores a snapshot of the items', function () {
    $user = customer();
    $mouse = productWithStock(10, ['name' => 'Mouse', 'price_cents' => 9990]);
    $keyboard = productWithStock(3, ['name' => 'Teclado', 'price_cents' => 25000]);

    $response = $this->actingAs($user)->postJson('/api/orders', [
        'items' => [
            // Prices/totals sent by the client are ignored.
            ['product_id' => $mouse->id, 'quantity' => 2, 'unit_price' => 1, 'unit_price_cents' => 1],
            ['product_id' => $keyboard->id, 'quantity' => 3],
        ],
        'address_id' => addressOf($user)->id,
        'total' => 100,
        'total_cents' => 1,
    ]);

    // The address is in SP, so the shipping is R$ 15,00 on top of the items.
    $response->assertCreated()
        ->assertJsonPath('data.status', 'placed')
        ->assertJsonPath('data.items_total_cents', 94980)
        ->assertJsonPath('data.shipping_cents', 1500)
        ->assertJsonPath('data.total_cents', 96480)
        ->assertJsonCount(2, 'data.items');

    expect($mouse->stock->fresh()->quantity)->toBe(8)
        ->and($keyboard->stock->fresh()->quantity)->toBe(0);

    $order = Order::query()->with('items')->sole();
    expect($order->customer_id)->toBe($user->id)
        ->and($order->items->firstWhere('product_id', $mouse->id))
        ->product_name->toBe('Mouse')
        ->unit_price_cents->toBe(9990)
        ->subtotal_cents->toBe(19980)
        ->and($order->items->firstWhere('product_id', $keyboard->id))
        ->unit_price_cents->toBe(25000)
        ->subtotal_cents->toBe(75000);

    Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->is($order));
});

it('keeps the historical snapshot when the product changes later', function () {
    $product = productWithStock(5, ['name' => 'Old name', 'price_cents' => 1000]);
    $this->actingAs(customer());
    checkout([['product_id' => $product->id, 'quantity' => 1]])->assertCreated();

    $product->update(['name' => 'New name', 'price_cents' => 9900]);

    $item = Order::query()->sole()->items()->sole();
    expect($item->product_name)->toBe('Old name')->and($item->unit_price_cents)->toBe(1000);
});

it('stores an order total equal to the items plus the shipping', function () {
    $first = productWithStock(10, ['price_cents' => 1990]);
    $second = productWithStock(10, ['price_cents' => 350]);
    $third = productWithStock(10, ['price_cents' => 12000]);
    $this->actingAs(customer());

    checkout([
        ['product_id' => $first->id, 'quantity' => 3],
        ['product_id' => $second->id, 'quantity' => 2],
        ['product_id' => $third->id, 'quantity' => 1],
    ])->assertCreated();

    // The address is in SP: R$ 15,00 of shipping.
    $order = Order::query()->sole();
    expect($order->shipping_cents)->toBe(1500)
        ->and($order->total_cents)->toBe(3 * 1990 + 2 * 350 + 12000 + 1500)
        ->and($order->total_cents)->toBe((int) $order->items()->sum('subtotal_cents') + $order->shipping_cents)
        ->and($order->items->every(fn ($item) => $item->subtotal_cents === $item->unit_price_cents * $item->quantity))->toBeTrue();
});

it('merges repeated products in the same cart', function () {
    $product = productWithStock(5, ['price_cents' => 1000]);
    $this->actingAs(customer());

    checkout([
        ['product_id' => $product->id, 'quantity' => 2],
        ['product_id' => $product->id, 'quantity' => 2],
    ])->assertCreated()->assertJsonPath('data.items.0.quantity', 4);

    expect($product->stock->fresh()->quantity)->toBe(1);
});

it('returns 409 and changes nothing when stock is insufficient', function () {
    $ok = productWithStock(10);
    $low = productWithStock(2);
    $this->actingAs(customer());

    $response = checkout([
        ['product_id' => $ok->id, 'quantity' => 1],
        ['product_id' => $low->id, 'quantity' => 3],
    ])
        ->assertConflict()
        ->assertJsonPath('code', 'INSUFFICIENT_STOCK')
        ->assertJsonPath('message', 'Estoque insuficiente para um ou mais produtos.');

    expect($response->json('errors'))->toBe(["items.{$low->id}" => ['Estoque insuficiente. Disponível: 2.']]);

    // The whole transaction was rolled back: no order and no stock change.
    expect(Order::query()->count())->toBe(0)
        ->and($ok->stock->fresh()->quantity)->toBe(10)
        ->and($low->stock->fresh()->quantity)->toBe(2);
    Event::assertNotDispatched(OrderPlaced::class);
});

it('rejects inactive and out of stock products', function () {
    $inactive = Product::factory()->inactive()->withStock(10)->create();
    $empty = productWithStock(0);
    $this->actingAs(customer());

    checkout([['product_id' => $inactive->id, 'quantity' => 1]])->assertConflict();
    checkout([['product_id' => $empty->id, 'quantity' => 1]])->assertConflict();
    checkout([['product_id' => 999999, 'quantity' => 1]])->assertConflict();

    expect(Order::query()->count())->toBe(0);
});

it('never lets stock go negative (spec scenario: stock 5, buy 4 then 3)', function () {
    $product = productWithStock(5);

    $this->actingAs(customer());
    checkout([['product_id' => $product->id, 'quantity' => 4]])->assertCreated();

    $this->actingAs(customer());
    checkout([['product_id' => $product->id, 'quantity' => 3]])->assertConflict();

    expect($product->stock->fresh()->quantity)->toBe(1)
        ->and(Order::query()->count())->toBe(1);
});

it('locks the stock rows with SELECT ... FOR UPDATE', function () {
    $product = productWithStock(5);
    $this->actingAs(customer());

    DB::enableQueryLog();
    checkout([['product_id' => $product->id, 'quantity' => 1]])->assertCreated();

    $lockQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'from "stocks"') && str_contains($sql, 'for update'));

    expect($lockQueries)->toHaveCount(1);
});

it('is protected by a database check constraint as a last line of defense', function () {
    $product = productWithStock(1);

    expect(fn () => DB::table('stocks')->where('product_id', $product->id)->update(['quantity' => -1]))
        ->toThrow(QueryException::class);
});

it('requires authentication and valid items', function () {
    checkout([['product_id' => 1, 'quantity' => 1]])->assertUnauthorized();

    $this->actingAs(customer());
    $this->postJson('/api/orders', ['items' => []])->assertUnprocessable()->assertJsonValidationErrors('items');
});

it('lists only the orders of the authenticated customer', function () {
    $user = customer();
    Order::factory()->count(2)->for($user, 'customer')->create();
    Order::factory()->create();

    $this->actingAs($user)->getJson('/api/orders')->assertOk()->assertJsonCount(2, 'data');
});

it('shows an order with items and timeline', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::PaymentApproved)->create();

    $this->actingAs($user)->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.status_label', 'Pagamento aprovado')
        ->assertJsonPath('data.timeline.2.completed', true)
        ->assertJsonPath('data.timeline.3.completed', false);
});

it('stores the customer id on the placed order', function () {
    $product = productWithStock(5);
    $account = customer();

    $this->actingAs($account)
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'address_id' => addressOf($account)->id])
        ->assertCreated();

    expect(Order::query()->sole()->customer_id)->toBe($account->id);
});
