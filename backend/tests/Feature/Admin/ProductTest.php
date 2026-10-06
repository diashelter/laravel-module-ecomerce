<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Models\OrderItem;

beforeEach(fn () => $this->actingAs(admin()));

it('creates a product with its price in cents', function () {
    $response = $this->postJson('/api/admin/products', productPayload())
        ->assertCreated()
        ->assertJsonPath('data.price_cents', 129990)
        ->assertJsonMissingPath('data.price')
        ->assertJsonPath('data.stock.quantity', 7)
        ->assertJsonCount(2, 'data.categories');

    $product = Product::query()->findOrFail($response->json('data.id'));
    expect($product->image_url)->toBe("https://picsum.photos/seed/product-{$product->id}/600/600");
});

it('validates product data', function () {
    $this->postJson('/api/admin/products', productPayload([
        'price_cents' => -1,
        'status' => 'deleted',
        'category_ids' => [],
        'stock_quantity' => -5,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['price_cents', 'status', 'category_ids', 'stock_quantity']);
});

it('updates a product without touching its stock', function () {
    $product = productWithStock(4);

    $this->putJson("/api/admin/products/{$product->id}", productPayload(['name' => 'Renamed', 'stock_quantity' => 999]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.stock.quantity', 4);
});

it('activates and deactivates a product', function () {
    $product = productWithStock(4);

    $this->patchJson("/api/admin/products/{$product->id}/status", ['status' => 'inactive'])
        ->assertOk()
        ->assertJsonPath('data.is_available', false);

    $this->patchJson("/api/admin/products/{$product->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.is_available', true);
});

it('deletes a product without orders together with its stock', function () {
    $product = productWithStock(4);

    $this->deleteJson("/api/admin/products/{$product->id}")->assertNoContent();

    $this->assertModelMissing($product);
    $this->assertDatabaseMissing('stocks', ['product_id' => $product->id]);
});

it('blocks deleting a product present in orders', function () {
    $product = productWithStock(4);
    OrderItem::factory()->forProduct($product, 1)->create();

    $this->deleteJson("/api/admin/products/{$product->id}")->assertConflict();

    $this->assertModelExists($product);
});
