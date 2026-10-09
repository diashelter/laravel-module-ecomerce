<?php

use App\Modules\Ordering\Services\PurchaseAvailabilityService;

beforeEach(function () {
    $this->service = new PurchaseAvailabilityService;
});

it('decides availability from the product status and the stock units', function (bool $isActive, ?int $quantity, bool $expected) {
    expect($this->service->isAvailable($isActive, $quantity))->toBe($expected);
})->with([
    'active with stock' => [true, 3, true],
    'active without stock' => [true, 0, false],
    'active without a stock row' => [true, null, false],
    'inactive with stock' => [false, 3, false],
]);

it('treats a missing stock row as zero units', function () {
    expect($this->service->availableQuantity(null))->toBe(0)
        ->and($this->service->availableQuantity(7))->toBe(7);
});

it('explains why a quantity cannot be bought', function (bool $isActive, ?int $stock, int $quantity, ?string $expected) {
    expect($this->service->purchaseProblem($isActive, $stock, $quantity))->toBe($expected);
})->with([
    'enough stock' => [true, 3, 3, null],
    'not enough stock' => [true, 2, 3, 'Estoque insuficiente. Disponível: 2.'],
    'out of stock' => [true, 0, 1, 'Produto indisponível.'],
    'without a stock row' => [true, null, 1, 'Produto indisponível.'],
    'inactive product' => [false, 5, 1, 'Produto indisponível.'],
]);
