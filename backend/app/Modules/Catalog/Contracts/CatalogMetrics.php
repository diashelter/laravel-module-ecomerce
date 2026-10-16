<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\ValueObjects\ProductCountsByStatus;

/**
 * Published by the catalog for the admin dashboard: how many products it has, as data. Implemented
 * by a query of the catalog, since it only reads.
 */
interface CatalogMetrics
{
    public function productCountsByStatus(): ProductCountsByStatus;
}
