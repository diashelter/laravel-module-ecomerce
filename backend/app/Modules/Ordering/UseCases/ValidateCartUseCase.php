<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\Services\CartValidationService;

/**
 * Visitor: re-checks the cart against the database before checkout (read only, no locks).
 */
final class ValidateCartUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CartValidationService $cartValidation,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, total: string, is_valid: bool}
     */
    public function execute(CartDTO $cart): array
    {
        $productIds = array_keys($cart->quantitiesByProduct());

        $products = $this->products->findManyKeyedById($productIds, withStock: true);

        return $this->cartValidation->validate($cart, $products);
    }
}
