<?php

declare(strict_types=1);

namespace App\Modules\Inventory\DTOs;

use App\Modules\Inventory\Enums\StockOperation;

final readonly class AdjustStockDTO
{
    public function __construct(
        public StockOperation $operation,
        public int $quantity,
    ) {}
}
