<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * What a stock adjustment leaves behind: the data of the row it wrote, never the model. The read
 * side completes it with the product (see StockListQuery::complete) without reading `stocks` again.
 */
final readonly class StockRecord
{
    public function __construct(
        public string $id,
        public string $productId,
        public int $quantity,
        public ?CarbonImmutable $updatedAt,
    ) {}
}
