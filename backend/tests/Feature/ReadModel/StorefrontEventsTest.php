<?php

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\DTOs\CreateProductDTO;
use Modules\Catalog\Core\DTOs\ProductDTO;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\UseCases\CreateProductUseCase;
use Modules\Catalog\Core\ValueObjects\CategoryIds;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Inventory\Persistence\Models\Stock;
use Modules\Ordering\Persistence\Models\Order;

/*
 * The writes that change the storefront record the whole part they changed, with the version of the record, in
 * their own transaction (S1 of the storefront read model).
 */

const PRODUCT_EVENT_KEYS = ['category_ids', 'created_at', 'description', 'image_url', 'name', 'price_cents', 'product_id', 'status', 'updated_at', 'version'];

/** @return array<string, mixed> the payload of the only message with the name */
function soleEvent(string $name): array
{
    return outboxMessages($name)->sole()->payload;
}

/** @return list<string> the ids of the product's categories, ascending */
function categoryIdsOf(Product $product): array
{
    $ids = $product->categories()->pluck('categories.id')->all();
    sort($ids);

    return $ids;
}

/** The payload a product event must carry for the row as it is stored now. */
function expectedProductPayload(string $productId): array
{
    $product = Product::query()->findOrFail($productId);

    return [
        'product_id' => $product->id,
        'name' => $product->name,
        'price_cents' => $product->price_cents,
        'description' => $product->description,
        'image_url' => $product->image_url,
        'status' => $product->status->value,
        'category_ids' => categoryIdsOf($product),
        'created_at' => $product->created_at->toIso8601String(),
        'updated_at' => $product->updated_at->toIso8601String(),
        'version' => $product->version,
    ];
}

/** Valid body of `PUT /api/admin/products/{product}` (the stock is not part of it). */
function editBody(array $overrides = []): array
{
    return [...productPayload(), ...$overrides];
}

/** @return array<string, mixed> */
function checkoutBody(array $items): array
{
    $account = auth('customer')->user();

    return ['items' => $items, 'address_id' => addressOf($account)->id];
}

it('records the product creation and the opening stock at version 1', function (string $example) {
    $this->actingAs(admin());

    if ($example === 'no category') {
        $record = app(CreateProductUseCase::class)->execute(new CreateProductDTO(
            new ProductDTO('Monitor 27', 129990, 'Monitor IPS', null, ProductStatus::Active, new CategoryIds),
            7,
        ));
        $productId = $record->id;
    } else {
        $categories = Category::factory()->count($example === 'two categories' ? 2 : 1)->create();
        $body = productPayload([
            'image_url' => $example === 'image url null' ? null : 'https://example.com/monitor.png',
            'category_ids' => $categories->pluck('id')->all(),
        ]);
        $productId = $this->postJson('/api/admin/products', $body)->assertCreated()->json('data.id');
    }

    $product = Product::query()->findOrFail($productId);
    $stock = stockOf($product);
    $payload = soleEvent('catalog.product_created');
    ksort($payload);

    expect(array_keys($payload))->toBe(PRODUCT_EVENT_KEYS)
        ->and($payload)->toEqual(expectedProductPayload($productId))
        ->and($payload['version'])->toBe(1)
        ->and($product->version)->toBe(1)
        ->and($stock->version)->toBe(1)
        ->and(soleEvent('inventory.stock_changed'))->toEqual(['stock_id' => $stock->id, 'product_id' => $productId, 'quantity' => 7, 'version' => 1]);

    if ($example === 'image url null') {
        expect($payload['image_url'])->toBe("https://picsum.photos/seed/product-{$productId}/600/600");
    }

    if ($example === 'no category') {
        expect($payload['category_ids'])->toBe([]);
    }

    if ($example === 'two categories') {
        $ids = $payload['category_ids'];
        expect($ids)->toHaveCount(2)->and($ids)->toBe(collect($ids)->sort()->values()->all());
    }
})->with(['image url null', 'one category', 'no category', 'two categories']);

