<?php

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Services\PurchaseAvailabilityService;

beforeEach(function () {
    $this->service = new PurchaseAvailabilityService;
});

it('decides availability from the product status and the stock units', function (ProductStatus $status, ?int $quantity, bool $expected) {
    $product = new Product(['status' => $status]);
    $stock = $quantity === null ? null : new Stock(['quantity' => $quantity]);

    expect($this->service->isAvailable($product, $stock))->toBe($expected);
})->with([
    'active with stock' => [ProductStatus::Active, 3, true],
    'active without stock' => [ProductStatus::Active, 0, false],
    'active without a stock row' => [ProductStatus::Active, null, false],
    'inactive with stock' => [ProductStatus::Inactive, 3, false],
]);

it('treats a missing stock row as zero units', function () {
    expect($this->service->availableQuantity(null))->toBe(0)
        ->and($this->service->availableQuantity(new Stock(['quantity' => 7])))->toBe(7);
});

it('explains why a quantity cannot be bought', function (ProductStatus $status, int $stock, int $quantity, ?string $expected) {
    $product = new Product(['status' => $status]);

    expect($this->service->purchaseProblem($product, new Stock(['quantity' => $stock]), $quantity))->toBe($expected);
})->with([
    'enough stock' => [ProductStatus::Active, 3, 3, null],
    'not enough stock' => [ProductStatus::Active, 2, 3, 'Estoque insuficiente. Disponível: 2.'],
    'out of stock' => [ProductStatus::Active, 0, 1, 'Produto indisponível.'],
    'inactive product' => [ProductStatus::Inactive, 5, 1, 'Produto indisponível.'],
]);
