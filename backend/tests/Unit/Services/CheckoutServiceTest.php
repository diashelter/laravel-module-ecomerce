<?php

use App\Modules\Catalog\ValueObjects\CatalogProduct;
use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Inventory\ValueObjects\StockQuantities;
use App\Modules\Ordering\DTOs\CartItemDTO;
use App\Modules\Ordering\Exceptions\InsufficientStockException;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\Services\PurchaseAvailabilityService;
use App\Modules\Ordering\ValueObjects\ProductQuantities;

beforeEach(function () {
    $this->service = new CheckoutService(new PurchaseAvailabilityService);
    $this->quantities = fn (array $byProduct) => new ProductQuantities(
        ...array_map(fn (int $id) => new CartItemDTO($id, $byProduct[$id]), array_keys($byProduct)),
    );
    $this->product = fn (int $id, int $priceCents, bool $isActive = true) => new CatalogProduct($id, "Produto {$id}", null, $priceCents, $isActive);
    $this->stock = fn (array $byProduct) => new StockQuantities(collect($byProduct));
});

it('accepts quantities that the stock can fulfil', function () {
    $products = new CatalogProducts(($this->product)(1, 1000));

    $this->service->assertCanFulfil(($this->quantities)([1 => 3]), $products, ($this->stock)([1 => 3]));
})->throwsNoExceptions();

it('reports every product that cannot be bought, keyed by item', function () {
    $products = new CatalogProducts(($this->product)(1, 1000), ($this->product)(2, 1000, isActive: false));

    try {
        $this->service->assertCanFulfil(($this->quantities)([1 => 3, 2 => 1, 3 => 1]), $products, ($this->stock)([1 => 2, 2 => 5]));
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
    $products = new CatalogProducts(($this->product)(1, 1990), ($this->product)(2, 10));

    $lines = $this->service->buildOrderLines(($this->quantities)([1 => 3, 2 => 3]), $products);

    expect($lines->totalCents())->toBe(6000)
        ->and(array_map(fn ($line) => [$line->productName, $line->unitPriceCents, $line->quantity, $line->subtotalCents()], iterator_to_array($lines)))
        ->toBe([['Produto 1', 1990, 3, 5970], ['Produto 2', 10, 3, 30]]);
});
