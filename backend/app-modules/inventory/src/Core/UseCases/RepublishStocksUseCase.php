<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Events\StockChanged;
use Modules\Inventory\Persistence\Queries\StockRepublicationQuery;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Shared\Exceptions\RepublicationFailedException;
use Throwable;

/**
 * Rebuild of the storefront, the inventory's part: records one inventory.stock_changed per stock row through the
 * outbox, with the quantity and the version the row has now. Each record is its own transaction, so a failure halfway
 * leaves exactly the records it counted, and a second run converges to the same storefront.
 */
final class RepublishStocksUseCase
{
    public function __construct(
        private readonly StockRepublicationQuery $query,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @return int how many it recorded
     *
     * @throws RepublicationFailedException
     */
    public function execute(): int
    {
        $stocks = 0;

        try {
            foreach ($this->query->stocks() as $stock) {
                DB::transaction(fn () => $this->outbox->record(new StockChanged($stock->id, $stock->productId, $stock->quantity, $stock->version)));
                $stocks++;
            }
        } catch (Throwable $thrown) {
            throw new RepublicationFailedException($stocks, $thrown);
        }

        return $stocks;
    }
}
