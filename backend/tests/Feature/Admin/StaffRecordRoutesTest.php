<?php

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Ordering\Persistence\Models\Order;
use Modules\Ordering\Persistence\Models\OrderItem;
use Modules\Payment\Persistence\Models\Payment;

/** The 15 routes of the staff and the showcase that point to a record: [method, uri, kind of record]. */
dataset('staff and showcase routes', [
    'show product' => ['GET', 'api/products/{product}', 'product'],
    'show admin product' => ['GET', 'api/admin/products/{product}', 'product'],
    'update product' => ['PUT', 'api/admin/products/{product}', 'product'],
    'delete product' => ['DELETE', 'api/admin/products/{product}', 'product'],
    'update product status' => ['PATCH', 'api/admin/products/{product}/status', 'product'],
    'show category' => ['GET', 'api/admin/categories/{category}', 'category'],
    'update category' => ['PUT', 'api/admin/categories/{category}', 'category'],
    'delete category' => ['DELETE', 'api/admin/categories/{category}', 'category'],
    'update stock' => ['PUT', 'api/admin/stocks/{stock}', 'stock'],
    'show customer' => ['GET', 'api/admin/customers/{customer}', 'customer'],
    'update customer' => ['PUT', 'api/admin/customers/{customer}', 'customer'],
    'show staff member' => ['GET', 'api/admin/users/{user}', 'user'],
    'update staff member' => ['PUT', 'api/admin/users/{user}', 'user'],
    'delete staff member' => ['DELETE', 'api/admin/users/{user}', 'user'],
    'show admin order' => ['GET', 'api/admin/orders/{order}', 'order'],
]);

/** A valid body for the write routes (the admin product body creates its categories). */
function validBodyOf(string $method, string $uri, string $kind): array
{
    return match (true) {
        ! in_array($method, ['PUT', 'PATCH'], true) => [],
        str_ends_with($uri, '/status') => ['status' => 'inactive'],
        $kind === 'product' => productPayload(),
        $kind === 'category' => ['name' => 'Periféricos'],
        $kind === 'stock' => ['operation' => 'increase', 'quantity' => 5],
        $kind === 'customer' => ['name' => 'Bruna Lima', 'email' => 'bruna@example.com'],
        $kind === 'user' => ['name' => 'Carla Dias', 'email' => 'carla@example.com', 'role' => 'support'],
    };
}

/** The id of a record of that kind that exists. */
function existingIdOf(string $kind): string
{
    return match ($kind) {
        'product' => productWithStock(3)->id,
        'category' => Category::factory()->create()->id,
        'stock' => stockOf(productWithStock(3))->id,
        'customer' => customer()->id,
        'user' => support()->id,
        'order' => Order::factory()->create()->id,
    };
}

function staffRouteNamed(string $method, string $uri): Illuminate\Routing\Route
{
    return collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true));
}

/** Some rows in all 9 business tables, so an unwanted change shows in the counts. */
function seedBusinessTables(): void
{
    $account = customer();
    addressOf($account);
    $order = Order::factory()->for($account, 'customer')->create();
    OrderItem::factory()->forProduct(productWithStock(2), 1)->for($order)->create();
    Payment::factory()->create(['order_id' => $order->id]);
    support();
}

