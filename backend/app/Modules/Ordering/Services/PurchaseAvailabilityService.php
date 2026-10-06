<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;

/**
 * Single source of truth for the business rule:
 * a product can be bought only when it is active (catalog) AND has units in stock (inventory).
 *
 * Works on models already loaded by the caller: no database access here. The stock is passed
 * explicitly so callers decide where it comes from (eager loaded relation or locked row).
 */
class PurchaseAvailabilityService
{
    public function availableQuantity(?Stock $stock): int
    {
        return $stock?->quantity ?? 0;
    }

    public function isAvailable(Product $product, ?Stock $stock): bool
    {
        return $product->isActive() && ($stock?->hasUnits() ?? false);
    }

    /**
     * Returns why the requested quantity cannot be bought, or null when it can.
     * Shared by the cart validation and the checkout so both apply the same rule.
     */
    public function purchaseProblem(Product $product, ?Stock $stock, int $quantity): ?string
    {
        if (! $this->isAvailable($product, $stock)) {
            return 'Produto indisponível.';
        }

        if ($quantity > $this->availableQuantity($stock)) {
            return sprintf('Estoque insuficiente. Disponível: %d.', $this->availableQuantity($stock));
        }

        return null;
    }
}
