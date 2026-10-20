<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\DTOs;

use Modules\Inventory\Core\Enums\StockOperation;

final readonly class AdjustStockDTO
{
    public function __construct(
        public StockOperation $operation,
        public int $quantity,
    ) {}
}
