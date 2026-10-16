<?php

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\ValueObjects\ProductAvailability;
use App\Modules\Ordering\Services\PurchaseAvailabilityService;

beforeEach(function () {
    $this->service = new PurchaseAvailabilityService;
});

it('explains why a quantity cannot be bought', function (ProductStatus $status, int $stock, int $quantity, ?string $expected) {
    expect($this->service->purchaseProblem(new ProductAvailability($status, $stock), $quantity))->toBe($expected);
})->with([
    'enough stock' => [ProductStatus::Active, 3, 3, null],
    'not enough stock' => [ProductStatus::Active, 2, 3, 'Estoque insuficiente. Disponível: 2.'],
    'out of stock' => [ProductStatus::Active, 0, 1, 'Produto indisponível.'],
    'without a stock row' => [ProductStatus::Active, 0, 1, 'Produto indisponível.'],
    'inactive product' => [ProductStatus::Inactive, 5, 1, 'Produto indisponível.'],
]);

// The rule is the catalog's (ProductAvailability): ordering words the problem and never decides it.
arch('ordering takes the availability from the catalog product')
    ->expect('App\Modules\Ordering')
    ->not->toUse(ProductStatus::class);

it('leaves the availability rule out of the purchase messages', function () {
    $methods = array_map(fn (ReflectionMethod $method) => $method->getName(), (new ReflectionClass(PurchaseAvailabilityService::class))->getMethods(ReflectionMethod::IS_PUBLIC));

    expect($methods)->toBe(['purchaseProblem']);
});
