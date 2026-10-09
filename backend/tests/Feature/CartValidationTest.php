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

it('treats a product without a stock row as unavailable in the cart', function () {
    $product = Product::factory()->create();

    $response = $this->postJson('/api/cart/validate', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
        ->assertOk()
        ->assertJsonPath('data.is_valid', false);

    expect($response->json('data.items.0'))->toMatchArray([
        'product_id' => $product->id,
        'available_quantity' => 0,
        'is_available' => false,
        'problem' => 'Produto indisponível.',
    ]);
});

it('validates the cart payload', function () {
    $this->postJson('/api/cart/validate', ['items' => [['product_id' => 'x', 'quantity' => 0]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
});

it('merges repeated lines and totals the cart in product order', function () {
    $first = productWithStock(10, ['price_cents' => 1990]);
    $second = productWithStock(10, ['price_cents' => 500]);

    $this->postJson('/api/cart/validate', ['items' => [
        ['product_id' => $first->id, 'quantity' => 2],
        ['product_id' => $first->id, 'quantity' => 3],
        ['product_id' => $second->id, 'quantity' => 1],
    ]])
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.product_id', $first->id)
        ->assertJsonPath('data.items.0.quantity', 5)
        ->assertJsonPath('data.items.1.product_id', $second->id)
        ->assertJsonPath('data.total_cents', 5 * 1990 + 500)
        ->assertJsonPath('data.is_valid', true);
});

it('keeps a missing product line out of the cart total', function () {
    $product = productWithStock(10, ['price_cents' => 1000]);

    $this->postJson('/api/cart/validate', ['items' => [
        ['product_id' => $product->id, 'quantity' => 1],
        ['product_id' => 999999, 'quantity' => 2],
    ]])
        ->assertOk()
        ->assertJsonPath('data.items.1', [
            'product_id' => 999999,
            'name' => null,
            'image_url' => null,
            'unit_price_cents' => null,
            'quantity' => 2,
            'subtotal_cents' => null,
            'available_quantity' => 0,
            'is_available' => false,
            'problem' => 'Produto não encontrado.',
        ])
        ->assertJsonPath('data.total_cents', 1000)
        ->assertJsonPath('data.is_valid', false);
});

it('keeps an out of stock line out of total_cents', function () {
    $available = productWithStock(10, ['price_cents' => 1000]);
    $soldOut = productWithStock(0, ['price_cents' => 700]);

    $this->postJson('/api/cart/validate', ['items' => [
        ['product_id' => $available->id, 'quantity' => 2],
        ['product_id' => $soldOut->id, 'quantity' => 3],
    ]])
        ->assertOk()
        ->assertJsonPath('data.items.1.subtotal_cents', 2100)
        ->assertJsonPath('data.items.1.unit_price_cents', 700)
        ->assertJsonPath('data.total_cents', 2000)
        ->assertJsonPath('data.is_valid', false);
});

it('keeps the exact cart validation response shape', function () {
    $product = productWithStock(10);

    $data = $this->postJson('/api/cart/validate', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
        ->assertOk()
        ->json('data');

    expect(array_keys($data))->toBe(['items', 'total_cents', 'is_valid'])
        ->and(array_keys($data['items'][0]))->toBe([
            'product_id', 'name', 'image_url', 'unit_price_cents', 'quantity',
            'subtotal_cents', 'available_quantity', 'is_available', 'problem',
        ]);
});
