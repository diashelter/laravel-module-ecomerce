<?php

use App\Modules\Catalog\Models\Product;

it('recalculates the cart in cents from database prices', function () {
    $product = productWithStock(10, ['price_cents' => 1990]);

    $this->postJson('/api/cart/validate', [
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'price' => '0.01', 'unit_price' => 1, 'total_cents' => 1]],
    ])
        ->assertOk()
        ->assertJsonPath('data.is_valid', true)
        ->assertJsonPath('data.total_cents', 5970)
        ->assertJsonPath('data.items.0.unit_price_cents', 1990)
        ->assertJsonPath('data.items.0.subtotal_cents', 5970)
        ->assertJsonPath('data.items.0.problem', null);
});

it('reports problems for each item', function () {
    $lowStock = productWithStock(2);
    $inactive = Product::factory()->inactive()->withStock(10)->create();

    $response = $this->postJson('/api/cart/validate', [
        'items' => [
            ['product_id' => $lowStock->id, 'quantity' => 5],
            ['product_id' => $inactive->id, 'quantity' => 1],
            ['product_id' => 999999, 'quantity' => 1],
        ],
    ])->assertOk()->assertJsonPath('data.is_valid', false);

    $problems = collect($response->json('data.items'))->pluck('problem', 'product_id');
    expect($problems[$lowStock->id])->toBe('Estoque insuficiente. Disponível: 2.')
        ->and($problems[$inactive->id])->toBe('Produto indisponível.')
        ->and($problems[999999])->toBe('Produto não encontrado.');
});

it('validates the cart payload', function () {
    $this->postJson('/api/cart/validate', ['items' => [['product_id' => 'x', 'quantity' => 0]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
});
