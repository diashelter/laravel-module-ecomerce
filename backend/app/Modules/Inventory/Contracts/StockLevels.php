<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\ValueObjects\ProductStocks;
use App\Modules\Inventory\ValueObjects\StockQuantities;

/**
 * Published by the inventory for the cart and the product pages: how many units each product has,
 * read without locking. The checkout reads them under a lock instead (see StockReservation).
 * Implemented by a query of the inventory, since it only reads.
 */
interface StockLevels
{
    /** The units of each product, zero for the ones without a stock row. */
    public function quantitiesFor(ProductIds $productIds): StockQuantities;

    /** The stock row (id and units) of each product that has one, in a single read. */
    public function stocksFor(ProductIds $productIds): ProductStocks;
}
