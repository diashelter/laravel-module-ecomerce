<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

/**
 * One line of a new order: a snapshot of the product name and price, taken from the database.
 */
final readonly class OrderLine
{
    public function __construct(
        public int $productId,
        public string $productName,
        public int $unitPriceCents,
        public int $quantity,
    ) {}

    public function subtotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }
}
