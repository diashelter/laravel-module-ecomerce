<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Core\DTOs\AdjustStockDTO;
use Modules\Inventory\Core\Services\StockService;
use Modules\Inventory\Core\ValueObjects\StockRecord;
use Modules\Inventory\Persistence\Repositories\StockRepository;
use Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: increases or decreases a stock. The row is locked so an admin adjustment and a
 * concurrent checkout can never overwrite each other. It returns the data it wrote, never the model.
 */
final class AdjustStockUseCase
{
    public function __construct(
        private readonly StockRepository $stocks,
        private readonly StockService $stockService,
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

            return $this->stocks->update($locked, ['quantity' => $newQuantity])->toRecord();
        });
    }
}
