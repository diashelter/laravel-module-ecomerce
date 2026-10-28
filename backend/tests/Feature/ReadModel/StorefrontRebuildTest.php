<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\InboundEvents\StockChanged;
use Modules\Catalog\Events\CategoryUpdated;
use Modules\Catalog\Events\ProductUpdated;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Messaging\Contracts\DomainEvent;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Messaging\Persistence\Repositories\OutboxMessageRepository;

/*
 * The rebuild of the storefront (S3 of the storefront read model): empty the read model, publish the state of every
 * record through the outbox, deliver the messages.
 */

/** An outbox that records like the real one and fails on the $nth record. */
function outboxFailingOn(int $nth): EventOutbox
{
    return new class($nth) implements EventOutbox
    {
        private int $calls = 0;

        public function __construct(private readonly int $nth) {}

        public function record(DomainEvent $event): void
        {
            if (++$this->calls === $this->nth) {
                throw new RuntimeException('outbox down');
            }

            app(OutboxMessageRepository::class)->record($event);
        }
    };
}

/** Two categories, three products with categories and stock, with versions the factories do not set. */
function catalogToRepublish(): array
{
    [$first, $second] = Category::factory()->count(2)->create()->all();
    DB::table('categories')->where('id', $second->id)->update(['version' => 3]);
    $products = [];

    foreach ([4, 0, 9] as $index => $quantity) {
        $product = productWithStock($quantity);
        $product->categories()->attach($index === 1 ? [$second->id, $first->id] : [$first->id]);
        DB::table('products')->where('id', $product->id)->update(['version' => $index + 2]);
        DB::table('stocks')->where('product_id', $product->id)->update(['version' => $index + 5]);
        $products[] = $product->id;
    }

    return [[$first->id, $second->id], $products];
}

it('republishes every category and product with the current state and version', function () {
    [$categoryIds, $productIds] = catalogToRepublish();

    $exit = Artisan::call('catalog:republish');

    $categories = outboxMessages('catalog.category_updated')->keyBy('payload.category_id');
    $products = outboxMessages('catalog.product_updated')->keyBy('payload.product_id');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Republicados: 2 categorias e 3 produtos.')
        ->and($categories)->toHaveCount(2)
        ->and($products)->toHaveCount(3)
        ->and(DB::table('outbox_messages')->count())->toBe(5);

    foreach ($categoryIds as $id) {
        $row = DB::table('categories')->find($id);
        expect($categories[$id]->payload)->toEqual(['category_id' => $id, 'name' => $row->name, 'slug' => $row->slug, 'version' => $row->version]);
    }

    foreach ($productIds as $id) {
        $row = Product::query()->findOrFail($id);
        $payload = $products[$id]->payload;
        $expected = $row->categories()->orderBy('categories.id')->pluck('categories.id')->all();

        expect($payload['version'])->toBe($row->version)
            ->and($payload['name'])->toBe($row->name)
            ->and($payload['price_cents'])->toBe($row->price_cents)
            ->and($payload['status'])->toBe($row->status->value)
            ->and($payload['category_ids'])->toBe($expected)
            ->and($payload['created_at'])->toBe($row->created_at->toIso8601String())
            ->and($payload['updated_at'])->toBe($row->updated_at->toIso8601String());
    }
});

it('republishes every stock row with the current quantity and version', function () {
    [, $productIds] = catalogToRepublish();

    $exit = Artisan::call('inventory:republish');

    $changes = outboxMessages('inventory.stock_changed')->keyBy('payload.product_id');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Republicados: 3 estoques.')
        ->and($changes)->toHaveCount(3)
        ->and(DB::table('outbox_messages')->count())->toBe(3);

    foreach ($productIds as $id) {
        $stock = DB::table('stocks')->where('product_id', $id)->first();
        expect($changes[$id]->payload)->toEqual(['stock_id' => $stock->id, 'product_id' => $id, 'quantity' => $stock->quantity, 'version' => $stock->version]);
    }
});

