<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Exceptions\InsufficientStockException;
use App\Modules\Ordering\ValueObjects\OrderLine;
use App\Modules\Ordering\ValueObjects\OrderLines;
use App\Modules\Ordering\ValueObjects\ProductQuantities;
use Illuminate\Database\Eloquent\Collection;

/**
 * Checkout business rules. Works on products already loaded (with their locked stock)
 * by PlaceOrderUseCase: no database access here.
 */
class CheckoutService
{
    public function __construct(private readonly PurchaseAvailabilityService $availability) {}

    /**
     * @param  Collection<int, Product>  $products  keyed by id, with the `stock` relation set
     *
     * @throws InsufficientStockException
     */
    public function assertCanFulfil(ProductQuantities $quantities, Collection $products): void
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
     * @param  Collection<int, Product>  $products  keyed by id
     */
    public function buildOrderLines(ProductQuantities $quantities, Collection $products): OrderLines
    {
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            $lines[] = new OrderLine($product->id, $product->name, $product->price_cents, $quantity);
        }

        return new OrderLines(...$lines);
    }
}
