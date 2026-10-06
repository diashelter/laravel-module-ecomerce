<?php

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Exceptions\InsufficientStockException;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\PurchaseAvailabilityService;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->service = new CheckoutService(new PurchaseAvailabilityService);
    $this->product = function (int $id, int $priceCents, int $stock, ProductStatus $status = ProductStatus::Active): Product {
        $product = new Product(['name' => "Produto {$id}", 'price_cents' => $priceCents, 'status' => $status]);
        $product->id = $id;

        return $product->setRelation('stock', new Stock(['quantity' => $stock]));
    };
});

it('accepts quantities that the stock can fulfil', function () {
    $products = new Collection([1 => ($this->product)(1, 1000, 3)]);

    $this->service->assertCanFulfil([1 => 3], $products);
})->throwsNoExceptions();

it('reports every product that cannot be bought, keyed by item', function () {
    $products = new Collection([
        1 => ($this->product)(1, 1000, 2),
        2 => ($this->product)(2, 1000, 5, ProductStatus::Inactive),
    ]);

    try {
        $this->service->assertCanFulfil([1 => 3, 2 => 1, 3 => 1], $products);
        $this->fail('InsufficientStockException was not thrown.');
    } catch (InsufficientStockException $e) {
        expect($e->errors())->toBe([
            'items.1' => ['Estoque insuficiente. Disponível: 2.'],
            'items.2' => ['Produto indisponível.'],
            'items.3' => ['Produto não encontrado.'],
        ]);
    }
});

it('builds the order lines and total in cents', function () {
    $products = new Collection([
        1 => ($this->product)(1, 1990, 10),
        2 => ($this->product)(2, 10, 10),
    ]);

    $result = $this->service->buildOrderLines([1 => 3, 2 => 3], $products);

    expect($result['total_cents'])->toBe(6000)
        ->and($result['lines'])->toBe([
            ['product_id' => 1, 'product_name' => 'Produto 1', 'unit_price_cents' => 1990, 'quantity' => 3, 'subtotal_cents' => 5970],
            ['product_id' => 2, 'product_name' => 'Produto 2', 'unit_price_cents' => 10, 'quantity' => 3, 'subtotal_cents' => 30],
        ]);
});
