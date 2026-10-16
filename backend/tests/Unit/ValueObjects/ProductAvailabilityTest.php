<?php

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\ValueObjects\CatalogProduct;
use App\Modules\Catalog\ValueObjects\ProductAvailability;

it('decides the availability from the status and the units', function (ProductStatus $status, int $units, bool $available, int $quantity) {
    $availability = new ProductAvailability($status, $units);

    expect($availability->isAvailable())->toBe($available)
        ->and($availability->availableQuantity())->toBe($quantity);
})->with([
    'active with units' => [ProductStatus::Active, 7, true, 7],
    'inactive with units' => [ProductStatus::Inactive, 10, false, 10],
    'active without units' => [ProductStatus::Active, 0, false, 0],
    'inactive without units' => [ProductStatus::Inactive, 0, false, 0],
]);

it('carries the product status in the catalog product', function () {
    $product = new CatalogProduct('0199e3b0-4c2a-7d10-9a3b-000000000001', 'Teclado', null, 1000, ProductStatus::Inactive);

    expect($product->status)->toBe(ProductStatus::Inactive)
        ->and(property_exists($product, 'isActive'))->toBeFalse()
        ->and($product->availability(5)->isAvailable())->toBeFalse()
        ->and($product->availability(5)->availableQuantity())->toBe(5)
        ->and((new CatalogProduct('0199e3b0-4c2a-7d10-9a3b-000000000002', 'Mouse', null, 500, ProductStatus::Active))->availability(2)->isAvailable())->toBeTrue();
});