it('records the whole product at the next version on an edit', function () {
    $this->actingAs(admin());
    [$first, $second, $third] = Category::factory()->count(3)->create()->all();
    $product = productWithStock(4);
    $product->categories()->attach([$first->id]);

    // Every field, then only the categories, then the same body again: versions 2, 3 and 4 in that order.
    $this->putJson("/api/admin/products/{$product->id}", editBody(['name' => 'Novo nome', 'price_cents' => 5500, 'category_ids' => [$second->id, $first->id]]))->assertOk();
    $this->putJson("/api/admin/products/{$product->id}", editBody(['name' => 'Novo nome', 'price_cents' => 5500, 'category_ids' => [$third->id]]))->assertOk();
    $this->putJson("/api/admin/products/{$product->id}", editBody(['name' => 'Novo nome', 'price_cents' => 5500, 'category_ids' => [$third->id]]))->assertOk();

    $messages = outboxMessages('catalog.product_updated');
    $ids = fn (string ...$ids) => collect($ids)->sort()->values()->all();

    expect($messages->pluck('payload.version')->all())->toBe([2, 3, 4])
        ->and($messages[0]->payload['category_ids'])->toBe($ids($first->id, $second->id))
        ->and($messages[0]->payload['name'])->toBe('Novo nome')
        ->and($messages[0]->payload['price_cents'])->toBe(5500)
        ->and($messages[1]->payload['category_ids'])->toBe([$third->id])
        ->and($messages[2]->payload)->toEqual(expectedProductPayload($product->id))
        ->and(array_keys($messages[2]->payload))->toEqualCanonicalizing(PRODUCT_EVENT_KEYS)
        ->and($product->fresh()->version)->toBe(4);
});

it('records the whole product at the next version on a status change', function () {
    $this->actingAs(admin());
    $category = Category::factory()->create();
    $product = productWithStock(4);
    $product->categories()->attach($category);

    $this->patchJson("/api/admin/products/{$product->id}/status", ['status' => 'inactive'])->assertOk();

    $payload = soleEvent('catalog.product_status_changed');
    $row = $product->fresh();

    expect(array_keys($payload))->toEqualCanonicalizing(PRODUCT_EVENT_KEYS)
        ->and($payload)->toEqual(expectedProductPayload($product->id))
        ->and($payload['status'])->toBe('inactive')
        ->and($payload['version'])->toBe(2)
        ->and($payload['name'])->toBe($row->name)
        ->and($payload['price_cents'])->toBe($row->price_cents)
        ->and($payload['description'])->toBe($row->description)
        ->and($payload['image_url'])->toBe($row->image_url)
        ->and($payload['category_ids'])->toBe([$category->id])
        ->and($payload['created_at'])->toBe($row->created_at->toIso8601String());
});

