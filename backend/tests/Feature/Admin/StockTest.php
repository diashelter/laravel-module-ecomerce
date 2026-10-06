<?php

use App\Modules\Catalog\Models\Product;

beforeEach(fn () => $this->actingAs(admin()));

it('lists stocks with their products', function () {
    productWithStock(3);

    $this->getJson('/api/admin/stocks')->assertOk()->assertJsonPath('data.0.quantity', 3)->assertJsonStructure([
        'data' => [['id', 'quantity', 'product' => ['id', 'name', 'status', 'is_available']]],
    ]);
});

it('increases and decreases stock', function () {
    $stock = productWithStock(5)->stock;

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'increase', 'quantity' => 10])
        ->assertOk()->assertJsonPath('data.quantity', 15);

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'decrease', 'quantity' => 15])
        ->assertOk()->assertJsonPath('data.quantity', 0);
});

it('never allows negative stock', function () {
    $stock = productWithStock(2)->stock;

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'decrease', 'quantity' => 3])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O estoque não pode ficar negativo.');

    expect($stock->fresh()->quantity)->toBe(2);
});

it('validates the stock operation', function () {
    $stock = productWithStock(2)->stock;

    $this->putJson("/api/admin/stocks/{$stock->id}", ['operation' => 'set', 'quantity' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['operation', 'quantity']);
});

it('makes a product unavailable at zero stock and available again only if active', function () {
    $active = productWithStock(1);
    $inactive = Product::factory()->inactive()->withStock(0)->create();

    $this->putJson("/api/admin/stocks/{$active->stock->id}", ['operation' => 'decrease', 'quantity' => 1])
        ->assertJsonPath('data.product.is_available', false);
    $this->getJson("/api/products/{$active->id}")->assertNotFound();

    $this->putJson("/api/admin/stocks/{$active->stock->id}", ['operation' => 'increase', 'quantity' => 1])
        ->assertJsonPath('data.product.is_available', true);
    $this->getJson("/api/products/{$active->id}")->assertOk();

    $this->putJson("/api/admin/stocks/{$inactive->stock->id}", ['operation' => 'increase', 'quantity' => 5])
        ->assertJsonPath('data.product.is_available', false);
});
