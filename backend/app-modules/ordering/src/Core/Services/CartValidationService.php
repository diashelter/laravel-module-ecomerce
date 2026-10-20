<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Services;

use Modules\Catalog\Core\ValueObjects\CatalogProducts;
use Modules\Inventory\Core\ValueObjects\StockQuantities;
use Modules\Ordering\Core\ValueObjects\ProductQuantities;
use Modules\Ordering\Core\ValueObjects\ValidatedCart;
use Modules\Ordering\Core\ValueObjects\ValidatedCartLine;

/**
 * Re-checks a cart against the current products before checkout.
 * The checkout itself checks everything again inside the transaction.
 */
class CartValidationService
{
    public function __construct(private readonly PurchaseAvailabilityService $availability) {}

    public function validate(ProductQuantities $quantities, CatalogProducts $products, StockQuantities $stock): ValidatedCart
    {
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->find($productId);

            if ($product === null) {
                $lines[] = new ValidatedCartLine($productId, null, null, null, $quantity, 0, false, 'Produto não encontrado.');

                continue;
            }

            $availability = $product->availability($stock->of($productId));

            $lines[] = new ValidatedCartLine(
                productId: $product->id,
                name: $product->name,
                imageUrl: $product->imageUrl,
                unitPriceCents: $product->priceCents,
                quantity: $quantity,
                availableQuantity: $availability->availableQuantity(),
                isAvailable: $availability->isAvailable(),
                problem: $this->availability->purchaseProblem($availability, $quantity),
            );
        }

        return new ValidatedCart(...$lines);
    }
}
