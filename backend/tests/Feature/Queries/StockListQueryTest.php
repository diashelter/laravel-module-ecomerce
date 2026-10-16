<?php

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\Queries\StockListQuery;
use App\Modules\Inventory\ValueObjects\StockView;

it('paginates stocks by quantity ascending', function () {
    $high = stockOf(productWithStock(30));
    $low = stockOf(productWithStock(1));
    $middle = stockOf(productWithStock(10));

    $page = app(StockListQuery::class)->paginateByQuantity(20);

    expect($page->map(fn (StockView $view) => $view->stock->id)->all())->toBe([$low->id, $middle->id, $high->id])
        ->and($page->first()->product->id)->toBe($low->product_id);
});

it('keeps a stock row whose product is gone with a null product', function () {
    $stock = stockOf(productWithStock(3));
    app()->instance(ProductCatalog::class, new class implements ProductCatalog
    {
        public function findMany(ProductIds $ids): CatalogProducts
        {
            return new CatalogProducts;
        }
    });

    $page = app(StockListQuery::class)->paginateByQuantity(20);

    expect($page)->toHaveCount(1)
        ->and($page->first()->stock->id)->toBe($stock->id)
        ->and($page->first()->product)->toBeNull();
});
