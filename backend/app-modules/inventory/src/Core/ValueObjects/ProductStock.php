<?php

declare(strict_types=1);

namespace Modules\Inventory\Core\ValueObjects;

/**
 * The stock row of a product: the id the admin adjusts it by, and the units it holds.
 */
final readonly class ProductStock
{
    public function __construct(
        public string $id,
        public int $quantity,
    ) {}
}
