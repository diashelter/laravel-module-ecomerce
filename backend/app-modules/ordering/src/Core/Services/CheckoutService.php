<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Services;

use Modules\Catalog\Core\ValueObjects\CatalogProducts;
use Modules\Inventory\Core\ValueObjects\StockQuantities;
use Modules\Ordering\Core\Exceptions\InsufficientStockException;
use Modules\Ordering\Core\ValueObjects\OrderLine;
use Modules\Ordering\Core\ValueObjects\OrderLines;
use Modules\Ordering\Core\ValueObjects\ProductQuantities;

/**
 * Checkout business rules. Works on the catalog products and the stock quantities that
 * PlaceOrderUseCase read under the lock: no database access here.
 */
class CheckoutService
{
    public function __construct(private readonly PurchaseAvailabilityService $availability) {}

    /**
     * @param  StockQuantities  $stock  read under the checkout lock
     *
     * @throws InsufficientStockException
     */
    public function assertCanFulfil(ProductQuantities $quantities, CatalogProducts $products, StockQuantities $stock): void
    {
        $errors = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->find($productId);

            if ($product === null) {
                $errors["items.{$productId}"] = ['Produto não encontrado.'];

                continue;
            }

            if (($problem = $this->availability->purchaseProblem($product->availability($stock->of($productId)), $quantity)) !== null) {
                $errors["items.{$productId}"] = [$problem];
            }
        }

        if ($errors !== []) {
            throw new InsufficientStockException($errors);
        }
    }

    /**
     * Prices always come from the catalog, never from the client.
     */
    public function buildOrderLines(ProductQuantities $quantities, CatalogProducts $products): OrderLines
    {
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->find($productId);

            $lines[] = new OrderLine($product->id, $product->name, $product->priceCents, $quantity);
        }

        return new OrderLines(...$lines);
    }
}
