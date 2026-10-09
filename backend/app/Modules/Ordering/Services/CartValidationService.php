<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Inventory\ValueObjects\StockQuantities;
use App\Modules\Ordering\ValueObjects\ProductQuantities;
use App\Modules\Ordering\ValueObjects\ValidatedCart;
use App\Modules\Ordering\ValueObjects\ValidatedCartLine;

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

            $lines[] = new ValidatedCartLine(
                productId: $product->id,
                name: $product->name,
                imageUrl: $product->imageUrl,
                unitPriceCents: $product->priceCents,
                quantity: $quantity,
                availableQuantity: $stock->of($productId),
                isAvailable: $this->availability->isAvailable($product->isActive, $stock->of($productId)),
                problem: $this->availability->purchaseProblem($product->isActive, $stock->of($productId), $quantity),
            );
        }

        return new ValidatedCart(...$lines);
    }
}
