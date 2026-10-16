<?php

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Catalog\ValueObjects\ProductIds;

beforeEach(fn () => $this->actingAs(admin()));

it('lists stocks with their products', function () {
    productWithStock(3);

    $this->getJson('/api/admin/stocks')->assertOk()->assertJsonPath('data.0.quantity', 3)->assertJsonStructure([
        'data' => [['id', 'quantity', 'product' => ['id', 'name', 'status', 'is_available']]],
    ]);
});

it('increases and decreases stock', function () {
    $stock = stockOf(productWithStock(5));

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'increase', 'quantity' => 10])
        ->assertOk()->assertJsonPath('data.quantity', 15);

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'decrease', 'quantity' => 15])
        ->assertOk()->assertJsonPath('data.quantity', 0);
});

it('never allows negative stock', function () {
    $stock = stockOf(productWithStock(2));

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'decrease', 'quantity' => 3])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O estoque não pode ficar negativo.');

    expect($stock->fresh()->quantity)->toBe(2);
});

it('validates the stock operation', function () {
    $stock = stockOf(productWithStock(2));

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'set', 'quantity' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['operation', 'quantity']);
});

it('makes a product unavailable at zero stock and available again only if active', function () {
    $active = productWithStock(1);
    $inactive = Product::factory()->inactive()->withStock(0)->create();
    $activeStock = stockOf($active);
    $inactiveStock = stockOf($inactive);

    $this->putJson("/api/admin/stocks/{$activeStock->id}", ['operation' => 'decrease', 'quantity' => 1])
        ->assertJsonPath('data.product.is_available', false);
    $this->getJson("/api/products/{$active->id}")->assertNotFound();

    $this->putJson("/api/admin/stocks/{$activeStock->id}", ['operation' => 'increase', 'quantity' => 1])
        ->assertJsonPath('data.product.is_available', true);
    $this->getJson("/api/products/{$active->id}")->assertOk();

    $this->putJson("/api/admin/stocks/{$inactiveStock->id}", ['operation' => 'increase', 'quantity' => 5])
        ->assertJsonPath('data.product.is_available', false);
});

it('renders every stock row with exactly the keys of today', function () {
    $stock = stockOf(productWithStock(5));

    $listed = $this->getJson('/api/admin/stocks')->assertOk()->json('data.0');
    $adjusted = $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'increase', 'quantity' => 1])->assertOk()->json('data');

    foreach ([$listed, $adjusted] as $row) {
        expect(array_keys($row))->toBe(['id', 'quantity', 'product', 'updated_at'])
            ->and(array_keys($row['product']))->toBe(['id', 'name', 'image_url', 'status', 'status_label', 'is_available']);
    }
});

it('lists the stock with the same query count for 1 and 20 rows', function () {
    $listing = fn () => queriesDuring(fn () => $this->getJson('/api/admin/stocks')->assertOk());

    productWithStock(3);
    $single = $listing();

    foreach (range(1, 19) as $unused) {
        productWithStock(3);
    }
    $full = $listing();

    $productReads = array_values(array_filter($full, fn (string $sql) => str_contains($sql, 'from "products"')));

    expect(count($full))->toBe(count($single))
        ->and($productReads)->toHaveCount(1)
        ->and($productReads[0])->toContain('"id" in (');
});

it('lists twenty stock rows per page by quantity and then by id', function () {
    $ids = collect(range(1, 21))->map(fn () => stockOf(productWithStock(4))->id)->sort()->values();
    stockOf(productWithStock(1));

    $response = $this->getJson('/api/admin/stocks')->assertOk();
    $second = $this->getJson('/api/admin/stocks?page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(20)
        ->and($response->json('meta.per_page'))->toBe(20)
        ->and($response->json('meta.total'))->toBe(22)
        ->and($response->json('data.0.quantity'))->toBe(1)
        ->and(collect($response->json('data'))->skip(1)->pluck('id')->all())->toBe($ids->take(19)->all())
        ->and(collect($second->json('data'))->pluck('id')->all())->toBe($ids->skip(19)->values()->all());
});

it('keeps a stock row whose product is gone with a null product', function () {
    $stock = stockOf(productWithStock(3));
    app()->instance(ProductCatalog::class, new class implements ProductCatalog
    {
        public function findMany(ProductIds $ids): CatalogProducts
        {
            return new CatalogProducts;
        }
    });

    $this->getJson('/api/admin/stocks')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $stock->id)
        ->assertJsonPath('data.0.quantity', 3)
        ->assertJsonPath('data.0.product', null);
});
