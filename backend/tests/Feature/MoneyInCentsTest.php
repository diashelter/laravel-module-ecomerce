<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Payment\Events\PaymentApproved;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

dataset('money columns', [
    'products.price_cents' => ['products', 'price_cents'],
    'orders.total_cents' => ['orders', 'total_cents'],
    'orders.shipping_cents' => ['orders', 'shipping_cents'],
    'order_items.unit_price_cents' => ['order_items', 'unit_price_cents'],
    'order_items.subtotal_cents' => ['order_items', 'subtotal_cents'],
]);

function assertNoLegacyMoneyKeys(array $payload): void
{
    foreach (['price', 'unit_price', 'subtotal', 'total'] as $legacyKey) {
        expect($payload)->not->toHaveKey($legacyKey);
    }
}

it('stores every money column as bigint not null', function (string $table, string $column) {
    $definition = DB::selectOne(
        'select data_type, is_nullable from information_schema.columns where table_name = ? and column_name = ?',
        [$table, $column],
    );

    expect($definition->data_type)->toBe('bigint')
        ->and($definition->is_nullable)->toBe('NO');
})->with('money columns');

it('rejects a negative value in every money column', function (string $table, string $column) {
    $row = match ($table) {
        'products' => Product::factory()->create(),
        'orders' => Order::factory()->create(),
        'order_items' => OrderItem::factory()->create(),
    };
    $original = DB::table($table)->where('id', $row->id)->value($column);

    // The nested transaction is a savepoint: the failed statement does not abort the test transaction.
    expect(fn () => DB::transaction(
        fn () => DB::table($table)->where('id', $row->id)->update([$column => -1])
    ))->toThrow(QueryException::class, "{$table}_{$column}_non_negative");

    expect(DB::table($table)->where('id', $row->id)->value($column))->toBe($original);
})->with('money columns');

describe('admin products', function () {
    beforeEach(fn () => $this->actingAs(admin()));

    it('updates the product price in cents', function () {
        $product = productWithStock(1, ['price_cents' => 5000]);

        $this->putJson("/api/admin/products/{$product->id}", productPayload(['price_cents' => 19990]))
            ->assertOk()
            ->assertJsonPath('data.price_cents', 19990);

        expect($product->fresh()->price_cents)->toBe(19990);
    });

    it('rejects an invalid price_cents', function (string $method, array $override) {
        $product = productWithStock(1, ['price_cents' => 5000]);
        $uri = $method === 'post' ? '/api/admin/products' : "/api/admin/products/{$product->id}";
        $payload = productPayload($override);

        if (array_key_exists('price_cents', $override) === false) {
            unset($payload['price_cents']);
        }

        $this->json($method, $uri, $payload)->assertUnprocessable()->assertJsonValidationErrors('price_cents');

        expect($product->fresh()->price_cents)->toBe(5000);
    })->with(function () {
        $invalid = [
            'zero' => ['price_cents' => 0],
            'negative' => ['price_cents' => -1],
            'decimal string' => ['price_cents' => '1299.90'],
            'float' => ['price_cents' => 1299.5],
            'text' => ['price_cents' => 'abc'],
            'above the maximum' => ['price_cents' => 10000000000],
            'legacy price key only' => ['price' => '1299.90'],
        ];

        foreach (['post', 'put'] as $method) {
            foreach ($invalid as $label => $override) {
                yield "{$method} {$label}" => [$method, $override];
            }
        }
    });

    it('explains invalid price_cents in reais', function () {
        $this->postJson('/api/admin/products', productPayload(['price_cents' => 10000000000]))
            ->assertJsonPath('errors.price_cents.0', 'O preço não pode ser maior que R$ 99.999.999,99.');

        $this->postJson('/api/admin/products', productPayload(['price_cents' => 'abc']))
            ->assertJsonPath('errors.price_cents.0', 'O campo preço deve ser um número inteiro.');
    });

    it('accepts the maximum price_cents', function () {
        $this->postJson('/api/admin/products', productPayload(['price_cents' => 9999999999]))
            ->assertCreated()
            ->assertJsonPath('data.price_cents', 9999999999);
    });

    it('exposes price_cents on every admin product route', function () {
        $product = productWithStock(3, ['price_cents' => 129990]);
        $responses = [
            $this->getJson('/api/admin/products')->assertOk()->json('data.0'),
            $this->getJson("/api/admin/products/{$product->id}")->assertOk()->json('data'),
            $this->patchJson("/api/admin/products/{$product->id}/status", ['status' => 'inactive'])->assertOk()->json('data'),
        ];

        foreach ($responses as $payload) {
            expect($payload['price_cents'])->toBe(129990);
            assertNoLegacyMoneyKeys($payload);
        }
    });
});

