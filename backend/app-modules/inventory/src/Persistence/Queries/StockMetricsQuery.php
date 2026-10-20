<?php

declare(strict_types=1);

namespace Modules\Inventory\Persistence\Queries;

use Modules\Inventory\Contracts\StockMetrics;
use Modules\Inventory\Core\ValueObjects\StockTotals;
use Modules\Inventory\Persistence\Models\Stock;

/**
 * Counts what the stock holds for the admin dashboard.
 */
class StockMetricsQuery implements StockMetrics
{
    public function totals(): StockTotals
    {
        return new StockTotals(
            productsInStock: Stock::query()->where('quantity', '>', 0)->count(),
            totalUnits: (int) Stock::query()->sum('quantity'),
        );
    }
}
