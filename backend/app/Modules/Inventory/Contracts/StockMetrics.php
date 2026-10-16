<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\ValueObjects\StockTotals;

/**
 * Published by the inventory for the admin dashboard: what the stock holds, as data. Implemented by
 * a query of the inventory, since it only reads.
 */
interface StockMetrics
{
    public function totals(): StockTotals;
}