it('exposes price_cents as an integer on the public catalog', function () {
    $product = productWithStock(3, ['price_cents' => 129990]);

    $list = $this->getJson('/api/products')->assertOk()->json('data.0');
    $show = $this->getJson("/api/products/{$product->id}")->assertOk()->json('data');

    foreach ([$list, $show] as $payload) {
        expect($payload['price_cents'])->toBe(129990);
        assertNoLegacyMoneyKeys($payload);
    }
});

describe('cart validation', function () {
    it('returns null cents for a product that does not exist', function () {
        $this->postJson('/api/cart/validate', ['items' => [['product_id' => 999999, 'quantity' => 2]]])
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price_cents', null)
            ->assertJsonPath('data.items.0.subtotal_cents', null)
            ->assertJsonPath('data.total_cents', 0)
            ->assertJsonPath('data.is_valid', false);
    });

    it('keeps an unavailable line out of total_cents', function () {
        $available = productWithStock(10, ['price_cents' => 1000]);
        $inactive = Product::factory()->inactive()->withStock(10)->create(['price_cents' => 2500]);

        $response = $this->postJson('/api/cart/validate', ['items' => [
            ['product_id' => $available->id, 'quantity' => 1],
            ['product_id' => $inactive->id, 'quantity' => 2],
        ]])->assertOk();

        $lines = collect($response->json('data.items'))->keyBy('product_id');

        expect($lines[$inactive->id]['subtotal_cents'])->toBe(5000)
            ->and($response->json('data.total_cents'))->toBe(1000)
            ->and($response->json('data.is_valid'))->toBeFalse();
    });

    it('ignores money fields sent by the client', function (string $field) {
        $product = productWithStock(10, ['price_cents' => 1990]);

        $this->postJson('/api/cart/validate', [
            'items' => [['product_id' => $product->id, 'quantity' => 3, $field => 1]],
            $field => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price_cents', 1990)
            ->assertJsonPath('data.items.0.subtotal_cents', 5970)
            ->assertJsonPath('data.total_cents', 5970);
    })->with(['price', 'unit_price', 'total', 'unit_price_cents', 'total_cents']);

    it('throttles cart validation at 60 requests per minute', function () {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/cart/validate', ['items' => [['product_id' => 1, 'quantity' => 1]]])->assertOk();
        }

        $this->postJson('/api/cart/validate', ['items' => [['product_id' => 1, 'quantity' => 1]]])
            ->assertStatus(429);
    });
});

it('ignores money fields sent by the client on checkout', function (string $field) {
    Event::fake([OrderPlaced::class]);
    $product = productWithStock(10, ['price_cents' => 1990]);

    $account = customer();

    $this->actingAs($account)->postJson('/api/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 3, $field => 1]],
        'address_id' => addressOf($account)->id,
        $field => 1,
    ])
        ->assertCreated()
        // The address is in SP: R$ 15,00 of shipping on top of the items.
        ->assertJsonPath('data.items_total_cents', 5970)
        ->assertJsonPath('data.total_cents', 5970 + 1500)
        ->assertJsonPath('data.items.0.unit_price_cents', 1990)
        ->assertJsonPath('data.items.0.subtotal_cents', 5970);
})->with(['price', 'unit_price', 'total', 'unit_price_cents', 'total_cents']);

