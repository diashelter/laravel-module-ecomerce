<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Shared\Support\RecordId;
use Illuminate\Support\Facades\Route;

const CANONICAL_ID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

const UPPERCASE_ID = '0199E3B0-4C2A-7D10-9A3B-5F6E7D8C9B0A';

/**
 * Collects the dot path of every `id` and `*_id` key of a decoded JSON body, and fails when
 * one of them is not a canonical UUID string.
 *
 * @return list<string>
 */
function idPathsIn(mixed $json, string $path = ''): array
{
    $found = [];

    if (! is_array($json)) {
        return $found;
    }

    foreach ($json as $key => $value) {
        $here = $path === '' ? (string) $key : "{$path}.{$key}";

        if (is_string($key) && ($key === 'id' || str_ends_with($key, '_id'))) {
            expect($value)->toBeString("{$here} is not a string")
                ->and($value)->toMatch(CANONICAL_ID);
            $found[] = $here;
        }

        array_push($found, ...idPathsIn($value, $here));
    }

    return $found;
}

it('returns every id as a uuid string', function (Closure $scenario) {
    [$response, $paths] = $scenario($this);

    $response->assertSuccessful();
    $found = idPathsIn($response->json());

    expect($found)->not->toBeEmpty();

    foreach ($paths as $path) {
        expect($found)->toContain($path);
    }
})->with([
    'categories' => [function ($test) {
        Category::factory()->create();

        return [$test->getJson('/api/categories'), ['data.0.id']];
    }],
    'admin product' => [function ($test) {
        $product = productWithStock(4);
        $product->categories()->attach(Category::factory()->create());

        return [$test->actingAs(admin())->getJson("/api/admin/products/{$product->id}"), ['data.id', 'data.stock.id', 'data.categories.0.id']];
    }],
    'addresses' => [function ($test) {
        $account = customer();
        addressOf($account);

        return [$test->actingAs($account)->getJson('/api/account/addresses'), ['data.0.id']];
    }],
    'account' => [function ($test) {
        $account = customer();
        Order::factory()->for($account, 'customer')->create();

        return [$test->actingAs($account)->getJson('/api/account'), ['data.customer.id', 'data.last_order.id', 'data.recent_orders.0.id']];
    }],
    'admin customer' => [function ($test) {
        $account = customer();
        Order::factory()->for($account, 'customer')->create();

        return [$test->actingAs(admin())->getJson("/api/admin/customers/{$account->id}"), ['data.id', 'data.orders.0.id']];
    }],
    'auth me' => [fn ($test) => [$test->actingAs(customer())->getJson('/api/auth/me'), ['data.id']]],
    'staff member' => [function ($test) {
        $member = support();

        return [$test->actingAs(admin())->getJson("/api/admin/users/{$member->id}"), ['data.id']];
    }],
    'stocks' => [function ($test) {
        productWithStock(2);

        return [$test->actingAs(admin())->getJson('/api/admin/stocks'), ['data.0.id', 'data.0.product.id']];
    }],
    'admin order' => [function ($test) {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create();

        return [$test->actingAs(admin())->getJson("/api/admin/orders/{$order->id}"), ['data.id', 'data.customer.id', 'data.items.0.id', 'data.items.0.product_id']];
    }],
    'cart validation' => [function ($test) {
        $product = productWithStock(3);

        return [$test->postJson('/api/cart/validate', ['items' => [['product_id' => $product->id, 'quantity' => 1]]]), ['data.items.0.product_id']];
    }],
    'payment' => [function ($test) {
        $account = customer();
        $order = Order::factory()->for($account, 'customer')->status(OrderStatus::AwaitingPayment)->create();

        return [$test->actingAs($account)->postJson("/api/orders/{$order->id}/payment", ['card_token' => 'fake_card_approved']), ['data.id', 'data.order_id']];
    }],
]);

dataset('malformed body ids', [
    'integer' => [1],
    'numeric string' => ['1'],
    'word' => ['abc'],
    'uppercase uuid' => [UPPERCASE_ID],
]);

