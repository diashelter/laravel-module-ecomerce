<?php

declare(strict_types=1);

namespace App\Modules\Catalog\ValueObjects;

/**
 * A product as the catalog hands it to another module (see ProductCatalog): never the model.
 */
final readonly class CatalogProduct
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $imageUrl,
        public int $priceCents,
        public bool $isActive,
    ) {}
}
