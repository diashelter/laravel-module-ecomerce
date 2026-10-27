<?php

declare(strict_types=1);

namespace Modules\Inventory\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\ValueObjects\ProductIds;
use Modules\Inventory\Contracts\StockInitializer;
use Modules\Inventory\Contracts\StockReservation;
use Modules\Inventory\Core\ValueObjects\StockQuantities;
use Modules\Inventory\Events\StockChanged;
use Modules\Inventory\Persistence\Models\Stock;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Shared\Repositories\BaseRepository;

/**
 * The inventory's writes. Other parts of the system never use this class directly: the catalog and
 * the checkout depend on the StockInitializer and StockReservation contracts. The reads live in
 * the Queries folder (StockLevels, StockMetrics and the admin list).
 *
 * Both contracts are called from inside the transaction of the caller, so the repository records the
 * inventory.stock_changed of the row it wrote in that same transaction.
 *
 * @extends BaseRepository<Stock>
 */
class StockRepository extends BaseRepository implements StockInitializer, StockReservation
{
    public function __construct(private readonly EventOutbox $outbox) {}

    protected function model(): string
    {
        return Stock::class;
    }

    /**
     * Product 1:1 Stock: the stock row is created together with the product.
     */
    public function createForProduct(string $productId, int $quantity): void
    {
        $stock = $this->create(['product_id' => $productId, 'quantity' => $quantity, 'version' => 1]);

        $this->outbox->record(new StockChanged($stock->id, $productId, $quantity, 1));
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
     * UPDATE stocks SET quantity = quantity - ?, version = version + 1 (safe only while the row is locked). The row
     * comes back from the same statement, so the event carries the quantity left and the new version without a read.
     */
    public function decrement(string $productId, int $quantity): void
    {
        $row = DB::selectOne(
            'update stocks set quantity = quantity - ?, version = version + 1 where product_id = ? returning id, quantity, version',
            [$quantity, $productId],
        );

        if ($row !== null) {
            $this->outbox->record(new StockChanged($row->id, $productId, $row->quantity, $row->version));
        }
    }
}
