<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

/**
 * Single source of truth for the business rule:
 * a product can be bought only when it is active (catalog) AND has units in stock (inventory).
 *
 * Works on data the caller already has: no database access here. The quantity is null when
 * the product has no stock row, which counts as zero units.
 */
class PurchaseAvailabilityService
{
    public function availableQuantity(?int $quantity): int
    {
        return $quantity ?? 0;
    }

    public function isAvailable(bool $isActive, ?int $quantity): bool
    {
        return $isActive && $this->availableQuantity($quantity) > 0;
    }

    /**
     * Returns why the requested quantity cannot be bought, or null when it can.
     * Shared by the cart validation and the checkout so both apply the same rule.
     */
    public function purchaseProblem(bool $isActive, ?int $quantity, int $requested): ?string
    {
        if (! $this->isAvailable($isActive, $quantity)) {
            return 'Produto indisponível.';
        }

        if ($requested > $this->availableQuantity($quantity)) {
            return sprintf('Estoque insuficiente. Disponível: %d.', $this->availableQuantity($quantity));
        }

        return null;
    }
}
