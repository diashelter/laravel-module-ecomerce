<?php

declare(strict_types=1);

namespace App\Modules\Catalog\ValueObjects;

/**
 * A category as the product pages show it.
 */
final readonly class ProductCategory
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
    ) {}
}
