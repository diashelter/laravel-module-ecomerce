<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\Services\CartValidationService;
use App\Modules\Ordering\ValueObjects\ValidatedCart;

/**
 * Visitor: re-checks the cart against the database before checkout (read only, no locks).
 */
final class ValidateCartUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CartValidationService $cartValidation,
    ) {}

    public function execute(CartDTO $cart): ValidatedCart
    {
        $quantities = $cart->quantities();

        $products = $this->products->findManyKeyedById($quantities->productIds(), withStock: true);

        return $this->cartValidation->validate($quantities, $products);
    }
}
