<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Services;

use Modules\Catalog\Core\ValueObjects\ProductAvailability;

/**
 * The messages of the cart and the checkout about a product that cannot be bought. The rule itself
 * (active AND with units in stock) belongs to the catalog's ProductAvailability; this class only
 * words the problem, so both flows explain it the same way.
 */
class PurchaseAvailabilityService
{
    /**
     * Returns why the requested quantity cannot be bought, or null when it can.
     */
    public function purchaseProblem(ProductAvailability $availability, int $requested): ?string
    {
        if (! $availability->isAvailable()) {
            return 'Produto indisponível.';
        }

        if ($requested > $availability->availableQuantity()) {
            return sprintf('Estoque insuficiente. Disponível: %d.', $availability->availableQuantity());
        }

        return null;
    }
}
