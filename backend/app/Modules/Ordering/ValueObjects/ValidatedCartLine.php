<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

/**
 * One line of a validated cart. A product that does not exist has no name, image or price
 * (`unitPriceCents` null), and so no subtotal.
 */
final readonly class ValidatedCartLine
{
    public function __construct(
        public int $productId,
        public ?string $name,
        public ?string $imageUrl,
        public ?int $unitPriceCents,
        public int $quantity,
        public int $availableQuantity,
        public bool $isAvailable,
        public ?string $problem,
    ) {}

    public function subtotalCents(): ?int
    {
        return $this->unitPriceCents === null ? null : $this->unitPriceCents * $this->quantity;
    }

    public function hasProblem(): bool
    {
        return $this->problem !== null;
    }
}
