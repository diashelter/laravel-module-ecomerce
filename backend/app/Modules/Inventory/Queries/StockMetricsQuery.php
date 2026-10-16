<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Queries;

use App\Modules\Inventory\Contracts\StockMetrics;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\ValueObjects\StockTotals;

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
