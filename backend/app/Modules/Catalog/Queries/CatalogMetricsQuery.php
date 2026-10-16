<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Contracts\CatalogMetrics;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\ProductCountsByStatus;

/**
 * Counts the products for the admin dashboard.
 */
class CatalogMetricsQuery implements CatalogMetrics
{
    public function productCountsByStatus(): ProductCountsByStatus
    {
        return new ProductCountsByStatus(Product::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status'));
    }
}
