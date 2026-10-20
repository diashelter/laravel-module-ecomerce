<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Queries;

use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Catalog\Core\ValueObjects\ProductCountsByStatus;
use Modules\Catalog\Persistence\Models\Product;

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
