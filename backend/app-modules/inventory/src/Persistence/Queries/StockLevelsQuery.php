<?php

declare(strict_types=1);

namespace Modules\Inventory\Persistence\Queries;

use Modules\Catalog\Core\ValueObjects\ProductIds;
use Modules\Inventory\Contracts\StockLevels;
use Modules\Inventory\Core\ValueObjects\ProductStock;
use Modules\Inventory\Core\ValueObjects\ProductStocks;
use Modules\Inventory\Core\ValueObjects\StockQuantities;
use Modules\Inventory\Persistence\Models\Stock;

/**
 * Reads the stock of the given products for the other modules, without a lock.
 */
class StockLevelsQuery implements StockLevels
{
    public function quantitiesFor(ProductIds $productIds): StockQuantities
    {
        return new StockQuantities(Stock::query()
            ->whereIn('product_id', $productIds->all())
            ->orderBy('product_id')
            ->pluck('quantity', 'product_id'));
    }

    public function stocksFor(ProductIds $productIds): ProductStocks
    {
        return new ProductStocks(Stock::query()
            ->whereIn('product_id', $productIds->all())
            ->get(['id', 'product_id', 'quantity'])
            ->mapWithKeys(fn (Stock $stock) => [$stock->product_id => new ProductStock($stock->id, $stock->quantity)]));
    }
}
