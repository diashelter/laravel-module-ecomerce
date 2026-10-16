<?php

use App\Modules\Inventory\Contracts\StockMetrics;

it('counts products in stock and the total units', function () {
    productWithStock(10);
    productWithStock(5);
    productWithStock(0);

    $totals = app(StockMetrics::class)->totals();

    expect($totals->productsInStock)->toBe(2)
        ->and($totals->totalUnits)->toBe(15);
});

it('returns zero units when there is no stock', function () {
    $totals = app(StockMetrics::class)->totals();

    expect($totals->productsInStock)->toBe(0)
        ->and($totals->totalUnits)->toBe(0);
});
