<?php

use Modules\Inventory\Core\DTOs\AdjustStockDTO;
use Modules\Inventory\Core\Enums\StockOperation;
use Modules\Inventory\Core\UseCases\AdjustStockUseCase;
use Modules\Inventory\Core\ValueObjects\StockRecord;
use Modules\Inventory\Persistence\Models\Stock;

it('returns the written stock row from the adjustment', function () {
    $product = productWithStock(5);
    $stock = stockOf($product);

    $record = app(AdjustStockUseCase::class)->execute($stock->id, new AdjustStockDTO(StockOperation::Increase, 10));
    $row = Stock::query()->findOrFail($stock->id);

    expect($record)->toBeInstanceOf(StockRecord::class)
        ->and($record->id)->toBe($stock->id)
        ->and($record->productId)->toBe($product->id)
        ->and($record->quantity)->toBe(15)
        ->and($row->quantity)->toBe(15)
        ->and($record->updatedAt?->toIso8601String())->toBe($row->updated_at->toIso8601String());
});
