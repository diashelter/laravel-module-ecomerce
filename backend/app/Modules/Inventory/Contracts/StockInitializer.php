<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;

/**
 * Published by the inventory for the catalog: opens the stock of a newly created product.
 *
 * Runs inside the caller's transaction, so the product and its stock are created atomically.
 */
interface StockInitializer
{
    /**
     * Product 1:1 Stock: called once, right after the product is created.
     */
    public function createForProduct(Product $product, int $quantity): Stock;
}
