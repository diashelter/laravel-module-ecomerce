<?php

declare(strict_types=1);

namespace App\Modules\Catalog\ValueObjects;

use App\Modules\Catalog\Enums\ProductStatus;

/**
 * A product as the catalog hands it to another module (see ProductCatalog): never the model.
 */
final readonly class CatalogProduct
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $imageUrl,
        public int $priceCents,
        public ProductStatus $status,
    ) {}

    /**
     * Whether the product can be bought, given the units the inventory holds for it.
     */
    public function availability(int $unitsInStock): ProductAvailability
    {
        return new ProductAvailability($this->status, $unitsInStock);
    }
}