it('records the product deletion at the stored version plus one and no stock change', function () {
    $this->actingAs(admin());
    $product = productWithStock(4);
    DB::table('products')->where('id', $product->id)->update(['version' => 5]);

    $this->deleteJson("/api/admin/products/{$product->id}")->assertNoContent();

    expect(soleEvent('catalog.product_deleted'))->toEqual(['product_id' => $product->id, 'version' => 6])
        ->and(Stock::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(outboxMessages('inventory.stock_changed'))->toBeEmpty();
});

it('records the category creation at version 1 and the rename at the next version', function () {
    $this->actingAs(admin());

    $id = $this->postJson('/api/admin/categories', ['name' => 'Eletrônicos'])->assertCreated()->json('data.id');

    expect(soleEvent('catalog.category_created'))->toEqual(['category_id' => $id, 'name' => 'Eletrônicos', 'slug' => 'eletronicos', 'version' => 1])
        ->and(DB::table('categories')->where('id', $id)->value('version'))->toBe(1);

    $this->putJson("/api/admin/categories/{$id}", ['name' => 'Casa e Jardim'])->assertOk();

    expect(soleEvent('catalog.category_updated'))->toEqual(['category_id' => $id, 'name' => 'Casa e Jardim', 'slug' => 'casa-e-jardim', 'version' => 2])
        ->and(DB::table('categories')->where('id', $id)->value('version'))->toBe(2);
});

it('records the category deletion at the stored version plus one', function () {
    $this->actingAs(admin());
    $category = Category::factory()->create();
    DB::table('categories')->where('id', $category->id)->update(['version' => 3]);

    $this->deleteJson("/api/admin/categories/{$category->id}")->assertNoContent();

    expect(soleEvent('catalog.category_deleted'))->toEqual(['category_id' => $category->id, 'version' => 4]);
});

it('records the stored quantity at the next version on a stock adjustment', function (string $operation, int $units, int $expected) {
    $this->actingAs(admin());
    $stock = stockOf(productWithStock(7));

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => $operation, 'quantity' => $units])
        ->assertOk()->assertJsonPath('data.quantity', $expected);

    expect(soleEvent('inventory.stock_changed'))->toEqual(['stock_id' => $stock->id, 'product_id' => $stock->product_id, 'quantity' => $expected, 'version' => 2])
        ->and($stock->fresh()->version)->toBe(2);
})->with([
    'increase 3 on 7' => ['increase', 3, 10],
    'decrease 7 on 7' => ['decrease', 7, 0],
]);

it('records one stock change per product of the checkout with the quantity left', function () {
    $first = productWithStock(10);
    $second = productWithStock(5);
    $this->actingAs(customer());

    $this->postJson('/api/orders', checkoutBody([
        ['product_id' => $first->id, 'quantity' => 3],
        ['product_id' => $second->id, 'quantity' => 1],
    ]))->assertCreated();

    $changes = outboxMessages('inventory.stock_changed')->keyBy('payload.product_id');

    expect($changes)->toHaveCount(2)
        ->and($changes[$first->id]->payload)->toEqual(['stock_id' => stockOf($first)->id, 'product_id' => $first->id, 'quantity' => 7, 'version' => 2])
        ->and($changes[$second->id]->payload)->toEqual(['stock_id' => stockOf($second)->id, 'product_id' => $second->id, 'quantity' => 4, 'version' => 2])
        ->and(outboxMessages('ordering.order_placed'))->toHaveCount(1);
});

it('records nothing and keeps every version when a write is refused', function (string $refusal) {
    $category = Category::factory()->create();
    $product = productWithStock(2);
    $inactive = Product::factory()->inactive()->withStock(10)->create();
    $empty = productWithStock(0);
    $versions = fn () => [
        DB::table('products')->orderBy('id')->pluck('version', 'id')->all(),
        DB::table('categories')->orderBy('id')->pluck('version', 'id')->all(),
        DB::table('stocks')->orderBy('id')->pluck('version', 'id')->all(),
    ];
    $before = $versions();

    match ($refusal) {
        'category with products' => (function () use ($category, $product) {
            $product->categories()->attach($category);
            $this->actingAs(admin())->deleteJson("/api/admin/categories/{$category->id}")->assertConflict();
        })(),
        'negative stock' => $this->actingAs(admin())->putJson('/api/admin/stocks/'.stockOf($product)->id, ['operation' => 'decrease', 'quantity' => 3])
            ->assertUnprocessable()->assertJsonPath('message', 'O estoque não pode ficar negativo.'),
        'inactive product' => $this->actingAs(customer())->postJson('/api/orders', checkoutBody([['product_id' => $inactive->id, 'quantity' => 1]]))->assertConflict(),
        'out of stock product' => $this->actingAs(customer())->postJson('/api/orders', checkoutBody([['product_id' => $empty->id, 'quantity' => 1]]))->assertConflict(),
        'insufficient stock' => $this->actingAs(customer())->postJson('/api/orders', checkoutBody([['product_id' => $product->id, 'quantity' => 3]]))->assertConflict(),
    };

    expect(DB::table('outbox_messages')->count())->toBe(0)
        ->and($versions())->toBe($before);
})->with(['category with products', 'negative stock', 'inactive product', 'out of stock product', 'insufficient stock']);

