<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Inventory\Contracts\StockLevels;
use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\Services\CartValidationService;
use App\Modules\Ordering\ValueObjects\ValidatedCart;

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
