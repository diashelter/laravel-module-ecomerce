<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;

it('lists active and inactive products with availability', function () {
    $available = productWithStock(10);
    $outOfStock = productWithStock(0);
    $inactive = Product::factory()->inactive()->withStock(10)->create();

    $response = $this->getJson('/api/products')->assertOk();

    $byId = collect($response->json('data'))->keyBy('id');
    expect($byId)->toHaveCount(3)
        ->and($byId[$available->id]['is_available'])->toBeTrue()
        ->and($byId[$outOfStock->id]['is_available'])->toBeFalse()
        ->and($byId[$inactive->id]['is_available'])->toBeFalse();
});

it('filters products by category slug', function () {
    $games = Category::factory()->create(['name' => 'Games']);
    $inGames = productWithStock(1);
    $inGames->categories()->attach($games);
    productWithStock(1);

    $this->getJson('/api/products?category=games')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inGames->id);
});

it('sorts products', function (string $sort, array $expected) {
    productWithStock(1, ['name' => 'Banana', 'price' => '20.00']);
    productWithStock(1, ['name' => 'Abacaxi', 'price' => '30.00']);
    productWithStock(1, ['name' => 'Caju', 'price' => '10.00']);

    $names = collect($this->getJson("/api/products?sort={$sort}")->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->toBe($expected);
})->with([
    'alphabetical' => ['name', ['Abacaxi', 'Banana', 'Caju']],
    'lowest price' => ['price_asc', ['Caju', 'Banana', 'Abacaxi']],
    'highest price' => ['price_desc', ['Abacaxi', 'Banana', 'Caju']],
]);

it('rejects an invalid sort option', function () {
    $this->getJson('/api/products?sort=random')->assertUnprocessable()->assertJsonValidationErrors('sort');
});

it('paginates products', function () {
    Product::factory()->count(15)->withStock(1)->create();

    $this->getJson('/api/products?page=2')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 15);
});

it('shows an available product', function () {
    $product = productWithStock(7);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.available_quantity', 7);
});

it('does not show unavailable products', function (string $state) {
    $product = $state === 'inactive'
        ? Product::factory()->inactive()->withStock(5)->create()
        : productWithStock(0);

    $this->getJson("/api/products/{$product->id}")
        ->assertNotFound()
        ->assertJsonPath('message', 'Produto indisponível.');
})->with(['inactive', 'out of stock']);

it('lists categories', function () {
    Category::factory()->count(3)->create();

    $this->getJson('/api/categories')->assertOk()->assertJsonCount(3, 'data');
});
