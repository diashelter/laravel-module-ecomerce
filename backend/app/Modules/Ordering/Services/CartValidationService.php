<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\DTOs\CartDTO;
use BcMath\Number;
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
     * @return array{items: list<array<string, mixed>>, total: string, is_valid: bool}
     */
    public function validate(CartDTO $cart, Collection $products): array
    {
        $quantities = $cart->quantitiesByProduct();

        $lines = [];
        $total = new Number('0.00');
        $isValid = true;

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null) {
                $isValid = false;
                $lines[] = [
                    'product_id' => $productId,
                    'name' => null,
                    'image_url' => null,
                    'unit_price' => null,
                    'quantity' => $quantity,
                    'subtotal' => null,
                    'available_quantity' => 0,
                    'is_available' => false,
                    'problem' => 'Produto não encontrado.',
                ];

                continue;
            }

            $problem = $this->availability->purchaseProblem($product, $product->stock, $quantity);
            $subtotal = new Number($product->price) * $quantity;

            if ($problem === null) {
                $total = $total + $subtotal;
            } else {
                $isValid = false;
            }

            $lines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url,
                'unit_price' => $product->price,
                'quantity' => $quantity,
                'subtotal' => (string) $subtotal,
                'available_quantity' => $this->availability->availableQuantity($product->stock),
                'is_available' => $this->availability->isAvailable($product, $product->stock),
                'problem' => $problem,
            ];
        }

        return [
            'items' => $lines,
            'total' => (string) $total,
            'is_valid' => $isValid && $lines !== [],
        ];
    }
}