it('keeps no stock change and no version of a checkout that fails on the third code collision', function () {
    $first = productWithStock(5);
    $second = productWithStock(7);
    $account = customer();
    $this->actingAs($account);
    $customerId = $account->id;
    foreach (['AAAAAAAA', 'BBBBBBBB', 'CCCCCCCC'] as $code) {
        DB::table('orders')->insert(orderRow($customerId, ['code' => $code]));
    }
    forceOrderCodes(['AAAAAAAA', 'BBBBBBBB', 'CCCCCCCC', 'DDDDDDDD']);
    $ordersBefore = Order::query()->count();

    $this->postJson('/api/orders', checkoutBody([
        ['product_id' => $first->id, 'quantity' => 2],
        ['product_id' => $second->id, 'quantity' => 3],
    ]))->assertStatus(500);

    expect(outboxMessages('inventory.stock_changed'))->toBeEmpty()
        ->and(Order::query()->count())->toBe($ordersBefore)
        ->and(stockOf($first)->version)->toBe(1)
        ->and(stockOf($second)->version)->toBe(1)
        ->and(stockOf($first)->quantity)->toBe(5)
        ->and(stockOf($second)->quantity)->toBe(7);
});

it('locks the row before computing the version', function (string $change, string $table) {
    $category = Category::factory()->create();
    $product = productWithStock(5);
    $product->categories()->attach(Category::factory()->create());
    $stock = stockOf($product);
    $deletable = productWithStock(1);
    $emptyCategory = Category::factory()->create();

    $run = match ($change) {
        'product edit' => fn () => $this->actingAs(admin())->putJson("/api/admin/products/{$product->id}", editBody())->assertOk(),
        'status change' => fn () => $this->actingAs(admin())->patchJson("/api/admin/products/{$product->id}/status", ['status' => 'inactive'])->assertOk(),
        'product deletion' => fn () => $this->actingAs(admin())->deleteJson("/api/admin/products/{$deletable->id}")->assertNoContent(),
        'category rename' => fn () => $this->actingAs(admin())->putJson("/api/admin/categories/{$category->id}", ['name' => 'Outro nome'])->assertOk(),
        'category deletion' => fn () => $this->actingAs(admin())->deleteJson("/api/admin/categories/{$emptyCategory->id}")->assertNoContent(),
        'stock adjustment' => fn () => $this->actingAs(admin())->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'increase', 'quantity' => 1])->assertOk(),
        'checkout' => fn () => $this->actingAs(customer())->postJson('/api/orders', checkoutBody([['product_id' => $product->id, 'quantity' => 1]]))->assertCreated(),
    };
    // Sign in first: the queries of the sign-in are not the write's.
    $account = $change === 'checkout' ? customer() : admin();
    $this->actingAs($account);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $queries = array_column(DB::getQueryLog(), 'query');

    $touching = array_values(array_filter($queries, fn (string $sql) => preg_match('/(^|[\s"])'.$table.'([\s"]|$)/', $sql) === 1));
    $lock = null;
    $write = null;
    foreach ($touching as $position => $sql) {
        $lock ??= preg_match('/^select .* for update$/', $sql) === 1 ? $position : null;
        $write ??= preg_match('/^(update|delete)\b/i', $sql) === 1 ? $position : null;
    }

    expect($lock)->not->toBeNull('no select ... for update on '.$table)
        ->and($write)->not->toBeNull('no write on '.$table)
        ->and($lock)->toBeLessThan($write);
})->with([
    'product edit' => ['product edit', 'products'],
    'status change' => ['status change', 'products'],
    'product deletion' => ['product deletion', 'products'],
    'category rename' => ['category rename', 'categories'],
    'category deletion' => ['category deletion', 'categories'],
    'stock adjustment' => ['stock adjustment', 'stocks'],
    'checkout' => ['checkout', 'stocks'],
]);
