<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Repositories;

use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\Contracts\StockInitializer;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\ValueObjects\StockQuantities;
use App\Modules\Shared\Repositories\BaseRepository;

/**
 * The inventory's writes. Other parts of the system never use this class directly: the catalog and
 * the checkout depend on the StockInitializer and StockReservation contracts. The reads live in
 * the Queries folder (StockLevels, StockMetrics and the admin list).
 *
 * @extends BaseRepository<Stock>
 */
class StockRepository extends BaseRepository implements StockInitializer, StockReservation
{
    protected function model(): string
    {
        return Stock::class;
    }

    /**
     * Product 1:1 Stock: the stock row is created together with the product.
     */
    public function createForProduct(string $productId, int $quantity): void
    {
        $this->create(['product_id' => $productId, 'quantity' => $quantity]);
    }

    /**
     * SELECT ... FOR UPDATE on the stock rows of the given products. Must run inside a
     * transaction. Rows are always locked in the same order (by product_id) to avoid deadlocks.
     */
    public function lockForProducts(ProductIds $productIds): StockQuantities
    {
        return new StockQuantities($this->query()
            ->whereIn('product_id', $productIds->all())
            ->orderBy('product_id')
            ->lockForUpdate()
            ->pluck('quantity', 'product_id'));
    }

    /**
     * Re-reads a single stock row with a lock. Must run inside a transaction.
     */
    public function lockById(string $id): Stock
    {
        return $this->query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * UPDATE stocks SET quantity = quantity - ? (safe only while the row is locked).
     */
    public function decrement(string $productId, int $quantity): void
    {
        $this->query()->where('product_id', $productId)->decrement('quantity', $quantity);
    }
}
