<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\Services;

use Modules\Inventory\Core\DTOs\AdjustStockDTO;
use Modules\Inventory\Core\Enums\StockOperation;
use Modules\Shared\Exceptions\BusinessRuleException;
use Symfony\Component\HttpFoundation\Response;

class StockService
{
    /**
     * Applies an increase/decrease to the current quantity. A stock can never be negative.
     *
     * @throws BusinessRuleException
     */
    public function calculateNewQuantity(int $currentQuantity, AdjustStockDTO $data): int
    {
        $amount = $data->quantity;

        $newQuantity = match ($data->operation) {
            StockOperation::Increase => $currentQuantity + $amount,
            StockOperation::Decrease => $currentQuantity - $amount,
        };

        if ($newQuantity < 0) {
            throw new BusinessRuleException(
                'O estoque não pode ficar negativo.',
                ['quantity' => [sprintf('Estoque atual: %d. Não é possível reduzir %d unidades.', $currentQuantity, $amount)]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $newQuantity;
    }
}
