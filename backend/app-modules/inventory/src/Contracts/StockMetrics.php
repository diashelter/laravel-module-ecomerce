<?php

declare(strict_types=1);

namespace Modules\Inventory\Contracts;

use Modules\Inventory\Core\ValueObjects\StockTotals;

/**
 * Published by the inventory for the admin dashboard: what the stock holds, as data. Implemented by
 * a query of the inventory, since it only reads.
 */
interface StockMetrics
{
    public function totals(): StockTotals;
}
