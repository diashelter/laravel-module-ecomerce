<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\ValueObjects\ProductQuantities;
use App\Modules\Ordering\ValueObjects\ValidatedCart;
use App\Modules\Ordering\ValueObjects\ValidatedCartLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Re-checks a cart against the current products before checkout.
 * The checkout itself checks everything again inside the transaction.
 */
class CartValidationService
{
    public function __construct(private readonly PurchaseAvailabilityService $availability) {}

    /**
     * @param  Collection<int, Product>  $products  keyed by id, with the `stock` relation loaded
     */
    public function validate(ProductQuantities $quantities, Collection $products): ValidatedCart
    {
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null) {
                $lines[] = new ValidatedCartLine($productId, null, null, null, $quantity, 0, false, 'Produto não encontrado.');

                continue;
            }

            $lines[] = new ValidatedCartLine(
                productId: $product->id,
                name: $product->name,
                imageUrl: $product->image_url,
                unitPriceCents: $product->price_cents,
                quantity: $quantity,
                availableQuantity: $this->availability->availableQuantity($product->stock),
                isAvailable: $this->availability->isAvailable($product, $product->stock),
                problem: $this->availability->purchaseProblem($product, $product->stock, $quantity),
            );
        }

        return new ValidatedCart(...$lines);
    }
}
