<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Repositories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Contracts\StockInitializer;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * The inventory's data access. Other parts of the system never use this class directly:
 * the catalog and the checkout depend on the StockInitializer and StockReservation contracts.
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
    public function createForProduct(Product $product, int $quantity): Stock
    {
        return $product->stock()->create(['quantity' => $quantity]);
    }

    /** @return LengthAwarePaginator<int, Stock> */
    public function paginateByQuantity(int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with('product')
            ->orderBy('quantity')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * SELECT ... FOR UPDATE on the stock rows of the given products. Must run inside a
     * transaction. Rows are always locked in the same order (by product_id) to avoid deadlocks.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Stock> keyed by product_id
     */
    public function lockForProducts(array $productIds): Collection
    {
        return $this->query()
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Re-reads a single stock row with a lock. Must run inside a transaction.
     */
    public function lockById(int $id): Stock
    {
        return $this->query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * UPDATE stocks SET quantity = quantity - ? (safe only while the row is locked).
     */
    public function decrement(Stock $stock, int $quantity): void
    {
        $stock->decrement('quantity', $quantity);
    }

    public function countInStock(): int
    {
        return $this->query()->where('quantity', '>', 0)->count();
    }

    public function totalUnits(): int
    {
        return (int) $this->query()->sum('quantity');
    }
}
