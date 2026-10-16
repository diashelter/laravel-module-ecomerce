<?php

declare(strict_types=1);

namespace App\Modules\Inventory\ValueObjects;

use App\Modules\Catalog\ValueObjects\CatalogProduct;

/**
 * A stock row as the admin list shows it: the row and its product (null when the catalog no longer
 * has it).
 */
final readonly class StockView
{
    public function __construct(
        public StockRecord $stock,
        public ?CatalogProduct $product,
    ) {}
}