it('exposes order money in cents on every order route', function () {
    Event::fake([OrderPlaced::class, PaymentApproved::class]);
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create(['total_cents' => 94980]);
    OrderItem::factory()->for($order)->create(['unit_price_cents' => 9990, 'quantity' => 2, 'subtotal_cents' => 19980]);

    $assertOrder = function (array $payload) {
        expect($payload['total_cents'])->toBe(94980);
        assertNoLegacyMoneyKeys($payload);

        foreach ($payload['items'] ?? [] as $item) {
            expect($item['unit_price_cents'])->toBe(9990)->and($item['subtotal_cents'])->toBe(19980);
            assertNoLegacyMoneyKeys($item);
        }
    };

    $this->actingAs($user);
    $assertOrder($this->getJson('/api/orders')->assertOk()->json('data.0'));
    $assertOrder($this->getJson("/api/orders/{$order->id}")->assertOk()->assertJsonCount(1, 'data.items')->json('data'));
    // The payment route answers the payment attempt, whose money is the order total in cents.
    $attempt = $this->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved'])->assertAccepted()->json('data');
    expect($attempt['amount_cents'])->toBe(94980);
    assertNoLegacyMoneyKeys($attempt);
    $assertOrder($this->getJson('/api/account')->assertOk()->json('data.last_order'));

    $this->actingAs(admin());
    $assertOrder($this->getJson('/api/admin/orders')->assertOk()->json('data.0'));
    $assertOrder($this->getJson("/api/admin/orders/{$order->id}")->assertOk()->assertJsonCount(1, 'data.items')->json('data'));
    $assertOrder($this->getJson("/api/admin/customers/{$user->id}")->assertOk()->json('data.orders.0'));
});

it('seeds orders whose total_cents is the sum of their items plus the shipping', function () {
    $this->seed();

    foreach (Order::query()->with('items')->get() as $order) {
        expect($order->total_cents)->toBe($order->items->sum('subtotal_cents') + $order->shipping_cents);

        foreach ($order->items as $item) {
            expect($item->subtotal_cents)->toBe($item->unit_price_cents * $item->quantity);
        }
    }
});

dataset('guarded money routes', function () {
    $routes = [
        ['get', '/api/admin/products'],
        ['post', '/api/admin/products'],
        ['get', '/api/admin/products/1'],
        ['put', '/api/admin/products/1'],
        ['patch', '/api/admin/products/1/status'],
        ['post', '/api/orders'],
        ['get', '/api/orders'],
        ['get', '/api/orders/1'],
        ['post', '/api/orders/1/payment'],
        ['get', '/api/admin/orders'],
        ['get', '/api/admin/orders/1'],
        ['get', '/api/account'],
        ['get', '/api/admin/customers/1'],
    ];

    foreach ($routes as [$method, $uri]) {
        yield "{$method} {$uri}" => [$method, $uri];
    }
});

it('keeps 401 for guests on the money routes', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with('guarded money routes');

it('keeps 401 for customer sessions on the admin money routes', function (string $method, string $uri) {
    // Bindings resolve before the admin middleware, so the ids must exist.
    $product = productWithStock(1);
    $order = Order::factory()->create();
    $uri = str_replace(['/products/1', '/orders/1', '/customers/1'], ["/products/{$product->id}", "/orders/{$order->id}", "/customers/{$order->customer_id}"], $uri);

    $this->actingAs(customer())->json($method, $uri)->assertUnauthorized();
})->with([
    ['get', '/api/admin/products'],
    ['post', '/api/admin/products'],
    ['get', '/api/admin/products/1'],
    ['put', '/api/admin/products/1'],
    ['patch', '/api/admin/products/1/status'],
    ['get', '/api/admin/orders'],
    ['get', '/api/admin/orders/1'],
    ['get', '/api/admin/customers/1'],
]);

it('keeps 404 for unknown ids on the money routes', function (string $method, string $uri) {
    // Both areas are signed in, because each route only answers to the session of its own area.
    $this->actingAs(admin())->actingAs(customer());
    $uri = str_replace('/1', '/999999', $uri);

    $this->json($method, $uri)->assertNotFound();
})->with([
    ['get', '/api/products/1'],
    ['get', '/api/admin/products/1'],
    ['put', '/api/admin/products/1'],
    ['patch', '/api/admin/products/1/status'],
    ['get', '/api/orders/1'],
    ['post', '/api/orders/1/payment'],
    ['get', '/api/admin/orders/1'],
    ['get', '/api/admin/customers/1'],
]);

it('keeps 422 for invalid payloads on the money routes', function () {
    $product = productWithStock(1);
    $invalidItems = ['items' => [['product_id' => 'x', 'quantity' => 0]]];

    $this->actingAs(admin())
        ->patchJson("/api/admin/products/{$product->id}/status", ['status' => 'deleted'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->actingAs(customer())->postJson('/api/orders', $invalidItems)->assertUnprocessable();
    $this->postJson('/api/cart/validate', $invalidItems)->assertUnprocessable();
});
