<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\ValueObjects;

use Modules\Catalog\Core\ValueObjects\CatalogProduct;

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
