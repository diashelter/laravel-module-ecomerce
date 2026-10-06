<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/**
 * What the catalog needs to know about past orders, without depending on the ordering module:
 * a product that was already ordered cannot be deleted (it would break the order history).
 *
 * Defined here, by the catalog, and implemented by the ordering module (dependency inversion),
 * so the dependency keeps pointing from ordering to catalog only.
 */
interface ProductOrderHistory
{
    public function hasBeenOrdered(int $productId): bool;
}