function businessTableCounts(): array
{
    return collect(['users', 'customers', 'categories', 'products', 'stocks', 'orders', 'order_items', 'payments', 'customer_addresses'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
        ->all();
}

it('receives the record id as a string in the staff and showcase routes', function (string $method, string $uri) {
    $route = staffRouteNamed($method, $uri);
    $parameters = $route->getControllerClass() === null ? [] : (new ReflectionMethod($route->getControllerClass(), $route->getActionMethod()))->getParameters();
    $named = array_values(array_filter($parameters, fn (ReflectionParameter $parameter) => in_array($parameter->getName(), $route->parameterNames(), true)));

    expect($named)->toHaveCount(1);

    $type = $named[0]->getType();

    expect($type instanceof ReflectionNamedType && $type->getName() === 'string' && ! $type->allowsNull())->toBeTrue("{$method} {$uri} does not take the id as a string");

    foreach ($parameters as $parameter) {
        $type = $parameter->getType();

        expect($type instanceof ReflectionNamedType && is_a($type->getName(), UrlRoutable::class, true))
            ->toBeFalse("{$method} {$uri} binds \${$parameter->getName()} by implicit binding");
    }
})->with('staff and showcase routes');

it('answers 404 for an unknown id in the staff and showcase routes', function (string $method, string $uri, string $kind) {
    $this->actingAs(admin());
    $body = validBodyOf($method, $uri, $kind);
    seedBusinessTables();
    $before = businessTableCounts();

    $response = $this->json($method, '/'.str_replace('{'.$kind.'}', unknownId(), $uri), $body);

    assertApiError($response->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');
    expect(businessTableCounts())->toBe($before);
})->with('staff and showcase routes');

it('validates the body before the record in the staff routes', function (string $method, string $uri, string $kind, bool $existing) {
    $this->actingAs(admin());
    $id = $existing ? existingIdOf($kind) : unknownId();

    $this->json($method, '/'.str_replace('{'.$kind.'}', $id, $uri), [])->assertUnprocessable();
})->with([
    ['PUT', 'api/admin/products/{product}', 'product'],
    ['PATCH', 'api/admin/products/{product}/status', 'product'],
    ['PUT', 'api/admin/categories/{category}', 'category'],
    ['PUT', 'api/admin/stocks/{stock}', 'stock'],
    ['PUT', 'api/admin/customers/{customer}', 'customer'],
    ['PUT', 'api/admin/users/{user}', 'user'],
])->with([
    'existing id' => true,
    'unknown id' => false,
]);

it('adjusts the stock reading it once with a lock', function () {
    $stock = stockOf(productWithStock(10));
    $this->actingAs(admin());

    $response = null;
    $queries = queriesDuring(function () use (&$response, $stock) {
        $response = $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'increase', 'quantity' => 5]);
    });

    $response->assertOk()->assertJsonPath('data.quantity', 15);

    $reads = array_filter($queries, fn (string $sql) => str_starts_with($sql, 'select') && str_contains($sql, 'from "stocks"'));

    // The lock read, the update, the inventory.stock_changed insert and the product the response shows.
    expect($queries)->toHaveCount(4)
        ->and($reads)->toHaveCount(1)
        ->and(array_values($reads)[0])->toContain('for update');
});

it('deletes a product reading only its version, under a lock', function () {
    $product = productWithStock(4);
    $this->actingAs(admin());

    $response = null;
    $queries = queriesDuring(function () use (&$response, $product) {
        $response = $this->deleteJson("/api/admin/products/{$product->id}");
    });

    $response->assertNoContent();

    // The order history check, the lock read (the stored version), the delete and the catalog.product_deleted insert.
    $reads = array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'select') && str_contains($sql, 'from "products"')));

    expect($queries)->toHaveCount(4)
        ->and($reads)->toHaveCount(1)
        ->and($reads[0])->toEndWith('for update')
        ->and(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

it('deletes a category reading only its version, under a lock', function () {
    $category = Category::factory()->create();
    $this->actingAs(admin());

    $response = null;
    $queries = queriesDuring(function () use (&$response, $category) {
        $response = $this->deleteJson("/api/admin/categories/{$category->id}");
    });

    $response->assertNoContent();

    // The products check, the lock read (the stored version), the delete and the catalog.category_deleted insert.
    $reads = array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'select * from "categories"')));

    expect($queries)->toHaveCount(4)
        ->and($reads)->toHaveCount(1)
        ->and($reads[0])->toEndWith('for update')
        ->and(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('refuses to delete the own account without querying the users', function () {
    $boss = admin();
    $this->actingAs($boss);

    $response = null;
    $queries = queriesDuring(function () use (&$response, $boss) {
        $response = $this->deleteJson("/api/admin/users/{$boss->id}");
    });

    assertApiError($response->assertStatus(409), 'BUSINESS_RULE_VIOLATION', 'Você não pode remover a própria conta.');

    expect(array_filter($queries, fn (string $sql) => str_contains($sql, '"users"')))->toBeEmpty()
        ->and($boss->fresh())->not->toBeNull();
});

it('deletes a staff member without reading it first', function () {
    $member = support();
    $this->actingAs(admin());

    $response = null;
    $queries = queriesDuring(function () use (&$response, $member) {
        $response = $this->deleteJson("/api/admin/users/{$member->id}");
    });

    $response->assertNoContent();

    expect($queries)->toHaveCount(1)
        ->and($queries[0])->toStartWith('delete from "users"')
        ->and($member->fresh())->toBeNull();
});

it('reads the customer once when the admin edits it', function () {
    $account = customer();
    Order::factory()->count(2)->for($account, 'customer')->create();
    $this->actingAs(admin());

    $response = null;
    $queries = queriesDuring(function () use (&$response, $account) {
        $response = $this->putJson("/api/admin/customers/{$account->id}", ['name' => 'Bruna Lima', 'email' => 'bruna@example.com']);
    });

    $response->assertOk()->assertJsonPath('data.orders_count', 2);

    expect($queries)->toHaveCount(4)
        ->and(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'select * from "customers"')))->toHaveCount(1);
});

it('keeps the query count of the staff and showcase reads and updates', function (string $method, string $uri, string $kind, int $expected) {
    $this->actingAs(admin());
    $id = match ($kind) {
        'product' => (function () {
            $product = productWithStock(5);
            $product->categories()->attach(Category::factory()->count(2)->create());

            return $product->id;
        })(),
        'category' => Category::factory()->create()->id,
        'customer' => (function () {
            $account = customer();
            Order::factory()->count(2)->for($account, 'customer')->create();

            return $account->id;
        })(),
        default => existingIdOf($kind),
    };
    $body = validBodyOf($method, $uri, $kind);

    $response = null;
    $queries = queriesDuring(function () use (&$response, $method, $uri, $kind, $id, $body) {
        $response = $this->json($method, '/'.str_replace('{'.$kind.'}', $id, $uri), $body);
    });

    $response->assertOk();

    expect($queries)->toHaveCount($expected, implode("\n", $queries));
})->with([
    'show product' => ['GET', 'api/products/{product}', 'product', 3],
    'show admin product' => ['GET', 'api/admin/products/{product}', 'product', 3],
    'update product' => ['PUT', 'api/admin/products/{product}', 'product', 12],
    'update product status' => ['PATCH', 'api/admin/products/{product}/status', 'product', 6],
    'show category' => ['GET', 'api/admin/categories/{category}', 'category', 2],
    'update category' => ['PUT', 'api/admin/categories/{category}', 'category', 5],
    'show customer' => ['GET', 'api/admin/customers/{customer}', 'customer', 3],
    'show admin order' => ['GET', 'api/admin/orders/{order}', 'order', 3],
    'show staff member' => ['GET', 'api/admin/users/{user}', 'user', 1],
    'update staff member' => ['PUT', 'api/admin/users/{user}', 'user', 3],
]);
