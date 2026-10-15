<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UseCases;

use App\Modules\Inventory\DTOs\AdjustStockDTO;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Repositories\StockRepository;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Admin: increases or decreases a stock. The row is locked so an admin adjustment and a
 * concurrent checkout can never overwrite each other.
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
    public function execute(string $stockId, AdjustStockDTO $data): Stock
    {
        $stock = DB::transaction(function () use ($stockId, $data): Stock {
            $locked = $this->stocks->lockById($stockId);

            $newQuantity = $this->stockService->calculateNewQuantity($locked->quantity, $data);

            return $this->stocks->update($locked, ['quantity' => $newQuantity]);
        });

        return $stock->load('product');
    }
}
