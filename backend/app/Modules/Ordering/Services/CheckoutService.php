<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Exceptions\InsufficientStockException;
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
     * @return array{total_cents: int, lines: list<array<string, mixed>>}
     */
    public function buildOrderLines(array $quantities, Collection $products): array
    {
        $totalCents = 0;
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            $subtotalCents = $product->price_cents * $quantity;
            $totalCents += $subtotalCents;

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price_cents' => $product->price_cents,
                'quantity' => $quantity,
                'subtotal_cents' => $subtotalCents,
            ];
        }

        return ['total_cents' => $totalCents, 'lines' => $lines];
    }
}