it('refuses a malformed product id in the cart body', function (string $route, mixed $badId) {
    $account = customer();
    $address = addressOf($account);
    productWithStock(3);
    $this->actingAs($account);
    $body = ['items' => [['product_id' => $badId, 'quantity' => 1]]];

    if ($route === '/api/orders') {
        $body['address_id'] = $address->id;
    }

    $response = null;
    $queries = queriesDuring(function () use (&$response, $route, $body) {
        $response = $this->postJson($route, $body);
    });

    $response->assertUnprocessable();

    expect(array_key_exists('items.0.product_id', $response->json('errors')))->toBeTrue()
        ->and(array_filter($queries, fn (string $sql) => str_contains($sql, '"products"') || str_contains($sql, '"stocks"')))->toBeEmpty();
})->with(['/api/cart/validate', '/api/orders'])->with('malformed body ids');

it('refuses a malformed address id before touching the stock', function (mixed $badId) {
    $account = customer();
    $product = productWithStock(3);
    $this->actingAs($account);

    $response = null;
    $queries = queriesDuring(function () use (&$response, $product, $badId) {
        $response = $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'address_id' => $badId]);
    });

    $response->assertUnprocessable();

    expect(array_key_exists('address_id', $response->json('errors')))->toBeTrue()
        ->and(array_filter($queries, fn (string $sql) => str_contains($sql, 'for update')))->toBeEmpty()
        ->and(stockOf($product)->quantity)->toBe(3);
})->with('malformed body ids');

it('refuses a malformed category id without querying the categories', function (string $method, mixed $badId) {
    $this->actingAs(admin());
    $product = productWithStock(3);
    $payload = productPayload(['category_ids' => [$badId]]);
    $uri = $method === 'POST' ? '/api/admin/products' : "/api/admin/products/{$product->id}";

    $response = null;
    $queries = queriesDuring(function () use (&$response, $method, $uri, $payload) {
        $response = $this->json($method, $uri, $payload);
    });

    $response->assertUnprocessable();

    expect(array_key_exists('category_ids.0', $response->json('errors')))->toBeTrue()
        ->and(array_filter($queries, fn (string $sql) => str_contains($sql, '"categories"')))->toBeEmpty();
})->with(['POST', 'PUT'])->with('malformed body ids');

it('refuses an unknown category id on the product', function () {
    $this->actingAs(admin());
    $before = Product::query()->count();

    $response = $this->postJson('/api/admin/products', productPayload(['category_ids' => [unknownId()]]))->assertUnprocessable();

    expect(array_key_exists('category_ids.0', $response->json('errors')))->toBeTrue()
        ->and(Product::query()->count())->toBe($before);
});

it('answers 404 without a query for a malformed id in the path', function (string $malformed) {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/') && $route->parameterNames() !== [])
        ->values();

    expect($routes->count())->toBeGreaterThanOrEqual(19)
        ->and($routes->flatMap->parameterNames()->unique()->all())->toContain('product', 'category', 'stock', 'customer', 'user', 'order', 'address');

    foreach ($routes as $route) {
        $uri = '/'.preg_replace('/\{[^}]+\}/', $malformed, $route->uri());
        $method = $route->methods()[0];
        $response = null;

        $queries = queriesDuring(function () use (&$response, $method, $uri) {
            $response = $this->json($method, $uri);
        });

        assertApiError($response->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');
        expect($queries)->toBeEmpty("{$method} {$uri} ran a query");
    }
})->with(['1', 'abc', UPPERCASE_ID]);

it('keeps the canonical id expression in the shared kernel', function () {
    expect(RecordId::PATTERN)->toBe('[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}')
        ->and(RecordId::isCanonical('0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a'))->toBeTrue()
        ->and(RecordId::isCanonical(UPPERCASE_ID))->toBeFalse()
        ->and(RecordId::isCanonical('1'))->toBeFalse()
        ->and(RecordId::isCanonical(1))->toBeFalse()
        ->and(RecordId::isCanonical("0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a\n"))->toBeFalse();
});
