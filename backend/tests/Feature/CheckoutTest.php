<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Persistence\Models\Order;
use Modules\Ordering\Persistence\Models\OrderItem;

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

    expect(stockOf($mouse)->quantity)->toBe(8)
        ->and(stockOf($keyboard)->quantity)->toBe(0);

    $order = Order::query()->with('items')->sole();
    expect($order->customer_id)->toBe($user->id)
        ->and($order->items->firstWhere('product_id', $mouse->id))
        ->product_name->toBe('Mouse')
        ->unit_price_cents->toBe(9990)
        ->subtotal_cents->toBe(19980)
        ->and($order->items->firstWhere('product_id', $keyboard->id))
        ->unit_price_cents->toBe(25000)
        ->subtotal_cents->toBe(75000);

    expect(outboxMessages('ordering.order_placed')->sole()->payload)->toBe(['order_id' => $order->id]);
});

it('announces the placed order by id', function () {
    $product = productWithStock(5);
    $this->actingAs(customer());

    $orderId = checkout([['product_id' => $product->id, 'quantity' => 1]])->assertCreated()->json('data.id');

    $messages = outboxMessages('ordering.order_placed');
    expect($messages)->toHaveCount(1)
        ->and($messages[0]->payload)->toBe(['order_id' => $orderId])
        ->and($messages[0]->version)->toBe(1)
        ->and($messages[0]->published_at)->toBeNull();
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

    expect(stockOf($product)->quantity)->toBe(1);
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
        ->and(stockOf($ok)->quantity)->toBe(10)
        ->and(stockOf($low)->quantity)->toBe(2)
        ->and(outboxMessages('ordering.order_placed'))->toBeEmpty();
});

it('rejects inactive and out of stock products', function () {
    $inactive = Product::factory()->inactive()->withStock(10)->create();
    $empty = productWithStock(0);
    $unknown = unknownId();
    $this->actingAs(customer());

    expect(checkout([['product_id' => $inactive->id, 'quantity' => 1]])->assertConflict()->json('errors'))
        ->toBe(["items.{$inactive->id}" => ['Produto indisponível.']])
        ->and(checkout([['product_id' => $empty->id, 'quantity' => 1]])->assertConflict()->json('errors'))
        ->toBe(["items.{$empty->id}" => ['Produto indisponível.']])
        ->and(checkout([['product_id' => $unknown, 'quantity' => 1]])->assertConflict()->json('errors'))
        ->toBe(["items.{$unknown}" => ['Produto não encontrado.']]);

    expect(Order::query()->count())->toBe(0)
        ->and(stockOf($inactive)->quantity)->toBe(10)
        ->and(stockOf($empty)->quantity)->toBe(0);
});

it('never lets stock go negative (spec scenario: stock 5, buy 4 then 3)', function () {
    $product = productWithStock(5);

    $this->actingAs(customer());
    checkout([['product_id' => $product->id, 'quantity' => 4]])->assertCreated();

    $this->actingAs(customer());
    checkout([['product_id' => $product->id, 'quantity' => 3]])->assertConflict();

    expect(stockOf($product)->quantity)->toBe(1)
        ->and(Order::query()->count())->toBe(1);
});

it('locks the stock rows with SELECT ... FOR UPDATE', function () {
    $product = productWithStock(5);
    $this->actingAs(customer());

    DB::enableQueryLog();
    checkout([['product_id' => $product->id, 'quantity' => 1]])->assertCreated();

    $queries = collect(DB::getQueryLog())->pluck('query')->values();
    $lockQueries = $queries->filter(fn (string $sql) => str_contains($sql, 'from "stocks"') && str_contains($sql, 'for update'));

    expect($lockQueries)->toHaveCount(1)
        ->and($lockQueries->first())->toContain('order by "product_id"')
        ->and($lockQueries->keys()->first())
        ->toBeLessThan($queries->search(fn (string $sql) => str_contains($sql, 'from "products"')));
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

it('returns the order code on checkout', function () {
    $product = productWithStock(5);
    $this->actingAs(customer());

    $code = checkout([['product_id' => $product->id, 'quantity' => 1]])->assertCreated()->json('data.code');

    expect($code)->toMatch('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/')
        ->and(Order::query()->sole()->code)->toBe($code);
});

/** Orders that already own the given codes, so a draw of one of them collides. */
function ordersOwning(string ...$codes): void
{
    foreach ($codes as $code) {
        Order::factory()->create(['code' => $code]);
    }
}

it('draws the order code again after a collision', function (int $collisions) {
    $taken = array_slice(['AAAAAAAA', 'BBBBBBBB'], 0, $collisions);
    ordersOwning(...$taken);
    $generator = forceOrderCodes([...$taken, 'CCCCCCCC', 'DDDDDDDD']);
    $first = productWithStock(5);
    $second = productWithStock(7);
    $this->actingAs(customer());
    $account = auth('customer')->user();
    $body = [
        'items' => [['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 3]],
        'address_id' => addressOf($account)->id,
    ];
    DB::enableQueryLog();

    $this->postJson('/api/orders', $body)->assertCreated()->assertJsonPath('data.code', 'CCCCCCCC');

    $stockLocks = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'from "stocks"') && str_contains($sql, 'for update'));

    expect($stockLocks)->toHaveCount(1)
        ->and($generator->draws)->toBe($collisions + 1)
        ->and(stockOf($first)->quantity)->toBe(3)
        ->and(stockOf($second)->quantity)->toBe(4)
        ->and(Order::query()->where('customer_id', $account->id)->sole()->code)->toBe('CCCCCCCC')
        ->and(outboxMessages('ordering.order_placed'))->toHaveCount(1);
})->with([1, 2]);

it('fails the whole order after three code collisions', function () {
    ordersOwning('AAAAAAAA', 'BBBBBBBB', 'CCCCCCCC');
    $generator = forceOrderCodes(['AAAAAAAA', 'BBBBBBBB', 'CCCCCCCC', 'DDDDDDDD']);
    $first = productWithStock(5);
    $second = productWithStock(7);
    $this->actingAs(customer());
    $ordersBefore = Order::query()->count();

    checkout([['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 3]])
        ->assertStatus(500);

    expect($generator->draws)->toBe(3)
        ->and(Order::query()->count())->toBe($ordersBefore)
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(stockOf($first)->quantity)->toBe(5)
        ->and(stockOf($second)->quantity)->toBe(7)
        ->and(outboxMessages('ordering.order_placed'))->toBeEmpty();
});

it('keeps the query count of the checkout', function () {
    $first = productWithStock(5);
    $second = productWithStock(7);
    $account = customer();
    $body = [
        'items' => [['product_id' => $first->id, 'quantity' => 1], ['product_id' => $second->id, 'quantity' => 2]],
        'address_id' => addressOf($account)->id,
    ];
    $this->actingAs($account);
    DB::enableQueryLog();

    $this->postJson('/api/orders', $body)->assertCreated();

    $queries = array_column(DB::getQueryLog(), 'query');

    // The outbox adds three inserts: one inventory.stock_changed per product (each right after its UPDATE ... RETURNING,
    // which carries the quantity left and the version back without a read) and OrderPlaced, the last write of the
    // checkout transaction, before the items are read back for the response.
    expect($queries)->toHaveCount(12)
        ->and(array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'insert into "outbox_messages"'))))->toHaveCount(3)
        ->and(array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'update stocks set quantity = quantity - ?, version = version + 1'))))->toHaveCount(2)
        ->and($queries[10])->toStartWith('insert into "outbox_messages"')
        ->and($queries[11])->toStartWith('select * from "order_items"');
});
