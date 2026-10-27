<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
