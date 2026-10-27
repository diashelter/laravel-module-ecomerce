<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Core\DTOs\AdjustStockDTO;
use Modules\Inventory\Core\Services\StockService;
use Modules\Inventory\Core\ValueObjects\StockRecord;
use Modules\Inventory\Events\StockChanged;
use Modules\Inventory\Persistence\Repositories\StockRepository;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: increases or decreases a stock. The row is locked so an admin adjustment and a
 * concurrent checkout can never overwrite each other, and the new version and the inventory.stock_changed event come
 * from the locked row, in the same transaction. It returns the data it wrote, never the model.
 */
final class AdjustStockUseCase
{
    public function __construct(
        private readonly StockRepository $stocks,
        private readonly StockService $stockService,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @throws BusinessRuleException
     * @throws ModelNotFoundException
     */
    public function execute(string $stockId, AdjustStockDTO $data): StockRecord
    {
        return DB::transaction(function () use ($stockId, $data): StockRecord {
            $locked = $this->stocks->lockById($stockId);

            $newQuantity = $this->stockService->calculateNewQuantity($locked->quantity, $data);

            $record = $this->stocks->update($locked, ['quantity' => $newQuantity, 'version' => $locked->version + 1])->toRecord();

            $this->outbox->record(new StockChanged($record->id, $record->productId, $record->quantity, $record->version));

            return $record;
        });
    }
}
