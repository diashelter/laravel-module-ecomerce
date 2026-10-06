<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

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
     * the same order (by product_id), so concurrent checkouts never deadlock.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Stock> keyed by product_id
     */
    public function lockForProducts(array $productIds): Collection;

    /**
     * Deducts sold units from a stock row previously locked with lockForProducts().
     */
    public function decrement(Stock $stock, int $quantity): void;
}
