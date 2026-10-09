<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\ValueObjects\StockQuantities;

/**
 * Published by the inventory for the checkout: locks the stock of the products being bought
 * and deducts the sold units.
 *
 * Both methods must run inside the caller's transaction: the locks are released only when
 * that transaction commits or rolls back.
 */
interface StockReservation
{
    /**
     * SELECT ... FOR UPDATE on the stock rows of the given products. Rows are always locked in
     * the same order (by product_id), so concurrent checkouts never deadlock. Returns the
     * quantities read under the lock.
     */
    public function lockForProducts(ProductIds $productIds): StockQuantities;

    /**
     * Deducts sold units from the stock of a product previously locked with lockForProducts().
     */
    public function decrement(int $productId, int $quantity): void;
}
