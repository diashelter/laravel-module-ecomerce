<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Queries;

use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\Contracts\StockLevels;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\ValueObjects\ProductStock;
use App\Modules\Inventory\ValueObjects\ProductStocks;
use App\Modules\Inventory\ValueObjects\StockQuantities;

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
