<?php

declare(strict_types=1);

namespace App\Modules\Catalog\ValueObjects;

use App\Modules\Inventory\ValueObjects\ProductStock;

/**
 * A product as the storefront and the admin product pages show it: the product row, its categories
 * and the stock row the inventory holds for it (null when it has none).
 */
final readonly class ProductView
{
    public function __construct(
        public ProductRecord $product,
        public ProductCategories $categories,
        public ?ProductStock $stock,
    ) {}

    /** A product without a stock row has no units, so it cannot be bought. */
    public function availability(): ProductAvailability
    {
        return new ProductAvailability($this->product->status, $this->stock?->quantity ?? 0);
    }
}
