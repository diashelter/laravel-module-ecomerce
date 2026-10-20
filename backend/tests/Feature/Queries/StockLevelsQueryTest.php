<?php

use Modules\Catalog\Core\ValueObjects\ProductIds;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Inventory\Contracts\StockLevels;
use Modules\Inventory\Core\ValueObjects\ProductStock;
use Modules\Inventory\Core\ValueObjects\ProductStocks;

it('reads the stock row of each product for the product pages', function () {
    $first = productWithStock(4);
    $second = productWithStock(9);
    $withoutStock = Product::factory()->create();
    productWithStock(1);

    $queries = queriesDuring(fn () => $this->stocks = app(StockLevels::class)->stocksFor(new ProductIds($first->id, $second->id, $withoutStock->id)));

    expect($this->stocks)->toBeInstanceOf(ProductStocks::class)
        ->and($this->stocks)->toHaveCount(2)
        ->and($this->stocks->of($first->id))->toEqual(new ProductStock(stockOf($first)->id, 4))
        ->and($this->stocks->of($second->id))->toEqual(new ProductStock(stockOf($second)->id, 9))
        ->and($this->stocks->of($withoutStock->id))->toBeNull()
        ->and($queries)->toHaveCount(1)
        ->and($queries[0])->not->toContain('for update');
});

it('reads the stock quantities of the given products without locking', function () {
    $first = productWithStock(4);
    $second = productWithStock(9);
    $withoutStock = Product::factory()->create();

    $queries = queriesDuring(fn () => $this->stock = app(StockLevels::class)->quantitiesFor(new ProductIds($first->id, $second->id, $withoutStock->id)));

    expect($this->stock->of($first->id))->toBe(4)
        ->and($this->stock->of($second->id))->toBe(9)
        ->and($this->stock->of($withoutStock->id))->toBe(0)
        ->and(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'for update')))->toBeEmpty();
});
