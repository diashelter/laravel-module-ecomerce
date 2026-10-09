<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\ValueObjects\StockQuantities;

/**
 * Published by the inventory for the cart: how many units each product has, read without
 * locking. The checkout reads them under a lock instead (see StockReservation).
 */
interface StockLevels
{
    public function quantitiesFor(ProductIds $productIds): StockQuantities;
}
