<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\ValueObjects;

use Modules\Catalog\Core\Enums\ProductStatus;

/**
 * Single source of truth for the business rule: a product can be bought only when it is active
 * (catalog) AND has units in stock (inventory). The storefront, the stock list, the cart and the
 * checkout all read the rule from here.
 *
 * Works on data the caller already has: the units are zero when the product has no stock row.
 */
final readonly class ProductAvailability
{
    public function __construct(
        private ProductStatus $status,
        private int $unitsInStock,
    ) {}

    public function isAvailable(): bool
    {
        return $this->status === ProductStatus::Active && $this->unitsInStock > 0;
    }

    /** The units in stock, also for a product that is not active. */
    public function availableQuantity(): int
    {
        return $this->unitsInStock;
    }
}
