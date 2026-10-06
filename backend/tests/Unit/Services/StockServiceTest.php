<?php

use App\Modules\Inventory\DTOs\AdjustStockDTO;
use App\Modules\Inventory\Enums\StockOperation;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

it('calculates the new quantity', function (StockOperation $operation, int $expected) {
    expect((new StockService)->calculateNewQuantity(5, new AdjustStockDTO($operation, 3)))->toBe($expected);
})->with([
    'increase' => [StockOperation::Increase, 8],
    'decrease' => [StockOperation::Decrease, 2],
]);

it('allows decreasing the stock to zero', function () {
    expect((new StockService)->calculateNewQuantity(5, new AdjustStockDTO(StockOperation::Decrease, 5)))->toBe(0);
});

it('never lets the stock become negative', function () {
    try {
        (new StockService)->calculateNewQuantity(2, new AdjustStockDTO(StockOperation::Decrease, 3));
        $this->fail('BusinessRuleException was not thrown.');
    } catch (BusinessRuleException $e) {
        expect($e->getMessage())->toBe('O estoque não pode ficar negativo.')
            ->and($e->errors())->toBe(['quantity' => ['Estoque atual: 2. Não é possível reduzir 3 unidades.']])
            ->and($e->render()->getStatusCode())->toBe(422);
    }
});
