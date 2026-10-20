<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\ValueObjects;

/**
 * What the inventory holds, in two numbers: the products that have units, and the units.
 */
final readonly class StockTotals
{
    public function __construct(
        public int $productsInStock,
        public int $totalUnits,
    ) {}
}
