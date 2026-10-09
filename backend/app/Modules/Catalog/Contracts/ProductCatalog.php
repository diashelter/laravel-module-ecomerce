<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Catalog\ValueObjects\ProductIds;

/**
 * Published by the catalog for the other modules: what they may know about a product,
 * as data. Whether it can be bought (active AND in stock) is decided by the ordering side.
 */
interface ProductCatalog
{
    /**
     * Ids with no product are simply absent from the result.
     */
    public function findMany(ProductIds $ids): CatalogProducts;
}
