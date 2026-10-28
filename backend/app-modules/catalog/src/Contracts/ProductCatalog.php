<?php

declare(strict_types=1);

namespace Modules\Catalog\Contracts;

use Modules\Catalog\Core\ValueObjects\CatalogProducts;
use Modules\Catalog\Core\ValueObjects\ProductIds;

/**
 * Published by the catalog for the other modules: what they may know about a product,
 * as data. Whether it can be bought (active AND in stock) is decided by the catalog's ProductAvailability,
 * which CatalogProduct::availability() answers with the units the caller read from the inventory.
 */
interface ProductCatalog
{
    /**
     * Ids with no product are simply absent from the result.
     */
    public function findMany(ProductIds $ids): CatalogProducts;
}
