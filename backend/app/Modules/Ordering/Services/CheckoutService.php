<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Exceptions\InsufficientStockException;
use BcMath\Number;
use Illuminate\Database\Eloquent\Collection;

/**
 * Checkout business rules. Works on products already loaded (with their locked stock)
 * by PlaceOrderUseCase: no database access here.
 */
class CheckoutService
{
    public function __construct(private readonly PurchaseAvailabilityService $availability) {}

    /**
     * @param  array<int, int>  $quantities  [product_id => quantity]
     * @param  Collection<int, Product>  $products  keyed by id, with the `stock` relation set
     *
     * @throws InsufficientStockException
     */
    public function assertCanFulfil(array $quantities, Collection $products): void
    {
        $errors = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null) {
                $errors["items.{$productId}"] = ['Produto não encontrado.'];

                continue;
            }

            if (($problem = $this->availability->purchaseProblem($product, $product->stock, $quantity)) !== null) {
                $errors["items.{$productId}"] = [$problem];
            }
        }

        if ($errors !== []) {
            throw new InsufficientStockException($errors);
        }
    }

    /**
     * Prices always come from the database, never from the client.
     *
     * @param  array<int, int>  $quantities  [product_id => quantity]
     * @param  Collection<int, Product>  $products  keyed by id
     * @return array{total: string, lines: list<array<string, mixed>>}
     */
    public function buildOrderLines(array $quantities, Collection $products): array
    {
        $total = new Number('0.00');
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            $subtotal = new Number($product->price) * $quantity;
            $total = $total + $subtotal;

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $quantity,
                'subtotal' => (string) $subtotal,
            ];
        }

        return ['total' => (string) $total, 'lines' => $lines];
    }
}