it('reads each product and its categories in one snapshot', function () {
    catalogToRepublish();
    DB::flushQueryLog();
    DB::enableQueryLog();

    Artisan::call('catalog:republish');

    $queries = array_column(DB::getQueryLog(), 'query');
    $products = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from "products"')));
    $pivot = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'category_product') && ! str_contains($sql, 'from "products"')));

    // One statement per product holds the row and its category ids; no second query reads the ids.
    expect($products)->toHaveCount(5) // two pages of ids (the second is empty) and the three snapshots
        ->and(array_values(array_filter($products, fn (string $sql) => str_contains($sql, 'category_product'))))->toHaveCount(3)
        ->and($pivot)->toBeEmpty();
});

it('exits non-zero with the count recorded when the republish fails halfway and converges when run again', function (string $command, string $failed) {
    catalogToRepublish();

    // The third record is refused: two had been recorded (categories for catalog:republish, stocks for the other).
    app()->instance(EventOutbox::class, outboxFailingOn(3));
    $exit = Artisan::call($command);

    expect($exit)->not->toBe(0)
        ->and(Artisan::output())->toContain('Republicados antes da falha: 2.')
        ->and(DB::table('outbox_messages')->count())->toBe(2);

    // The outbox is back: running again records everything, and the storefront ends as after a clean rebuild.
    app()->forgetInstance(EventOutbox::class);
    DB::table('outbox_messages')->delete();
    Artisan::call('catalog:read-model-clear');
    Artisan::call($command);
    Artisan::call($command === 'catalog:republish' ? 'inventory:republish' : 'catalog:republish');
    relayUntilIdle();
    $afterFailure = readModel()->products()->find([], ['sort' => ['_id' => 1], 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray();

    rebuildStorefront();
    $clean = readModel()->products()->find([], ['sort' => ['_id' => 1], 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray();

    expect($afterFailure)->toHaveCount(3)->and($afterFailure)->toBe($clean);
})->with([
    'catalog:republish' => ['catalog:republish', 'catalog'],
    'inventory:republish' => ['inventory:republish', 'inventory'],
]);

/** Every body the storefront answers: each page of each sort, a category filter, and every product page. */
function storefrontBodies(array $productIds, string $categorySlug): array
{
    $bodies = [];

    foreach (['name', 'price_asc', 'price_desc'] as $sort) {
        foreach ([1, 2] as $page) {
            $bodies["list {$sort} {$page}"] = test()->getJson("/api/products?sort={$sort}&page={$page}")->assertOk()->json();
        }
    }

    $bodies['category'] = test()->getJson("/api/products?category={$categorySlug}")->assertOk()->json();

    foreach ($productIds as $id) {
        $response = test()->getJson("/api/products/{$id}");
        // The request id of an error body is different on every request.
        $bodies["product {$id}"] = [$response->status(), Arr::except($response->json(), 'request_id')];
    }

    return $bodies;
}

it('returns the same storefront after emptying MongoDB and rebuilding', function () {
    $this->actingAs(admin());
    $categories = collect(['Periféricos', 'Monitores'])->map(fn (string $name) => Category::query()->findOrFail($this->postJson('/api/admin/categories', ['name' => $name])->assertCreated()->json('data.id')));
    $ids = [];

    // Written through the routes, so every change publishes its event and the storefront fills as in production.
    foreach (range(1, 15) as $number) {
        $ids[] = $this->postJson('/api/admin/products', productPayload([
            'name' => "Produto {$number}",
            'price_cents' => 1000 + ($number % 4) * 250,
            'category_ids' => $number % 2 === 0 ? [$categories[0]->id, $categories[1]->id] : [$categories[1]->id],
            'stock_quantity' => $number % 5 === 0 ? 0 : 10,
        ]))->assertCreated()->json('data.id');
    }
    $this->putJson("/api/admin/products/{$ids[0]}", productPayload(['name' => 'Editado', 'category_ids' => [$categories[0]->id]]))->assertOk();
    $this->patchJson("/api/admin/products/{$ids[1]}/status", ['status' => 'inactive'])->assertOk();
    $this->deleteJson("/api/admin/products/{$ids[2]}")->assertNoContent();
    $this->putJson('/api/admin/stocks/'.stockOf(Product::query()->findOrFail($ids[3]))->id, ['operation' => 'decrease', 'quantity' => 4])->assertOk();
    $this->actingAs($account = customer())->postJson('/api/orders', checkoutBody([['product_id' => $ids[5], 'quantity' => 2]]))->assertCreated();
    relayUntilIdle();

    $before = storefrontBodies($ids, $categories[0]->slug);

    expect($before['list name 1']['meta']['total'])->toBe(14);

    readModel()->products()->deleteMany([]);
    readModel()->categories()->deleteMany([]);
    $this->getJson('/api/products')->assertOk()->assertJsonPath('meta.total', 0);

    rebuildStorefront();

    expect(storefrontBodies($ids, $categories[0]->slug))->toEqual($before);
});

it('leaves no document of a record missing from PostgreSQL after the rebuild', function () {
    $product = productWithStock(3);
    $category = Category::factory()->create();
    readModel()->products()->insertOne(['_id' => unknownId(), 'product' => ['name' => 'Fantasma', 'version' => 9], 'stock' => null, 'deleted_version' => null]);
    readModel()->products()->insertOne(['_id' => unknownId(), 'product' => null, 'stock' => null, 'deleted_version' => 4]);
    readModel()->categories()->insertOne(['_id' => unknownId(), 'name' => 'Fantasma', 'slug' => 'fantasma', 'version' => 9, 'deleted_version' => null]);

    rebuildStorefront();

    expect(readModel()->products()->distinct('_id'))->toBe([$product->id])
        ->and(readModel()->categories()->distinct('_id'))->toBe([$category->id]);
});

it('keeps the written state when a republished envelope arrives after the write', function () {
    $this->actingAs(admin());
    $product = productWithStock(3);
    Artisan::call('catalog:read-model-clear');

    // The rebuild records the product at version 1; before it is delivered, an edit commits at version 2.
    Artisan::call('catalog:republish');
    $this->putJson("/api/admin/products/{$product->id}", productPayload(['name' => 'Depois da reconstrução']))->assertOk();

    $messages = outboxMessages('catalog.product_updated');
    expect($messages->pluck('payload.version')->all())->toBe([1, 2]);

    // Delivered in the wrong order: the write first, then the older republished one.
    project(ProductUpdated::fromPayload($messages[1]->payload));
    project(ProductUpdated::fromPayload($messages[0]->payload));
    Artisan::call('inventory:republish');
    project(StockChanged::fromPayload(outboxMessages('inventory.stock_changed')->sole()->payload));

    $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('data.name', 'Depois da reconstrução');
});

it('answers 200 with what was projected while the rebuild runs', function () {
    $products = collect(range(1, 6))->map(fn () => productWithStock(2));
    Category::factory()->count(2)->create();
    Artisan::call('catalog:read-model-clear');
    Artisan::call('catalog:republish');
    Artisan::call('inventory:republish');

    // Nothing projected yet.
    $this->getJson('/api/products')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');

    // Only the categories delivered: still an empty page.
    foreach (outboxMessages('catalog.category_updated') as $message) {
        project(CategoryUpdated::fromPayload($message->payload));
    }
    $this->getJson('/api/products')->assertOk()->assertJsonPath('meta.total', 0);

    // Half of the products delivered: that half, each with its stock once the stock arrives.
    foreach (outboxMessages('catalog.product_updated')->take(3) as $message) {
        project(ProductUpdated::fromPayload($message->payload));
    }
    $response = $this->getJson('/api/products')->assertOk()->assertJsonPath('meta.total', 3);

    expect(array_column($response->json('data'), 'is_available'))->toBe([false, false, false]);

    foreach (outboxMessages('inventory.stock_changed') as $message) {
        project(StockChanged::fromPayload($message->payload));
    }

    expect(array_column($this->getJson('/api/products')->assertOk()->json('data'), 'is_available'))->toBe([true, true, true]);
});
