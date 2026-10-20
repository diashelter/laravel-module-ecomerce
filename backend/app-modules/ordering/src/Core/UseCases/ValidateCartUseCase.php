<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\UseCases;

use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Inventory\Contracts\StockLevels;
use Modules\Ordering\Core\DTOs\CartDTO;
use Modules\Ordering\Core\Services\CartValidationService;
use Modules\Ordering\Core\ValueObjects\ValidatedCart;

/**
 * Visitor: re-checks the cart against the database before checkout (read only, no locks).
 */
final class ValidateCartUseCase
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly StockLevels $stockLevels,
        private readonly CartValidationService $cartValidation,
    ) {}

    public function execute(CartDTO $cart): ValidatedCart
    {
        $quantities = $cart->quantities();
        $productIds = $quantities->productIds();

        return $this->cartValidation->validate(
            $quantities,
            $this->catalog->findMany($productIds),
            $this->stockLevels->quantitiesFor($productIds),
        );
    }
}
