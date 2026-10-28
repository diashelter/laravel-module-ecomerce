<?php

use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;

it('renders every product with exactly the keys of today', function (string $method, string $uri, string $path) {
    $this->actingAs(admin());
    $product = productWithStock(7);
    $product->categories()->attach(Category::factory()->count(2)->create());
    rebuildStorefront();

    $body = match ($method.' '.$uri) {
        'POST api/admin/products', 'PUT api/admin/products/{product}' => productPayload(),
        'PATCH api/admin/products/{product}/status' => ['status' => 'inactive'],
        default => [],
    };

    $item = data_get($this->json($method, '/'.str_replace('{product}', $product->id, $uri), $body)->assertSuccessful()->json(), $path);

    expect(array_keys($item))->toBe([
        'id', 'name', 'price_cents', 'description', 'image_url', 'status', 'status_label',
        'is_available', 'available_quantity', 'stock', 'categories', 'created_at', 'updated_at',
    ])
        ->and(array_keys($item['stock']))->toBe(['id', 'quantity'])
        ->and($item['categories'])->not->toBeEmpty();

    foreach ($item['categories'] as $category) {
        expect(array_keys($category))->toBe(['id', 'name', 'slug']);
    }
})->with([
    'storefront list' => ['GET', 'api/products', 'data.0'],
    'storefront product' => ['GET', 'api/products/{product}', 'data'],
    'admin list' => ['GET', 'api/admin/products', 'data.0'],
    'admin product' => ['GET', 'api/admin/products/{product}', 'data'],
    'admin create' => ['POST', 'api/admin/products', 'data'],
    'admin update' => ['PUT', 'api/admin/products/{product}', 'data'],
    'admin status' => ['PATCH', 'api/admin/products/{product}/status', 'data'],
]);

it('lists the admin products newest first fifteen per page', function () {
    $ids = collect(range(1, 16))->map(fn () => productWithStock(1)->id);
    $this->actingAs(admin());

    $response = $this->getJson('/api/admin/products')->assertOk();

    expect($response->json('data'))->toHaveCount(15)
        ->and(collect($response->json('data'))->pluck('id')->all())->toBe($ids->reverse()->take(15)->values()->all())
        ->and($response->json('meta.per_page'))->toBe(15)
        ->and($response->json('meta.total'))->toBe(16)
        ->and(Product::query()->count())->toBe(16);
});

it('returns the new product as available with its stock and categories', function () {
    $this->actingAs(admin());
    [$first, $second] = Category::factory()->count(2)->create();

    $response = $this->postJson('/api/admin/products', productPayload(['stock_quantity' => 7, 'category_ids' => [$first->id, $second->id]]))
        ->assertCreated()
        ->assertJsonPath('data.stock.quantity', 7)
        ->assertJsonPath('data.is_available', true)
        ->assertJsonPath('data.available_quantity', 7);

    expect($response->json('data.stock.id'))->toBe(stockOf(Product::query()->findOrFail($response->json('data.id')))->id)
        ->and(collect($response->json('data.categories'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});
